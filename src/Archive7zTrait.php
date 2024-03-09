<?php

declare(strict_types=1);

namespace Archive7z;

trait Archive7zTrait
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
    protected static array $binary7zNix = ['/usr/bin/7z', '/usr/bin/7za', '/usr/bin/7zz', '/usr/local/bin/7z', '/usr/local/bin/7za', '/usr/local/bin/7zz'];
    /**
     * @var string[]
     */
    protected static array $binary7zWindows = ['C:\Program Files\7-Zip\7z.exe']; // %ProgramFiles%\7-Zip\7z.exe

    protected static function isOsWin(): bool
    {
        return 'Windows' === \PHP_OS_FAMILY;
    }

    protected static function getAutoBinary7z(): ?string
    {
        $binary7zPath = null;

        if (static::isOsWin()) {
            $binary7zPaths = static::$binary7zWindows;
        } else {
            $binary7zPaths = static::$binary7zNix;
        }

        foreach ($binary7zPaths as $binary7zPath) {
            if (\file_exists($binary7zPath)) {
                break;
            }
        }

        return $binary7zPath;
    }

    /**
     * @throws Exception
     */
    protected static function makeBinary7z(?string $binary7z = null): string
    {
        if (null === $binary7z) {
            $binary7z = static::getAutoBinary7z();
            if (null === $binary7z) {
                throw new Exception('Can\'t auto detect binary of 7-zip');
            }
        }
        $binary7z = \realpath($binary7z);
        if (false === $binary7z) {
            throw new Exception('Binary of 7-zip is not available');
        }
        if (!\is_executable($binary7z)) {
            throw new Exception('Binary of 7-zip is not executable');
        }

        return $binary7z;
    }
}
