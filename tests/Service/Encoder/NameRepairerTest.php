<?php

declare(strict_types=1);

namespace Archive7z\Tests\Service\Encoder;

use Archive7z\Config\ArchiveOptions;
use Archive7z\Config\FallbackEncoding;
use Archive7z\Service\Encoder\NameEncoder;
use Archive7z\Service\Encoder\NameRepairer;
use PHPUnit\Framework\TestCase;

class NameRepairerTest extends TestCase
{
    private string $directory;
    private NameEncoder $encoder;

    protected function setUp(): void
    {
        if ('Windows' === \PHP_OS_FAMILY) {
            self::markTestSkipped('Test environment limitation: Windows file names are UTF-16, so a raw non-UTF-8 name cannot be created on disk. The library handles Windows via -mcp, covered by Archive7zTest::testExtractRepairsLegacyNames.');
        }

        $this->directory = \sys_get_temp_dir().'/'.\uniqid('Archive7z-name-', false);
        \mkdir($this->directory);

        $this->encoder = NameEncoder::resolve(FallbackEncoding::codepage('Windows-1251'));
    }

    protected function tearDown(): void
    {
        if (!isset($this->directory)) {
            return;
        }

        foreach (\glob($this->directory.'/*') ?: [] as $file) {
            \unlink($file);
        }
        \rmdir($this->directory);
    }

    public function testRepairOverwrite(): void
    {
        $raw = \mb_convert_encoding('чавес.jpg', 'Windows-1251', 'UTF-8');
        \file_put_contents($this->directory.'/'.$raw, 'content');

        (new NameRepairer(ArchiveOptions::OVERWRITE_MODE_A))
            ->repair($this->directory, [$raw], $this->encoder);

        self::assertFileExists($this->directory.'/чавес.jpg');
        self::assertFileDoesNotExist($this->directory.'/'.$raw);
    }

    public function testRepairSkipExisting(): void
    {
        $raw = \mb_convert_encoding('чавес.jpg', 'Windows-1251', 'UTF-8');
        \file_put_contents($this->directory.'/'.$raw, 'raw');
        \file_put_contents($this->directory.'/чавес.jpg', 'utf8');

        (new NameRepairer(ArchiveOptions::OVERWRITE_MODE_S))
            ->repair($this->directory, [$raw], $this->encoder);

        self::assertFileExists($this->directory.'/'.$raw);
        self::assertSame('utf8', \file_get_contents($this->directory.'/чавес.jpg'));
    }

    public function testAsIsDoesNothing(): void
    {
        $raw = \mb_convert_encoding('чавес.jpg', 'Windows-1251', 'UTF-8');
        \file_put_contents($this->directory.'/'.$raw, 'content');

        (new NameRepairer(ArchiveOptions::OVERWRITE_MODE_A))
            ->repair($this->directory, [$raw], NameEncoder::resolve(FallbackEncoding::asIs()));

        self::assertFileExists($this->directory.'/'.$raw);
    }
}
