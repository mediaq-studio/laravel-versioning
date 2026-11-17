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

    /**
     * @param  bool  $interactive  Allow git to prompt for credentials. Off by default:
     *                             a prompt cannot be answered in CI or queue workers, so
     *                             the command would hang until the timeout kills it.
     */
    public function __construct(
        private readonly string $workingDirectory,
        private readonly bool $interactive = false,
    ) {}

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
        // The message is passed as an argument list (never through a shell), so no
        // quoting is needed. Blank-line separated paragraphs become extra -m flags,
        // which keeps the first line as the subject and the rest as the body.
        $arguments = ['git', 'commit'];

        foreach (preg_split("/\R{2,}/", trim($message)) ?: [$message] as $paragraph) {
            $arguments[] = '-m';
            $arguments[] = trim($paragraph);
        }

        $this->run($arguments, self::RELEASE_TIMEOUT);
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
            throw new RuntimeException(sprintf(
                'Command failed: git %s%s',
                $this->describeCommand($command),
                $this->describeFailure($process),
            ));
        }

        return $process->getOutput();
    }

    /**
     * Render the command arguments for an error message.
     *
     * This is purely cosmetic: the command itself is handed to the operating
     * system as an argument list, so nothing has to be quoted at runtime. The
     * arguments are quoted here only so the message is unambiguous to read.
     *
     * @param  array<int, string>  $command
     */
    private function describeCommand(array $command): string
    {
        return implode(' ', array_map(
            static fn(string $argument): string => preg_match('/^[\w@%+=:,.\/-]+$/', $argument) === 1
                ? $argument
                : escapeshellarg($argument),
            array_slice($command, 1),
        ));
    }

    /**
     * Collect the output of a failed command. git reports several failures (most
     * pre-commit hooks, "nothing to commit") on stdout instead of stderr.
     */
    private function describeFailure(Process $process): string
    {
        $error = trim($process->getErrorOutput());
        $details = $error !== '' ? $error : trim($process->getOutput());

        return $details === '' ? '' : PHP_EOL . $details;
    }

    /**
     * @param  array<int, string>  $command
     */
    private function process(array $command, float $timeout): Process
    {
        $process = new Process(
            $command,
            $this->workingDirectory,
            $this->interactive ? null : self::NON_INTERACTIVE_ENV,
        );

        $process->setTimeout($timeout);

        return $process;
    }
}
