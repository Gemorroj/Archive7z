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
        \file_put_contents($this->directory.'/старое.txt', 'content');

        (new NameRepairer(ArchiveOptions::OVERWRITE_MODE_A))
            ->rename($this->directory, [['from' => 'старое.txt', 'to' => 'новое.txt']]);

        self::assertFileExists($this->directory.'/новое.txt');
        self::assertFileDoesNotExist($this->directory.'/старое.txt');
    }

    public function testRenameSkipExisting(): void
    {
        \file_put_contents($this->directory.'/старое.txt', 'extracted');
        \file_put_contents($this->directory.'/новое.txt', 'existing');

        (new NameRepairer(ArchiveOptions::OVERWRITE_MODE_S))
            ->rename($this->directory, [['from' => 'старое.txt', 'to' => 'новое.txt']]);

        self::assertFileExists($this->directory.'/старое.txt');
        self::assertSame('existing', \file_get_contents($this->directory.'/новое.txt'));
    }

    public function testRenameNonUtf8Name(): void
    {
        $from = $this->rawName('чавес.jpg');
        \file_put_contents($this->directory.'/'.$from, 'content');

        (new NameRepairer(ArchiveOptions::OVERWRITE_MODE_A))
            ->rename($this->directory, [['from' => $from, 'to' => 'чавес.jpg']]);

        self::assertFileExists($this->directory.'/чавес.jpg');
        self::assertFileDoesNotExist($this->directory.'/'.$from);
    }

    public function testRenameRefusesEscapingPath(): void
    {
        \file_put_contents($this->directory.'/from.txt', 'content');

        $relative = '../escaped_'.\basename($this->directory).'.txt';
        $escaped = $this->directory.'/'.$relative;
        @\unlink($escaped);

        (new NameRepairer(ArchiveOptions::OVERWRITE_MODE_A))
            ->rename($this->directory, [['from' => 'from.txt', 'to' => $relative]]);

        self::assertFileExists($this->directory.'/from.txt');
        self::assertFileDoesNotExist($escaped);
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

    /**
     * Returns a legacy-encoded (non-UTF-8) name, skipping the test when the
     * filesystem cannot store it as a name distinct from its UTF-8 form
     * (Windows colates raw bytes into UTF-16, macOS rejects invalid UTF-8).
     */
    private function rawName(string $utf8): string
    {
        $raw = \mb_convert_encoding($utf8, 'Windows-1251', 'UTF-8');

        if (false === @\file_put_contents($this->directory.'/'.$raw, '')) {
            self::markTestSkipped('The filesystem cannot store a raw non-UTF-8 name.');
        }

        $distinct = !\file_exists($this->directory.'/'.$utf8);
        \unlink($this->directory.'/'.$raw);

        if (!$distinct) {
            self::markTestSkipped('The filesystem does not distinguish raw non-UTF-8 names from their UTF-8 form.');
        }

        return $raw;
    }
}
