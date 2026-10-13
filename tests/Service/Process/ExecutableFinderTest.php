<?php

declare(strict_types=1);

namespace Archive7z\Tests\Service\Process;

use Archive7z\Exception\RuntimeException;
use Archive7z\Service\Process\ExecutableFinder;
use PHPUnit\Framework\TestCase;

class ExecutableFinderTest extends TestCase
{
    public function testFindAutoDetect(): void
    {
        self::assertFileExists(ExecutableFinder::find());
    }

    public function testFindExplicit(): void
    {
        self::assertFileExists(ExecutableFinder::find(ExecutableFinder::find()));
    }

    public function testFindNotAvailable(): void
    {
        $this->expectException(RuntimeException::class);
        ExecutableFinder::find('/nonexistent/7z');
    }

    public function testFindNotExecutable(): void
    {
        $file = (string) \tempnam(\sys_get_temp_dir(), 'archive7z_bin_');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Binary of 7-zip is not executable');

            ExecutableFinder::find($file);
        } finally {
            \unlink($file);
        }
    }

    public function testFindFromPath(): void
    {
        $this->withBinDirInPath(static function (string $dir): void {
            // Either name may win depending on the preference order.
            self::assertSame(\realpath($dir), \dirname(ExecutableFinder::find()));
        });
    }

    public function testFindBareNameFromPath(): void
    {
        $this->withBinDirInPath(static function (string $dir): void {
            self::assertSame(\realpath($dir), \dirname(ExecutableFinder::find('7z')));
            self::assertSame(\realpath($dir), \dirname(ExecutableFinder::find('7zz')));
        });
    }

    /**
     * Creates a temp dir with fake 7-Zip binaries, prepends it to PATH for the
     * duration of the callback, then restores everything.
     *
     * @param callable(string): void $test
     */
    private function withBinDirInPath(callable $test): void
    {
        $dir = \sys_get_temp_dir().'/'.\uniqid('archive7z_bin_', false);
        \mkdir($dir);
        $suffix = 'Windows' === \PHP_OS_FAMILY ? '.exe' : '';
        $binaries = [$dir.'/7z'.$suffix, $dir.'/7zz'.$suffix];
        foreach ($binaries as $binary) {
            // Non-empty: empty files are not "executable" on Windows.
            \file_put_contents($binary, 'MZ');
            @\chmod($binary, 0o755);
        }

        $path = $dir.\PATH_SEPARATOR.(\getenv('PATH') ?: '');
        $original = \getenv('PATH');
        \putenv('PATH='.$path);
        \putenv('Path='.$path); // Windows stores it as "Path"

        try {
            $test($dir);
        } finally {
            \putenv('PATH='.($original ?: ''));
            \putenv('Path='.($original ?: ''));
            foreach ($binaries as $binary) {
                \unlink($binary);
            }
            \rmdir($dir);
        }
    }
}
