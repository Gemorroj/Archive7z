<?php

declare(strict_types=1);

namespace Archive7z\Tests\Service\Namer;

use Archive7z\Archive7z;
use Archive7z\Config\ExtractOptions;
use Archive7z\Config\FallbackEncoding;
use Archive7z\Config\Options;
use Archive7z\Service\Namer\NameRepairer;
use PHPUnit\Framework\TestCase;

class NameRepairerTest extends TestCase
{
    private string $directory;
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = \dirname(__DIR__, 2).'/fixtures';
        $this->directory = \sys_get_temp_dir().'/'.\uniqid('Archive7z-name-', false);
        \mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        if (!isset($this->directory)) {
            return;
        }

        $this->removeDir($this->directory);
    }

    public function testRenameOverwrite(): void
    {
        \file_put_contents($this->directory.'/старое.txt', 'content');

        (new NameRepairer(ExtractOptions::OVERWRITE_MODE_A))
            ->rename($this->directory, [['from' => 'старое.txt', 'to' => 'новое.txt']]);

        self::assertFileExists($this->directory.'/новое.txt');
        self::assertFileDoesNotExist($this->directory.'/старое.txt');
    }

    public function testRenameSkipExisting(): void
    {
        \file_put_contents($this->directory.'/старое.txt', 'extracted');
        \file_put_contents($this->directory.'/новое.txt', 'existing');

        (new NameRepairer(ExtractOptions::OVERWRITE_MODE_S))
            ->rename($this->directory, [['from' => 'старое.txt', 'to' => 'новое.txt']]);

        self::assertFileExists($this->directory.'/старое.txt');
        self::assertSame('existing', \file_get_contents($this->directory.'/новое.txt'));
    }

    public function testRenameNonUtf8Name(): void
    {
        $from = $this->rawName('чавес.jpg');
        \file_put_contents($this->directory.'/'.$from, 'content');

        (new NameRepairer(ExtractOptions::OVERWRITE_MODE_A))
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

        (new NameRepairer(ExtractOptions::OVERWRITE_MODE_A))
            ->rename($this->directory, [['from' => 'from.txt', 'to' => $relative]]);

        self::assertFileExists($this->directory.'/from.txt');
        self::assertFileDoesNotExist($escaped);
    }

    public function testRenameAutoRenameExtracted(): void
    {
        \file_put_contents($this->directory.'/a.txt', 'extracted');
        \file_put_contents($this->directory.'/b.txt', 'existing');

        (new NameRepairer(ExtractOptions::OVERWRITE_MODE_U))
            ->rename($this->directory, [['from' => 'a.txt', 'to' => 'b.txt']]);

        self::assertSame('existing', \file_get_contents($this->directory.'/b.txt'));
        self::assertSame('extracted', \file_get_contents($this->directory.'/b_1.txt'));
    }

    public function testRenameAutoRenameExisting(): void
    {
        \file_put_contents($this->directory.'/a.txt', 'extracted');
        \file_put_contents($this->directory.'/b.txt', 'existing');

        (new NameRepairer(ExtractOptions::OVERWRITE_MODE_T))
            ->rename($this->directory, [['from' => 'a.txt', 'to' => 'b.txt']]);

        self::assertSame('extracted', \file_get_contents($this->directory.'/b.txt'));
        self::assertSame('existing', \file_get_contents($this->directory.'/b_1.txt'));
    }

    public function testNothingToRename(): void
    {
        \file_put_contents($this->directory.'/file.txt', 'content');

        (new NameRepairer(ExtractOptions::OVERWRITE_MODE_A))->rename($this->directory, []);

        self::assertFileExists($this->directory.'/file.txt');
    }

    public function testExtractRepairsNonUtf8Names(): void
    {
        $archive = new Archive7z(
            $this->fixturesDir.'/tar/windows/totalcommander-9.21a/sample.tar',
            new Options(fallbackEncoding: FallbackEncoding::codepage('Windows-1251')),
        );
        $archive->extract($this->directory);

        self::assertFileExists($this->directory.'/чавес.jpg');
        self::assertFileExists($this->directory.'/1.jpg');
        self::assertFileExists($this->directory.'/test/test.txt');
    }

    public function testExtractDetectRepairsNonUtf8Names(): void
    {
        $archive = new Archive7z(
            $this->fixturesDir.'/tar/windows/totalcommander-9.21a/sample.tar',
            new Options(fallbackEncoding: FallbackEncoding::detect()),
        );
        $archive->extract($this->directory);

        self::assertFileExists($this->directory.'/чавес.jpg');
    }

    /**
     * The default fallback encoding detects the code page, so legacy names are
     * repaired without extra configuration.
     *
     * @see https://github.com/Gemorroj/Archive7z/issues/15
     */
    public function testExtractRepairsWithDefaultEncoding(): void
    {
        $archive = new Archive7z($this->fixturesDir.'/tar/windows/totalcommander-9.21a/sample.tar');
        $archive->extract($this->directory);

        self::assertFileExists($this->directory.'/чавес.jpg');
    }

    public function testExtractRepairsWithCallbackEncoding(): void
    {
        $archive = new Archive7z(
            $this->fixturesDir.'/tar/windows/totalcommander-9.21a/sample.tar',
            new Options(fallbackEncoding: FallbackEncoding::viaCallback(
                static function (string $raw): string {
                    return \mb_convert_encoding($raw, 'UTF-8', 'Windows-1251');
                },
                static function (string $value): string {
                    return \mb_convert_encoding($value, 'Windows-1251', 'UTF-8');
                },
            )),
        );
        $archive->extract($this->directory);

        self::assertFileExists($this->directory.'/чавес.jpg');
    }

    private function removeDir(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        foreach (\scandir($dir) ?: [] as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = $dir.'/'.$entry;
            if (\is_dir($path) && !\is_link($path)) {
                $this->removeDir($path);
            } else {
                @\unlink($path);
            }
        }

        \rmdir($dir);
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
