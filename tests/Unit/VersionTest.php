<?php

namespace MediaQ\Versioning\Tests\Unit;

use InvalidArgumentException;
use MediaQ\Versioning\Version;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class VersionTest extends TestCase
{
    #[DataProvider('bumpProvider')]
    public function test_it_bumps_a_version_like_npm(string $current, string $type, string $expected): void
    {
        $this->assertSame($expected, Version::parse($current)->bump($type)->toString());
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function bumpProvider(): array
    {
        return [
            'patch'                                               => ['1.0.1', 'patch', '1.0.2'],
            'minor'                                               => ['1.0.1', 'minor', '1.1.0'],
            'major'                                               => ['1.0.1', 'major', '2.0.0'],
            'patch drops the pre-release'                         => ['1.2.3-beta.1', 'patch', '1.2.3'],
            'minor drops a x.y.0 pre-release'                     => ['1.2.0-beta.1', 'minor', '1.2.0'],
            'minor on a x.y.z pre-release bumps the minor'        => ['1.2.3-beta.1', 'minor', '1.3.0'],
            'major drops a x.0.0 pre-release'                     => ['2.0.0-beta.1', 'major', '2.0.0'],
            'prepatch'                                            => ['1.0.1', 'prepatch', '1.0.2-beta.0'],
            'prepatch on a pre-release'                           => ['1.2.3-beta.1', 'prepatch', '1.2.4-beta.0'],
            'preminor'                                            => ['1.0.1', 'preminor', '1.1.0-beta.0'],
            'premajor'                                            => ['1.0.1', 'premajor', '2.0.0-beta.0'],
            'prerelease starts a pre-release'                     => ['1.0.1', 'prerelease', '1.0.1-beta.0'],
            'prerelease increments the number'                    => ['1.0.1-beta.1', 'prerelease', '1.0.1-beta.2'],
            'prerelease keeps a numeric pre-release numeric'      => ['1.0.1-0', 'prerelease', '1.0.1-1'],
            'prerelease restarts an incomplete pre-release'       => ['1.0.1-beta', 'prerelease', '1.0.1-beta.0'],
        ];
    }

    public function test_it_uses_the_given_pre_identifier(): void
    {
        $this->assertSame('1.0.1-alpha.0', Version::parse('1.0.0')->bump('prepatch', 'alpha')->toString());
    }

    public function test_it_switches_the_pre_identifier(): void
    {
        $this->assertSame('1.0.1-beta.0', Version::parse('1.0.1-alpha.3')->bump('prerelease', 'beta')->toString());
    }

    public function test_it_keeps_the_pre_identifier_when_it_matches(): void
    {
        $this->assertSame('1.0.1-rc.4', Version::parse('1.0.1-rc.3')->bump('prerelease', 'rc')->toString());
    }

    #[DataProvider('parseProvider')]
    public function test_it_parses_supported_formats(string $given, string $expected): void
    {
        $this->assertSame($expected, Version::parse($given)->toString());
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function parseProvider(): array
    {
        return [
            'plain'         => ['1.2.3', '1.2.3'],
            'leading v'     => ['v2.3.4', '2.3.4'],
            'pre-release'   => ['1.2.3-beta.1', '1.2.3-beta.1'],
            'numeric pre'   => ['1.2.3-0', '1.2.3-0'],
            'spaces'        => ['  1.2.3  ', '1.2.3'],
        ];
    }

    #[DataProvider('invalidVersionProvider')]
    public function test_it_rejects_invalid_versions(string $invalid): void
    {
        $this->expectException(InvalidArgumentException::class);

        Version::parse($invalid);
    }

    /**
     * @return array<int, array{0: string}>
     */
    public static function invalidVersionProvider(): array
    {
        return [['1.0'], ['1.0.0.0'], ['dev-master'], ['1.0.0+build'], [''], ['v']];
    }

    public function test_it_rejects_an_unknown_bump_type(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Version::parse('1.0.0')->bump('huge');
    }

    public function test_pre_releases_can_be_detected(): void
    {
        $this->assertTrue(Version::parse('1.0.0-beta.1')->isPreRelease());
        $this->assertFalse(Version::parse('1.0.0')->isPreRelease());
    }

    public function test_versions_can_be_compared(): void
    {
        $this->assertTrue(Version::parse('1.0.0')->equals(Version::parse('v1.0.0')));
        $this->assertFalse(Version::parse('1.0.0')->equals(Version::parse('1.0.1')));
    }
}
