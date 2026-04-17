<?php

namespace App\Services;

class FilesystemCleanupService
{
    public function pruneEmptyDirectories(array $directories): array
    {
        $removed = [];

        foreach ($directories as $directory) {
            $directory = $this->normalizePath($directory);
            if ($directory === null || ! is_dir($directory)) {
                continue;
            }

            if (! $this->isDirectoryEmpty($directory)) {
                continue;
            }

            if (@rmdir($directory)) {
                $removed[] = $directory;
            }
        }

        return $removed;
    }

    private function isDirectoryEmpty(string $directory): bool
    {
        $items = @scandir($directory);
        if (! is_array($items)) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            return false;
        }

        return true;
    }

    private function normalizePath(mixed $path): ?string
    {
        if (! is_string($path)) {
            return null;
        }

        $trimmed = rtrim(trim($path), DIRECTORY_SEPARATOR);

        return $trimmed !== '' ? $trimmed : null;
    }
}
