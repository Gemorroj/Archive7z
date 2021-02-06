<?php

namespace Archive7z;

trait Archive7zTrait
{
    /**
     * 7z uses plugins (7z.so and Codecs/Rar.so) to handle archives.
     * 7za is a stand-alone executable (7za handles less archive formats than 7z).
     * 7zr is a light stand-alone executable that supports only 7z/LZMA/BCJ/BCJ2.
     *
     * @var string[]
     */
    protected static $binary7zNix = ['/usr/bin/7z', '/usr/bin/7za', '/usr/local/bin/7z', '/usr/local/bin/7za'];
    /**
     * @var string[]
     */
    protected static $binary7zWindows = ['C:\Program Files\7-Zip\7z.exe']; // %ProgramFiles%\7-Zip\7z.exe

    protected static function isOsWin(): bool
    {
        return '\\' === \DIRECTORY_SEPARATOR;
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
