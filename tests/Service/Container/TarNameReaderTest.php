<?php

declare(strict_types=1);

namespace Archive7z\Tests\Service\Container;

use Archive7z\Archive7z;
use Archive7z\Config\FallbackEncoding;
use Archive7z\Config\Options;
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

    public function testGnuBase256Size(): void
    {
        $tar = $this->tarHeader('base256.txt', $this->base256(5)).'hello'.\str_repeat("\0", 507);
        $tar .= $this->tarHeader('plain.txt', $this->octal(5, 12)).'world'.\str_repeat("\0", 507);
        $tar .= \str_repeat("\0", 1024);

        $path = \tempnam(\sys_get_temp_dir(), 'archive7z_tar_');
        \file_put_contents($path, $tar);

        try {
            $names = (new TarNameReader())->read($path);
        } finally {
            \unlink($path);
        }

        self::assertCount(2, $names);
        self::assertSame('base256.txt', $names[0]->value);
        self::assertSame('plain.txt', $names[1]->value);
    }

    /**
     * A PAX long path must survive a configured fallback encoding.
     */
    public function testPaxLongPathWithFallbackEncoding(): void
    {
        $archive = new Archive7z(
            $this->fixturesDir.'/tar/linux/bsdtar-3.6.2/features.tar',
            new Options(fallbackEncoding: FallbackEncoding::codepage('UTF-8')),
        );

        $longNames = [];
        foreach ($archive->getEntries() as $entry) {
            $path = (string) $entry->getUnixPath();
            if (\str_starts_with($path, 'long-')) {
                $longNames[] = $path;
            }
        }

        self::assertCount(1, $longNames);
        self::assertGreaterThan(100, \strlen($longNames[0]));
    }

    public function testMacosUnicodeNames(): void
    {
        $archive = new Archive7z($this->fixturesDir.'/tar/macos/bsdtar/unicode.tar');

        $paths = [];
        foreach ($archive->getEntries() as $entry) {
            $paths[] = $entry->getUnixPath();
        }

        self::assertContains('café.txt', $paths);
        self::assertContains('й.txt', $paths);
        self::assertContains('чавес.jpg', $paths);
        self::assertTrue($this->hasMacosSidecar($paths));
    }

    /**
     * @param list<string|null> $paths
     */
    private function hasMacosSidecar(array $paths): bool
    {
        foreach ($paths as $path) {
            if (\str_starts_with((string) $path, '__MACOSX/') || \str_starts_with((string) $path, '._')) {
                return true;
            }
        }

        return false;
    }

    private function tarHeader(string $name, string $sizeField): string
    {
        $header = \str_pad($name, 100, "\0");
        $header .= $this->octal(0o644, 8);  // mode
        $header .= $this->octal(0, 8);      // uid
        $header .= $this->octal(0, 8);      // gid
        $header .= $sizeField;              // size
        $header .= $this->octal(0, 12);     // mtime
        $header .= \str_repeat(' ', 8);     // checksum (spaces while summing)
        $header .= '0';                     // typeflag: regular file
        $header .= \str_repeat("\0", 100);  // linkname
        $header .= "ustar\0";               // magic
        $header .= '00';                    // version
        $header = \str_pad($header, 512, "\0");

        $sum = 0;
        for ($i = 0; $i < 512; ++$i) {
            $sum += \ord($header[$i]);
        }

        return \substr_replace($header, \str_pad(\decoct($sum), 6, '0', \STR_PAD_LEFT)."\0 ", 148, 8);
    }

    private function octal(int $value, int $length): string
    {
        return \str_pad(\decoct($value), $length - 1, '0', \STR_PAD_LEFT)."\0";
    }

    private function base256(int $value): string
    {
        $field = "\x80";
        for ($i = 10; $i >= 0; --$i) {
            $field .= \chr(($value >> ($i * 8)) & 0xFF);
        }

        return $field;
    }
}
