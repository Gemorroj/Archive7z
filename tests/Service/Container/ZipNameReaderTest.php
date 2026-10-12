<?php

declare(strict_types=1);

namespace Archive7z\Tests\Service\Container;

use Archive7z\Service\Container\ZipNameReader;
use PHPUnit\Framework\TestCase;

class ZipNameReaderTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = \dirname(__DIR__, 2).'/fixtures';
    }

    public function testRawLegacyNames(): void
    {
        $names = (new ZipNameReader())->read($this->fixturesDir.'/windows/7zip-18.05/sample.zip');

        self::assertCount(5, $names);
        self::assertSame('1.jpg', $names[0]->value);
        self::assertFalse($names[0]->utf8);
        self::assertSame('test', $names[1]->value);
        self::assertSame('test/2.jpg', $names[2]->value);
        self::assertSame('test/test.txt', $names[3]->value);
        self::assertSame(\mb_convert_encoding('чавес.jpg', 'CP866', 'UTF-8'), $names[4]->value);
        self::assertFalse($names[4]->utf8);
    }

    public function testUnicodeNames(): void
    {
        $names = (new ZipNameReader())->read($this->fixturesDir.'/linux/bsdtar-3.6.2/sample.zip');

        $last = $names[\count($names) - 1];
        self::assertTrue($last->utf8);
        self::assertSame('чавес.jpg', $last->value);
    }
}
