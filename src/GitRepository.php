<?php

namespace MediaQ\Versioning;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Thin wrapper around the git commands needed to release a version.
 */
final class GitRepository
{
    /**
     * Timeout for read-only queries such as `git status` or `git tag --list`.
     */
    private const QUERY_TIMEOUT = 60.0;

    /**
     * Timeout for commands that can legitimately take longer: a push over a
     * slow connection, or a commit/tag that runs hooks or GPG signing.
     */
    private const RELEASE_TIMEOUT = 900.0;

    /**
     * Environment that keeps git from waiting for interactive input. The child
     * process has no usable stdin, so an unanswerable credential prompt would
     * otherwise block until the timeout kills the command.
     */
    private const NON_INTERACTIVE_ENV = [
        'GIT_TERMINAL_PROMPT' => '0',
        'GCM_INTERACTIVE' => 'never',
    ];

    public function __construct(private readonly string $workingDirectory) {}

    public function workingDirectory(): string
    {
        return $this->workingDirectory;
    }

    public function isAvailable(): bool
    {
        return $this->succeeds(['git', '--version']);
    }

    public function isRepository(): bool
    {
        return $this->succeeds(['git', 'rev-parse', '--is-inside-work-tree']);
    }

    public function isClean(): bool
    {
        if (! $this->isRepository()) {
            return true;
        }

        return trim($this->run(['git', 'status', '--porcelain'])) === '';
    }

    public function currentBranch(): ?string
    {
        if (! $this->isRepository()) {
            return null;
        }

        $branch = trim($this->run(['git', 'rev-parse', '--abbrev-ref', 'HEAD']));

        return $branch === '' || $branch === 'HEAD' ? null : $branch;
    }

    public function hasTag(string $tag): bool
    {
        return $this->isRepository() && trim($this->run(['git', 'tag', '--list', $tag])) !== '';
    }

    public function add(string ...$paths): void
    {
        $this->run(['git', 'add', '--', ...$paths]);
    }

    public function commit(string $message): void
    {
        $this->run(['git', 'commit', '-m', $message], self::RELEASE_TIMEOUT);
    }

    public function tag(string $tag, string $message): void
    {
        $this->run(['git', 'tag', '-a', $tag, '-m', $message], self::RELEASE_TIMEOUT);
    }

    public function push(string $remote, string $ref): void
    {
        $this->run(['git', 'push', $remote, $ref], self::RELEASE_TIMEOUT);
    }

    /**
     * Return the given path relative to the git working directory when possible.
     */
    public function relativePath(string $path): string
    {
        $realPath = realpath($path) ?: $path;
        $realWorkingDirectory = realpath($this->workingDirectory) ?: $this->workingDirectory;
        $prefix = rtrim($realWorkingDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (str_starts_with($realPath, $prefix)) {
            return substr($realPath, strlen($prefix));
        }

        return $realPath;
    }

    /**
     * Run a probe command. Process failures are reported as `false` instead of
     * exceptions so the callers (`isAvailable()`, `isRepository()`) can be used
     * as plain guards, even when git is missing or the working directory is
     * unusable.
     *
     * @param  array<int, string>  $command
     */
    private function succeeds(array $command): bool
    {
        try {
            return $this->process($command, self::QUERY_TIMEOUT)->run() === 0;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * @param  array<int, string>  $command
     *
     * @throws RuntimeException
     */
    private function run(array $command, float $timeout = self::QUERY_TIMEOUT): string
    {
        $process = $this->process($command, $timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput());

            throw new RuntimeException(sprintf(
                'Command failed: git %s%s',
                implode(' ', array_slice($command, 1)),
                $error === '' ? '' : PHP_EOL . $error,
            ));
        }

        return $process->getOutput();
    }

    /**
     * @param  array<int, string>  $command
     */
    private function process(array $command, float $timeout): Process
    {
        $process = new Process($command, $this->workingDirectory, self::NON_INTERACTIVE_ENV);
        $process->setTimeout($timeout);

        return $process;
    }
}
