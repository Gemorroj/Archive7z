<?php

declare(strict_types=1);

namespace Archive7z\Service\Process;

use Archive7z\Exception\RuntimeException;

final class ExecutableFinder
{
    /**
     * 7zz - official standalone 7-Zip for Linux/macOS (7-zip.org, Homebrew).
     * 7z  - distribution package (e.g. the Debian/Ubuntu "7zip" package).
     *
     * @var string[]
     */
    private const NIX_PATHS = [
        '/usr/bin/7z',
        '/usr/bin/7zz',
        '/usr/local/bin/7z',
        '/usr/local/bin/7zz',
        '/opt/homebrew/bin/7z',
        '/opt/homebrew/bin/7zz',
    ];

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
