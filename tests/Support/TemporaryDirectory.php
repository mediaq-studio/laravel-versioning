<?php

namespace MediaQ\Versioning\Tests\Support;

use RuntimeException;

/**
 * Creates and removes throw-away project directories for the package tests.
 */
trait TemporaryDirectory
{
    private ?string $temporaryDirectory = null;

    protected function temporaryDirectory(): string
    {
        if ($this->temporaryDirectory === null) {
            $directory = rtrim(sys_get_temp_dir(), '/') . '/laravel-versioning-' . bin2hex(random_bytes(8));

            if (! mkdir($directory, 0777, true) && ! is_dir($directory)) {
                throw new RuntimeException(sprintf('Could not create %s.', $directory));
            }

            $this->temporaryDirectory = $directory;
        }

        return $this->temporaryDirectory;
    }

    protected function removeTemporaryDirectory(): void
    {
        if ($this->temporaryDirectory === null) {
            return;
        }

        $this->deleteDirectory($this->temporaryDirectory);
        $this->temporaryDirectory = null;
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);

        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;

            if (is_dir($path) && ! is_link($path)) {
                $this->deleteDirectory($path);

                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
