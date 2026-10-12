<?php

declare(strict_types=1);

namespace Archive7z\Service\Namer;

use Archive7z\Config\ArchiveOptions;

/**
 * Renames extracted entries to their target names.
 *
 * Deeper paths are renamed first (so parent directories are renamed after
 * their contents), and name collisions honor the overwrite mode.
 */
final class NameRepairer
{
    public function __construct(
        private readonly string $overwriteMode,
    ) {
    }

    /**
     * @param array<int, array{from: string, to: string}> $renames relative paths
     */
    public function rename(string $directory, array $renames): void
    {
        $depths = [];
        foreach ($renames as $index => $rename) {
            $depths[$index] = \substr_count(\str_replace('\\', '/', $rename['from']), '/');
        }
        \array_multisort($depths, \SORT_DESC, \SORT_NUMERIC, $renames);

        foreach ($renames as $rename) {
            if ($rename['from'] === $rename['to']) {
                continue;
            }

            $this->renamePath(
                $this->buildPath($directory, $rename['from']),
                $this->buildPath($directory, $rename['to']),
            );
        }
    }

    private function buildPath(string $directory, string $relativePath): string
    {
        $relativePath = \str_replace(['/', '\\'], \DIRECTORY_SEPARATOR, $relativePath);

        return \rtrim($directory, '/\\').\DIRECTORY_SEPARATOR.\ltrim($relativePath, '/\\');
    }

    private function renamePath(string $from, string $to): void
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
