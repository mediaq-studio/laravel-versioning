<?php

namespace MediaQ\Versioning\Tests\Unit;

use RuntimeException;
use PHPUnit\Framework\TestCase;
use MediaQ\Versioning\GitRepository;
use Symfony\Component\Process\Process;
use MediaQ\Versioning\Tests\Support\TemporaryDirectory;

class GitRepositoryTest extends TestCase
{
    use TemporaryDirectory;

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();

        parent::tearDown();
    }

    public function test_it_detects_a_directory_without_a_repository(): void
    {
        $git = new GitRepository($this->temporaryDirectory());

        $this->assertTrue($git->isAvailable());
        $this->assertFalse($git->isRepository());
        $this->assertTrue($git->isClean());
        $this->assertNull($git->currentBranch());
        $this->assertFalse($git->hasTag('v1.0.0'));
    }

    public function test_it_reports_a_clean_and_a_dirty_working_tree(): void
    {
        $git = $this->initialiseRepository();

        $this->assertTrue($git->isClean());
        $this->assertNotNull($git->currentBranch());

        file_put_contents($this->temporaryDirectory() . '/composer.json', '{}');

        $this->assertFalse($git->isClean());
    }

    public function test_it_commits_and_tags(): void
    {
        $git = $this->initialiseRepository();

        file_put_contents($this->temporaryDirectory() . '/composer.json', "{\"version\": \"1.0.1\"}\n");

        $git->add('composer.json');
        $git->commit('chore(release): 1.0.1');
        $git->tag('v1.0.1', 'v1.0.1');

        $this->assertTrue($git->isClean());
        $this->assertTrue($git->hasTag('v1.0.1'));
        $this->assertFalse($git->hasTag('v9.9.9'));
        $this->assertSame('chore(release): 1.0.1', $this->git('log', '-1', '--pretty=%s'));
    }

    public function test_it_throws_when_a_command_fails(): void
    {
        $git = $this->initialiseRepository();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Command failed: git commit');

        $git->commit('nothing to commit');
    }

    public function test_it_resolves_paths_relative_to_the_working_directory(): void
    {
        $directory = $this->temporaryDirectory();
        $git = new GitRepository($directory);

        $this->assertSame('composer.json', $git->relativePath($directory . '/composer.json'));
        $this->assertSame('/somewhere/else/composer.json', $git->relativePath('/somewhere/else/composer.json'));
    }

    private function initialiseRepository(): GitRepository
    {
        file_put_contents($this->temporaryDirectory() . '/composer.json', "{\"version\": \"1.0.0\"}\n");

        $this->git('init', '--quiet');
        $this->git('config', 'user.email', 'versioning@example.test');
        $this->git('config', 'user.name', 'Versioning Test');
        $this->git('add', 'composer.json');
        $this->git('commit', '--quiet', '-m', 'chore: initial composer.json');

        return new GitRepository($this->temporaryDirectory());
    }

    private function git(string ...$arguments): string
    {
        $process = new Process(['git', ...$arguments], $this->temporaryDirectory());
        $process->mustRun();

        return trim($process->getOutput());
    }
}
