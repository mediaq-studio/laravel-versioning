<?php

namespace MediaQ\Versioning;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Thin wrapper around the git commands needed to release a version.
 */
final class GitRepository
{
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
        $this->run(['git', 'commit', '-m', $message]);
    }

    public function tag(string $tag, string $message): void
    {
        $this->run(['git', 'tag', '-a', $tag, '-m', $message]);
    }

    public function push(string $remote, string $ref): void
    {
        $this->run(['git', 'push', $remote, $ref]);
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
     * @param  array<int, string>  $command
     */
    private function succeeds(array $command): bool
    {
        $process = new Process($command, $this->workingDirectory);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * @param  array<int, string>  $command
     *
     * @throws RuntimeException
     */
    private function run(array $command): string
    {
        $process = new Process($command, $this->workingDirectory);
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
}
