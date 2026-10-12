<?php

declare(strict_types=1);

namespace Archive7z\Tests\Service\Namer;

use Archive7z\Config\ArchiveOptions;
use Archive7z\Service\Namer\NameRepairer;
use PHPUnit\Framework\TestCase;

class NameRepairerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = \sys_get_temp_dir().'/'.\uniqid('Archive7z-name-', false);
        \mkdir($this->directory);
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

    public function testRenameOverwrite(): void
    {
        $this->requireRawNames();

        $from = \mb_convert_encoding('чавес.jpg', 'Windows-1251', 'UTF-8');
        \file_put_contents($this->directory.'/'.$from, 'content');

        (new NameRepairer(ArchiveOptions::OVERWRITE_MODE_A))
            ->rename($this->directory, [['from' => $from, 'to' => 'чавес.jpg']]);

        self::assertFileExists($this->directory.'/чавес.jpg');
        self::assertFileDoesNotExist($this->directory.'/'.$from);
    }

    public function testRenameSkipExisting(): void
    {
        $this->requireRawNames();

        $from = \mb_convert_encoding('чавес.jpg', 'Windows-1251', 'UTF-8');
        \file_put_contents($this->directory.'/'.$from, 'raw');
        \file_put_contents($this->directory.'/чавес.jpg', 'utf8');

        (new NameRepairer(ArchiveOptions::OVERWRITE_MODE_S))
            ->rename($this->directory, [['from' => $from, 'to' => 'чавес.jpg']]);

        self::assertFileExists($this->directory.'/'.$from);
        self::assertSame('utf8', \file_get_contents($this->directory.'/чавес.jpg'));
    }

    public function testRenameAutoRenameExtracted(): void
    {
        \file_put_contents($this->directory.'/a.txt', 'extracted');
        \file_put_contents($this->directory.'/b.txt', 'existing');

        (new NameRepairer(ArchiveOptions::OVERWRITE_MODE_U))
            ->rename($this->directory, [['from' => 'a.txt', 'to' => 'b.txt']]);

        self::assertSame('existing', \file_get_contents($this->directory.'/b.txt'));
        self::assertSame('extracted', \file_get_contents($this->directory.'/b_1.txt'));
    }

    public function testRenameAutoRenameExisting(): void
    {
        \file_put_contents($this->directory.'/a.txt', 'extracted');
        \file_put_contents($this->directory.'/b.txt', 'existing');

        (new NameRepairer(ArchiveOptions::OVERWRITE_MODE_T))
            ->rename($this->directory, [['from' => 'a.txt', 'to' => 'b.txt']]);

        self::assertSame('extracted', \file_get_contents($this->directory.'/b.txt'));
        self::assertSame('existing', \file_get_contents($this->directory.'/b_1.txt'));
    }

    public function testNothingToRename(): void
    {
        \file_put_contents($this->directory.'/file.txt', 'content');

        (new NameRepairer(ArchiveOptions::OVERWRITE_MODE_A))->rename($this->directory, []);

        self::assertFileExists($this->directory.'/file.txt');
    }

    private function requireRawNames(): void
    {
        if ('Windows' === \PHP_OS_FAMILY) {
            self::markTestSkipped('Test environment limitation: Windows file names are UTF-16, so a raw non-UTF-8 name cannot be created on disk.');
        }
    }
}
