<?php

declare(strict_types=1);

namespace Archive7z\Service\Process;

use Archive7z\Exception\RuntimeException;
use Symfony\Component\Process\ExecutableFinder as SymfonyExecutableFinder;

final class ExecutableFinder
{
    /**
     * Preference order. 7zz (the official standalone build) is tried first so
     * that, when both are installed, an explicit official build wins over an
     * old distribution 7z (e.g. p7zip 16.02, which this library does not
     * support). 7z covers distribution packages and Windows, where 7zz does
     * not exist.
     *
     * @var list<string>
     */
    private const NAMES = ['7zz', '7z'];

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
        } elseif (!\str_contains($binary7z, '/') && !\str_contains($binary7z, '\\')) {
            // A bare name (e.g. "7z") is resolved through PATH, like the shell.
            $binary7z = self::locate($binary7z, $binary7z);
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
        foreach (self::NAMES as $name) {
            $found = self::locate($name);
            if (null !== $found) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Locates a 7-Zip executable through PATH and the well-known directories.
     */
    private static function locate(string $name, ?string $default = null): ?string
    {
        return (new SymfonyExecutableFinder())->find($name, $default, self::extraDirs());
    }

    /**
     * Well-known install directories, checked in addition to PATH: batches and
     * non-interactive processes often lack the Homebrew or Windows "Program
     * Files" directories in PATH.
     *
     * @return list<string>
     */
    private static function extraDirs(): array
    {
        if ('Windows' === \PHP_OS_FAMILY) {
            $dirs = [];
            foreach (['ProgramFiles', 'ProgramFiles(x86)', 'ProgramW6432'] as $variable) {
                $base = \getenv($variable);
                if (\is_string($base) && '' !== $base) {
                    $dirs[] = $base.'\7-Zip';
                }
            }

            return $dirs;
        }

        return ['/usr/bin', '/usr/local/bin', '/opt/homebrew/bin', '/opt/local/bin'];
    }
}
