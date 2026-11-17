<?php

namespace MediaQ\Versioning;

use RuntimeException;
use InvalidArgumentException;

/**
 * Reads and writes the "version" field of a composer.json file.
 *
 * The field is edited in place so the formatting of the rest of the file is
 * left untouched, and the result is validated as JSON before it is written.
 */
final class VersionFile
{
    public function __construct(private readonly string $path) {}

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    /**
     * Read the version. Returns null when the file, the field or the format is unusable.
     */
    public function read(): ?Version
    {
        $contents = $this->contents();

        if ($contents === null) {
            return null;
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded) || ! isset($decoded['version']) || ! is_string($decoded['version'])) {
            return null;
        }

        try {
            return Version::parse($decoded['version']);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Write the version to the file.
     *
     * @throws RuntimeException
     */
    public function write(Version $version): void
    {
        $contents = $this->contents();

        if ($contents === null) {
            throw new RuntimeException(sprintf('Composer file not found: %s', $this->path));
        }

        $updated = $this->replaceVersion($contents, $version) ?? $this->insertVersion($contents, $version);

        if ($updated === null) {
            throw new RuntimeException(sprintf('Could not find a place to write the version in %s.', $this->path));
        }

        json_decode($updated, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(sprintf(
                'Refusing to write %s: the result would not be valid JSON (%s).',
                $this->path,
                json_last_error_msg(),
            ));
        }

        if (file_put_contents($this->path, $updated) === false) {
            throw new RuntimeException(sprintf('Could not write %s.', $this->path));
        }
    }

    private function contents(): ?string
    {
        if (! $this->exists()) {
            return null;
        }

        $contents = file_get_contents($this->path);

        return $contents === false ? null : $contents;
    }

    private function replaceVersion(string $contents, Version $version): ?string
    {
        $replaced = preg_replace_callback(
            '/^([ \t]*)"version"[ \t]*:[ \t]*"[^"]*"/m',
            static fn (array $matches): string => $matches[1] . '"version": "' . $version->toString() . '"',
            $contents,
            1,
            $count,
        );

        return $count > 0 ? $replaced : null;
    }

    private function insertVersion(string $contents, Version $version): ?string
    {
        foreach (['name', 'description', 'type'] as $anchor) {
            $inserted = preg_replace_callback(
                '/^([ \t]*)"' . $anchor . '"[ \t]*:[ \t]*"[^"]*"/m',
                static fn (array $matches): string => $matches[0] . ',' . "\n"
                    . $matches[1] . '"version": "' . $version->toString() . '"',
                $contents,
                1,
                $count,
            );

            if ($count > 0) {
                return $inserted;
            }
        }

        $inserted = preg_replace_callback(
            '/\{\R/',
            static fn (array $matches): string => $matches[0] . '    "version": "' . $version->toString() . "\",\n",
            $contents,
            1,
            $count,
        );

        return $count > 0 ? $inserted : null;
    }
}
