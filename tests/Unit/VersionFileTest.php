<?php

namespace MediaQ\Versioning\Tests\Unit;

use RuntimeException;
use MediaQ\Versioning\Version;
use PHPUnit\Framework\TestCase;
use MediaQ\Versioning\VersionFile;
use MediaQ\Versioning\Tests\Support\TemporaryDirectory;

class VersionFileTest extends TestCase
{
    use TemporaryDirectory;

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();

        parent::tearDown();
    }

    public function test_it_reads_the_version(): void
    {
        $file = new VersionFile($this->writeComposerJson($this->composerJson('1.0.1')));

        $this->assertSame('1.0.1', $file->read()?->toString());
    }

    public function test_it_returns_null_when_the_file_is_missing(): void
    {
        $file = new VersionFile($this->temporaryDirectory() . '/composer.json');

        $this->assertNull($file->read());
        $this->assertFalse($file->exists());
    }

    public function test_it_returns_null_when_the_version_is_missing(): void
    {
        $file = new VersionFile($this->writeComposerJson($this->composerJson(null)));

        $this->assertNull($file->read());
    }

    public function test_it_returns_null_when_the_version_is_unparsable(): void
    {
        $file = new VersionFile($this->writeComposerJson($this->composerJson('dev-main')));

        $this->assertNull($file->read());
    }

    public function test_it_replaces_the_version_and_keeps_the_rest_of_the_file_intact(): void
    {
        $path = $this->writeComposerJson($this->composerJson('1.0.1'));
        $file = new VersionFile($path);

        $file->write(Version::parse('1.0.2'));

        $contents = (string) file_get_contents($path);
        $decoded = json_decode($contents, true);

        $this->assertSame('1.0.2', $decoded['version']);
        $this->assertSame('acme/example', $decoded['name']);
        $this->assertSame('project', $decoded['type']);
        $this->assertStringContainsString('    "version": "1.0.2",', $contents);
        $this->assertStringContainsString('"require": {' . "\n" . '        "php": "^8.2"', $contents);
    }

    public function test_it_inserts_the_version_when_the_field_is_missing(): void
    {
        $path = $this->writeComposerJson($this->composerJson(null));
        $file = new VersionFile($path);

        $file->write(Version::parse('3.1.4'));

        $contents = (string) file_get_contents($path);
        $decoded = json_decode($contents, true);

        $this->assertSame('3.1.4', $decoded['version']);
        $this->assertStringContainsString('"name": "acme/example",' . "\n" . '    "version": "3.1.4"', $contents);
    }

    public function test_it_writes_a_pre_release_version(): void
    {
        $path = $this->writeComposerJson($this->composerJson('1.0.1'));
        $file = new VersionFile($path);

        $file->write(Version::parse('1.1.0-beta.0'));

        $this->assertSame('1.1.0-beta.0', $file->read()?->toString());
    }

    public function test_it_refuses_to_write_a_missing_file(): void
    {
        $file = new VersionFile($this->temporaryDirectory() . '/composer.json');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Composer file not found');

        $file->write(Version::parse('1.0.0'));
    }

    private function composerJson(?string $version): string
    {
        $contents = ['name' => 'acme/example'];

        if ($version !== null) {
            $contents['version'] = $version;
        }

        $contents['type'] = 'project';
        $contents['require'] = ['php' => '^8.2'];

        return (string) json_encode($contents, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    private function writeComposerJson(string $contents): string
    {
        $path = $this->temporaryDirectory() . '/composer.json';
        file_put_contents($path, $contents);

        return $path;
    }
}
