<?php

namespace MediaQ\Versioning\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Illuminate\Container\Container;
use MediaQ\Versioning\Console\VersionCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;
use MediaQ\Versioning\Tests\Support\ArrayConfig;
use MediaQ\Versioning\Tests\Support\FakeApplication;
use MediaQ\Versioning\Tests\Support\TemporaryDirectory;

/**
 * End-to-end coverage for the release path: bump, commit, tag and push against
 * a throw-away repository with a bare "origin" remote.
 */
class VersionCommandTest extends TestCase
{
    use TemporaryDirectory;

    protected function tearDown(): void
    {
        Container::setInstance(null);

        $this->removeTemporaryDirectory();

        parent::tearDown();
    }

    public function test_it_commits_tags_and_pushes(): void
    {
        [$app, $remote] = $this->repositoryWithRemote();

        [$exitCode, $output] = $this->runCommand($app, ['version' => 'patch', '--tag' => true, '--push' => true]);

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('Version updated to 1.0.1.', $output);
        $this->assertStringContainsString('Created tag v1.0.1.', $output);
        $this->assertStringContainsString('Pushed tag v1.0.1 to origin.', $output);

        $this->assertSame('1.0.1', $this->version($app));
        $this->assertContains('v1.0.1', $this->tagList($app->basePath()));
        $this->assertContains('v1.0.1', $this->tagList($remote));
        $this->assertSame('chore(release): 1.0.1', $this->git($app->basePath(), 'log', '-1', '--pretty=%s'));
        $this->assertSame(
            "chore(release): 1.0.1\n\nBump the version in composer.json to 1.0.1.",
            $this->git($app->basePath(), 'log', '-1', '--pretty=%B'),
        );
        $this->assertSame('chore(release): 1.0.1', $this->git($remote, 'log', '-1', '--pretty=%s'));
    }

    public function test_it_uses_the_configured_commit_message_template(): void
    {
        [$app] = $this->repositoryWithRemote();

        $app->config->set(
            'versioning.commit_message',
            "chore(release): :version\n\nRelease :previous -> :version.",
        );

        [$exitCode, $output] = $this->runCommand($app, ['version' => 'minor', '--commit' => true]);

        $this->assertSame(0, $exitCode, $output);
        $this->assertSame(
            "chore(release): 1.1.0\n\nRelease 1.0.0 -> 1.1.0.",
            $this->git($app->basePath(), 'log', '-1', '--pretty=%B'),
        );
    }

    public function test_it_only_tags_without_pushing(): void
    {
        [$app, $remote] = $this->repositoryWithRemote();

        [$exitCode, $output] = $this->runCommand($app, ['version' => 'patch', '--tag' => true]);

        $this->assertSame(0, $exitCode, $output);
        $this->assertContains('v1.0.1', $this->tagList($app->basePath()));
        $this->assertNotContains('v1.0.1', $this->tagList($remote));
    }

    public function test_it_only_bumps_without_committing(): void
    {
        [$app] = $this->repositoryWithRemote();

        [$exitCode, $output] = $this->runCommand($app, ['version' => 'patch']);

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('Nothing was committed.', $output);
        $this->assertSame('1.0.1', $this->version($app));
        $this->assertSame([], $this->tagList($app->basePath()));
    }

    public function test_it_refuses_a_dirty_working_tree(): void
    {
        [$app] = $this->repositoryWithRemote();

        file_put_contents($app->basePath() . '/README.md', "dirty\n");

        [$exitCode, $output] = $this->runCommand($app, ['version' => 'patch', '--tag' => true]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('uncommitted changes', $output);
        $this->assertSame('1.0.0', $this->version($app));
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array{0: int, 1: string}
     */
    private function runCommand(FakeApplication $app, array $parameters): array
    {
        Container::setInstance($app);

        $command = new VersionCommand();
        $command->setLaravel($app);

        $output = new BufferedOutput();
        $exitCode = $command->run(new ArrayInput($parameters), $output);

        return [$exitCode, $output->fetch()];
    }

    /**
     * @return array{0: FakeApplication, 1: string}
     */
    private function repositoryWithRemote(): array
    {
        $root = $this->temporaryDirectory();
        $work = $root . '/app';
        $remote = $root . '/remote.git';

        mkdir($work, 0777, true);

        $this->git($root, 'init', '--quiet', '--bare', '--initial-branch=main', $remote);
        $this->git($work, 'init', '--quiet', '--initial-branch=main');
        $this->git($work, 'config', 'user.email', 'versioning@example.test');
        $this->git($work, 'config', 'user.name', 'Versioning Test');
        $this->git($work, 'config', 'tag.gpgSign', 'false');
        $this->git($work, 'remote', 'add', 'origin', $remote);

        file_put_contents($work . '/composer.json', "{\n  \"name\": \"acme/app\",\n  \"version\": \"1.0.0\"\n}\n");
        $this->git($work, 'add', 'composer.json');
        $this->git($work, 'commit', '--quiet', '-m', 'chore: initial');
        $this->git($work, 'push', '--quiet', 'origin', 'main');

        return [new FakeApplication($work, new ArrayConfig()), $remote];
    }

    private function version(FakeApplication $app): string
    {
        $decoded = json_decode((string) file_get_contents($app->basePath('composer.json')), true);

        return (string) $decoded['version'];
    }

    /**
     * @return array<int, string>
     */
    private function tagList(string $directory): array
    {
        $tags = $this->git($directory, 'tag', '--list');

        return $tags === '' ? [] : explode("\n", $tags);
    }

    private function git(string $directory, string ...$arguments): string
    {
        $process = new Process(['git', ...$arguments], $directory);
        $process->mustRun();

        return trim($process->getOutput());
    }
}
