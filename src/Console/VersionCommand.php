<?php

namespace MediaQ\Versioning\Console;

use RuntimeException;
use InvalidArgumentException;
use MediaQ\Versioning\Version;
use Illuminate\Console\Command;
use MediaQ\Versioning\VersionFile;
use MediaQ\Versioning\GitRepository;

class VersionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'version
                            {version? : Bump type (patch, minor, major, prepatch, preminor, premajor, prerelease) or an explicit version like 2.1.5}
                            {--show : Show the current version and exit}
                            {--dry-run : Show what would change without writing anything}
                            {--preid= : Pre-release identifier, defaults to the configured value}
                            {--commit : Commit the version change}
                            {--tag : Create an annotated git tag, implies --commit}
                            {--push : Push the commit and the tag, implies --commit}
                            {--allow-dirty : Allow committing with a dirty working tree}
                            {--message= : Commit message, use :version as placeholder}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bump the application version in composer.json (npm version style)';

    /**
     * The bump types that are supported.
     *
     * @var array<int, string>
     */
    private const BUMP_TYPES = ['patch', 'minor', 'major', 'prepatch', 'preminor', 'premajor', 'prerelease'];

    public function handle(): int
    {
        $versionFile = new VersionFile($this->composerJsonPath());
        $current = $versionFile->read();
        $requested = $this->argument('version');

        if ($this->option('show') || $requested === null) {
            $this->line($current instanceof Version
                ? $current->toString()
                : sprintf('No version found in %s.', $versionFile->path()));

            return self::SUCCESS;
        }

        try {
            $target = $this->resolveTargetVersion($current, (string) $requested);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line(sprintf(
            '%s: %s -> %s',
            $versionFile->path(),
            $current?->toString() ?? '(none)',
            $target->toString(),
        ));

        $git = new GitRepository(dirname($versionFile->path()));
        $commitMessage = $this->commitMessage($target);
        $tagName = $this->tagPrefix() . $target->toString();
        $createTag = (bool) $this->option('tag') || (bool) $this->option('push');
        $push = (bool) $this->option('push');
        $wantsCommit = (bool) $this->option('commit') || $createTag;
        $isDryRun = (bool) $this->option('dry-run');
        $stagedPath = $git->relativePath($versionFile->path());
        $branch = null;

        if ($wantsCommit) {
            $problem = $this->gitPreflight($git, $tagName, $createTag);

            if ($problem !== null) {
                $this->error($problem);

                return self::FAILURE;
            }

            $branch = $git->currentBranch();

            if ($branch === null) {
                $this->error('Unable to determine the current git branch (detached HEAD?).');

                return self::FAILURE;
            }
        }

        if ($isDryRun) {
            return $this->dryRun($versionFile, $git, $target, $wantsCommit, $createTag, $push, $stagedPath, $commitMessage, $tagName, $branch);
        }

        try {
            $versionFile->write($target);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Version updated to %s.', $target->toString()));

        if (! $wantsCommit) {
            $this->newLine();
            $this->comment('Nothing was committed. Re-run with --commit (and optionally --tag / --push) to create a release.');
            $this->comment('Running with a cached config? Refresh it with: php artisan config:cache');

            return self::SUCCESS;
        }

        return $this->release($git, $target, (string) $branch, $stagedPath, $commitMessage, $tagName, $createTag, $push);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function resolveTargetVersion(?Version $current, string $requested): Version
    {
        if (! in_array($requested, self::BUMP_TYPES, true)) {
            return Version::parse($requested);
        }

        if (! $current instanceof Version) {
            throw new InvalidArgumentException(
                'Cannot bump without a current version. Pass an explicit version instead, for example: php artisan version 1.0.0',
            );
        }

        return $current->bump($requested, $this->preIdentifier());
    }

    private function gitPreflight(GitRepository $git, string $tagName, bool $createTag): ?string
    {
        if (! $git->isAvailable()) {
            return 'Git is not available on this machine.';
        }

        if (! $git->isRepository()) {
            return sprintf('%s is not inside a git repository.', $git->workingDirectory());
        }

        if (! $this->option('allow-dirty') && ! $git->isClean()) {
            return 'The working tree has uncommitted changes. Commit or stash them first, or pass --allow-dirty.';
        }

        if ($createTag && $git->hasTag($tagName)) {
            return sprintf('Tag %s already exists.', $tagName);
        }

        return null;
    }

    private function dryRun(
        VersionFile $versionFile,
        GitRepository $git,
        Version $target,
        bool $wantsCommit,
        bool $createTag,
        bool $push,
        string $stagedPath,
        string $commitMessage,
        string $tagName,
        ?string $branch,
    ): int {
        $this->newLine();
        $this->comment('Dry run: nothing was written.');
        $this->line(sprintf('Would write %s -> %s', $versionFile->path(), $target->toString()));

        if (! $wantsCommit) {
            return self::SUCCESS;
        }

        $this->line(sprintf('Would run: git add -- %s', $stagedPath));
        $this->line(sprintf('Would run: git commit -m "%s"', $commitMessage));

        if ($createTag) {
            $this->line(sprintf('Would run: git tag -a %s -m "%s"', $tagName, $tagName));
        }

        if ($push) {
            $this->line(sprintf('Would run: git push %s %s', $this->remote(), $branch));
            $this->line(sprintf('Would run: git push %s %s', $this->remote(), $tagName));
        }

        return self::SUCCESS;
    }

    private function release(
        GitRepository $git,
        Version $target,
        string $branch,
        string $stagedPath,
        string $commitMessage,
        string $tagName,
        bool $createTag,
        bool $push,
    ): int {
        try {
            $git->add($stagedPath);
            $git->commit($commitMessage);
            $this->info(sprintf('Committed the version bump to %s.', $target->toString()));

            if ($createTag) {
                $git->tag($tagName, $tagName);
                $this->info(sprintf('Created tag %s.', $tagName));
            }

            if ($push) {
                $git->push($this->remote(), $branch);
                $this->info(sprintf('Pushed %s to %s.', $branch, $this->remote()));

                if ($createTag) {
                    $git->push($this->remote(), $tagName);
                    $this->info(sprintf('Pushed tag %s to %s.', $tagName, $this->remote()));
                }
            }
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function composerJsonPath(): string
    {
        $path = (string) config('versioning.composer_json', 'composer.json');

        if ($path === '') {
            $path = 'composer.json';
        }

        return $this->isAbsolutePath($path) ? $path : base_path($path);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    private function preIdentifier(): string
    {
        $preIdentifier = $this->option('preid');

        if (is_string($preIdentifier) && $preIdentifier !== '') {
            return $preIdentifier;
        }

        return (string) config('versioning.preid', 'beta');
    }

    private function commitMessage(Version $version): string
    {
        $template = $this->option('message');

        if (! is_string($template) || $template === '') {
            $template = (string) config('versioning.commit_message', 'chore(release): :version');
        }

        return str_replace(':version', $version->toString(), $template);
    }

    private function tagPrefix(): string
    {
        return (string) config('versioning.tag_prefix', 'v');
    }

    private function remote(): string
    {
        return (string) config('versioning.remote', 'origin');
    }
}
