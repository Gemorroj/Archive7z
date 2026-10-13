<?php

declare(strict_types=1);

namespace Archive7z\Tests\Service\Process;

use Archive7z\Exception\RuntimeException;
use Archive7z\Service\Process\ExecutableFinder;
use PHPUnit\Framework\TestCase;

class ExecutableFinderTest extends TestCase
{
    public function testIsWindows(): void
    {
        self::assertSame('Windows' === \PHP_OS_FAMILY, ExecutableFinder::isWindows());
    }

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
}
