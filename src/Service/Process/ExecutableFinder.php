<?php

declare(strict_types=1);

namespace Archive7z\Service\Process;

use Archive7z\Exception\RuntimeException;

final class ExecutableFinder
{
    /**
     * 7zz  (7-Zip) - standalone full version of 7-Zip that supports all formats.
     * 7zzs (7-Zip) - standalone full version of 7-Zip that supports all formats (static library linking).
     * 7z  (p7zip) - 7-Zip that requires 7z.so shared library, and it supports all formats via 7z.so.
     * 7zr (p7zip) - standalone reduced version of 7-Zip that supports some 7-Zip's formats: 7z, xz, lzma and split.
     * 7za (p7zip) - standalone version of 7-Zip that supports some main formats: 7z, xz, lzma, zip, bzip2, gzip, tar, cab, ppmd and split.
     *
     * @var string[]
     */
    private const NIX_PATHS = ['/usr/bin/7z', '/usr/bin/7za', '/usr/bin/7zz', '/usr/local/bin/7z', '/usr/local/bin/7za', '/usr/local/bin/7zz'];

    /**
     * @var string[]
     */
    private const WINDOWS_PATHS = ['C:\Program Files\7-Zip\7z.exe']; // %ProgramFiles%\7-Zip\7z.exe

    public static function isWindows(): bool
    {
        return 'Windows' === \PHP_OS_FAMILY;
    }

    /**
     * @throws RuntimeException
     */
    public static function find(?string $binary7z = null): string
    {
        if (null === $binary7z) {
            $binary7z = self::autoDetect();
            if (null === $binary7z) {
                throw new RuntimeException('Can\'t auto detect binary of 7-zip');
            }
        }

        $binary7z = \realpath($binary7z);
        if (false === $binary7z) {
            throw new RuntimeException('Binary of 7-zip is not available');
        }

        if (!\is_executable($binary7z)) {
            throw new RuntimeException('Binary of 7-zip is not executable');
        }

        return $binary7z;
    }

    private static function autoDetect(): ?string
    {
        $paths = self::isWindows() ? self::WINDOWS_PATHS : self::NIX_PATHS;

        foreach ($paths as $path) {
            if (\file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
