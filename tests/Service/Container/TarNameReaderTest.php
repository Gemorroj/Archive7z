<?php

declare(strict_types=1);

namespace Archive7z\Tests\Service\Container;

use Archive7z\Service\Container\TarNameReader;
use PHPUnit\Framework\TestCase;

class TarNameReaderTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = \dirname(__DIR__, 2).'/fixtures';
    }

    public function testUtf8Names(): void
    {
        $names = (new TarNameReader())->read($this->fixturesDir.'/tar/windows/7zip-18.05/sample.tar');

        self::assertCount(5, $names);
        self::assertSame('1.jpg', $names[0]->value);
        self::assertSame('test', $names[1]->value);
        self::assertSame('test/2.jpg', $names[2]->value);
        self::assertSame('test/test.txt', $names[3]->value);
        self::assertSame('чавес.jpg', $names[4]->value);
        self::assertFalse($names[4]->utf8);
    }

    public function testNonUtf8Names(): void
    {
        $names = (new TarNameReader())->read($this->fixturesDir.'/tar/windows/totalcommander-9.21a/sample.tar');

        self::assertSame(\mb_convert_encoding('чавес.jpg', 'Windows-1251', 'UTF-8'), $names[4]->value);
        self::assertFalse($names[4]->utf8);
    }

    public function testGnuTar(): void
    {
        $names = (new TarNameReader())->read($this->fixturesDir.'/tar/linux/gnutar-1.34/sample.tar');

        self::assertCount(5, $names);
        self::assertSame('test/test.txt', $names[3]->value);
    }

    public function testPaxLongPath(): void
    {
        $names = (new TarNameReader())->read($this->fixturesDir.'/tar/linux/bsdtar-3.6.2/features.tar');

        self::assertCount(10, $names);

        $long = $names[2];
        self::assertTrue($long->utf8);
        self::assertGreaterThan(100, \strlen($long->value));
        self::assertStringStartsWith('long-', $long->value);
        self::assertStringEndsWith('.txt', $long->value);

        // The PAX path must not leak into the following entries.
        self::assertSame('name with spaces.txt', $names[3]->value);
        self::assertSame('nested', $names[4]->value);
    }
}
