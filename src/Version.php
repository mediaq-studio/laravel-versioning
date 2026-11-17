<?php

namespace MediaQ\Versioning;

use Stringable;
use InvalidArgumentException;

/**
 * An immutable semantic version with npm compatible bump behaviour.
 *
 * Supported formats: "1.2.3", "v1.2.3", "1.2.3-beta.1", "1.2.3-0" and "1.2.3-beta".
 */
final class Version implements Stringable
{
    private function __construct(
        public readonly int $major,
        public readonly int $minor,
        public readonly int $patch,
        public readonly ?string $preRelease = null,
    ) {}

    /**
     * Parse a version like "1.2.3", "v1.2.3", "1.2.3-beta.1" or "1.2.3-0".
     *
     * @throws InvalidArgumentException
     */
    public static function parse(string $version): self
    {
        $pattern = '/^v?(\d+)\.(\d+)\.(\d+)(?:-([0-9A-Za-z][0-9A-Za-z.-]*))?$/';

        if (preg_match($pattern, trim($version), $matches) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Invalid semantic version "%s". Expected something like 1.2.3 or 1.2.3-beta.1.',
                $version,
            ));
        }

        $preRelease = $matches[4] ?? '';

        return new self(
            (int) $matches[1],
            (int) $matches[2],
            (int) $matches[3],
            $preRelease === '' ? null : $preRelease,
        );
    }

    public function isPreRelease(): bool
    {
        return $this->preRelease !== null;
    }

    public function equals(self $other): bool
    {
        return $this->toString() === $other->toString();
    }

    /**
     * Bump the version, mirroring the semantics of "npm version".
     *
     * @throws InvalidArgumentException
     */
    public function bump(string $type, string $preIdentifier = 'beta'): self
    {
        return match ($type) {
            'patch'      => $this->bumpPatch(),
            'minor'      => $this->bumpMinor(),
            'major'      => $this->bumpMajor(),
            'prepatch'   => $this->startPreRelease($preIdentifier, $this->patch + 1),
            'preminor'   => $this->startPreRelease($preIdentifier, 0, $this->minor + 1),
            'premajor'   => $this->startPreRelease($preIdentifier, 0, 0, $this->major + 1),
            'prerelease' => $this->bumpPreRelease($preIdentifier),
            default      => throw new InvalidArgumentException(sprintf(
                'Unknown bump type "%s". Use patch, minor, major, prepatch, preminor, premajor or prerelease.',
                $type,
            )),
        };
    }

    public function toString(): string
    {
        $version = sprintf('%d.%d.%d', $this->major, $this->minor, $this->patch);

        return $this->preRelease === null ? $version : $version . '-' . $this->preRelease;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * A pre-release of 1.2.3 becomes 1.2.3, a stable 1.2.3 becomes 1.2.4.
     */
    private function bumpPatch(): self
    {
        if ($this->isPreRelease()) {
            return new self($this->major, $this->minor, $this->patch);
        }

        return new self($this->major, $this->minor, $this->patch + 1);
    }

    /**
     * A pre-release of 1.2.0 becomes 1.2.0, anything else becomes X.(Y+1).0.
     */
    private function bumpMinor(): self
    {
        if ($this->isPreRelease() && $this->patch === 0) {
            return new self($this->major, $this->minor, 0);
        }

        return new self($this->major, $this->minor + 1, 0);
    }

    /**
     * A pre-release of 2.0.0 becomes 2.0.0, anything else becomes (X+1).0.0.
     */
    private function bumpMajor(): self
    {
        if ($this->isPreRelease() && $this->minor === 0 && $this->patch === 0) {
            return new self($this->major, 0, 0);
        }

        return new self($this->major + 1, 0, 0);
    }

    private function withPreRelease(string $preRelease): self
    {
        return new self($this->major, $this->minor, $this->patch, $preRelease);
    }

    /**
     * Start a pre-release, always incrementing the given part (like npm).
     */
    private function startPreRelease(string $preIdentifier, ?int $patch = null, ?int $minor = null, ?int $major = null): self
    {
        return (new self(
            $major ?? $this->major,
            $minor ?? $this->minor,
            $patch ?? $this->patch,
        ))->withPreRelease($preIdentifier . '.0');
    }

    private function bumpPreRelease(string $preIdentifier): self
    {
        $preRelease = $this->preRelease;

        if ($preRelease === null) {
            return $this->withPreRelease($preIdentifier . '.0');
        }

        if (preg_match('/^(\d+)$/', $preRelease, $matches) === 1) {
            return $this->withPreRelease((string) ((int) $matches[1] + 1));
        }

        if (preg_match('/^([0-9A-Za-z-]+)\.(\d+)$/', $preRelease, $matches) === 1) {
            if ($matches[1] !== $preIdentifier) {
                return $this->withPreRelease($preIdentifier . '.0');
            }

            return $this->withPreRelease(sprintf('%s.%d', $matches[1], (int) $matches[2] + 1));
        }

        return $this->withPreRelease($preIdentifier . '.0');
    }
}
