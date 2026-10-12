<?php

declare(strict_types=1);

namespace Archive7z\Service\Encoder;

use Archive7z\Config\ArchiveOptions;

/**
 * Renames extracted entries whose names were not valid UTF-8 on disk.
 *
 * Only the passed entries are touched (never other files that may already
 * exist in the output directory).
 */
final class NameRepairer
{
    public function __construct(
        private readonly string $overwriteMode,
    ) {
    }

    /**
     * @param string[] $relativePaths extracted archive entry paths
     */
    public function repair(string $directory, array $relativePaths, NameEncoder $nameEncoder): void
    {
        $paths = [];
        foreach ($relativePaths as $path) {
            if ('' !== $path) {
                $paths[$path] = true;
            }
        }

        $paths = \array_keys($paths);
        $depths = [];
        foreach ($paths as $path) {
            $depths[] = \substr_count(\str_replace('\\', '/', $path), '/');
        }
        \array_multisort($depths, \SORT_DESC, \SORT_NUMERIC, $paths);

        foreach ($paths as $path) {
            $corrected = $nameEncoder->decode($path);
            if ($corrected === $path) {
                continue;
            }

            $this->rename(
                $this->buildPath($directory, $path),
                $this->buildPath($directory, $corrected),
            );
        }
    }

    private function buildPath(string $directory, string $relativePath): string
    {
        $relativePath = \str_replace(['/', '\\'], \DIRECTORY_SEPARATOR, $relativePath);

        return \rtrim($directory, '/\\').\DIRECTORY_SEPARATOR.\ltrim($relativePath, '/\\');
    }

    private function rename(string $from, string $to): void
    {
        if ($from === $to || (!\file_exists($from) && !\is_link($from))) {
            return;
        }

        if (\file_exists($to) || \is_link($to)) {
            if (ArchiveOptions::OVERWRITE_MODE_S === $this->overwriteMode) {
                return;
            }

            if (ArchiveOptions::OVERWRITE_MODE_U === $this->overwriteMode) {
                $to = $this->makeUniquePath($to);
            } elseif (ArchiveOptions::OVERWRITE_MODE_T === $this->overwriteMode) {
                if (!@\rename($to, $this->makeUniquePath($to))) {
                    return;
                }
            } elseif (\is_dir($to) && !\is_link($to)) {
                return;
            } else {
                @\unlink($to);
            }
        }

        @\rename($from, $to);
    }

    private function makeUniquePath(string $path): string
    {
        $directory = \dirname($path);
        $extension = \pathinfo($path, \PATHINFO_EXTENSION);
        $suffix = '' !== $extension ? '.'.$extension : '';
        $filename = \pathinfo($path, \PATHINFO_FILENAME);

        $i = 1;
        do {
            $candidate = $directory.\DIRECTORY_SEPARATOR.$filename.'_'.$i.$suffix;
            ++$i;
        } while (\file_exists($candidate) || \is_link($candidate));

        return $candidate;
    }
}
