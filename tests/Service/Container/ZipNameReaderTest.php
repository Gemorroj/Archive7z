<?php

declare(strict_types=1);

namespace Archive7z\Tests\Service\Container;

use Archive7z\Archive7z;
use Archive7z\Service\Container\RawName;
use Archive7z\Service\Container\ZipNameReader;
use PHPUnit\Framework\TestCase;

class ZipNameReaderTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = \dirname(__DIR__, 2).'/fixtures';
    }

    public function testRawNonUtf8Names(): void
    {
        $names = (new ZipNameReader())->read($this->fixturesDir.'/zip/windows/7zip-18.05/sample.zip');

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
        $names = (new ZipNameReader())->read($this->fixturesDir.'/zip/linux/bsdtar-3.6.2/sample.zip');

        $last = $names[\count($names) - 1];
        self::assertTrue($last->utf8);
        self::assertSame('чавес.jpg', $last->value);
    }

    public function testSelfExtractingZip(): void
    {
        $zip = (string) \file_get_contents($this->fixturesDir.'/zip/windows/7zip-18.05/sample.zip');

        // Stub with unadjusted offsets: the stored central-directory offset is
        // relative to the ZIP start, not to the beginning of the file.
        $path = \tempnam(\sys_get_temp_dir(), 'archive7z_sfx_');
        \file_put_contents($path, 'MZ'.\str_repeat("\0", 1000).$zip);

        try {
            $names = (new ZipNameReader())->read($path);
        } finally {
            \unlink($path);
        }

        $plain = (new ZipNameReader())->read($this->fixturesDir.'/zip/windows/7zip-18.05/sample.zip');

        self::assertSame(
            \array_map(static function (RawName $name): string { return $name->value; }, $plain),
            \array_map(static function (RawName $name): string { return $name->value; }, $names),
        );
    }

    public function testEocdSignatureOutsideRecord(): void
    {
        $path = \tempnam(\sys_get_temp_dir(), 'archive7z_zip_');
        \file_put_contents($path, $this->zipWithForeignSignature());

        try {
            $names = (new ZipNameReader())->read($path);
        } finally {
            \unlink($path);
        }

        self::assertCount(1, $names);
        self::assertSame('a.txt', $names[0]->value);
    }

    public function testMacosUnicodeNames(): void
    {
        $archive = new Archive7z($this->fixturesDir.'/zip/macos/apple-archive/unicode.zip');

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

    /**
     * A valid single-entry ZIP that carries a fake "PK\x05\x06" signature with
     * bogus fields, followed by enough bytes to be a plausible record, plus
     * data after the archive so the real EOCD is not the canonical one. This
     * forces the central-directory validation to reject the fake candidate.
     */
    private function zipWithForeignSignature(): string
    {
        $name = 'a.txt';
        $data = 'x';
        $crc = \crc32($data);
        $size = \strlen($data);

        $local = "PK\x03\x04"
            .\pack('v', 20).\pack('v', 0).\pack('v', 0).\pack('v', 0).\pack('v', 0)
            .\pack('V', $crc).\pack('V', $size).\pack('V', $size)
            .\pack('v', \strlen($name)).\pack('v', 0).$name;

        $central = "PK\x01\x02"
            .\pack('v', 20).\pack('v', 20).\pack('v', 0).\pack('v', 0).\pack('v', 0).\pack('v', 0)
            .\pack('V', $crc).\pack('V', $size).\pack('V', $size)
            .\pack('v', \strlen($name)).\pack('v', 0).\pack('v', 0).\pack('v', 0).\pack('v', 0)
            .\pack('V', 0).\pack('V', 0).$name;

        // fake EOCD signature + 30 bogus field bytes (total=0xFFFF, sizes=0xFFFFFFFF)
        $comment = 'AAAPK'."\x05\x06".\str_repeat("\xFF", 30).'ZZ';
        $eocd = "PK\x05\x06"
            .\pack('v', 0).\pack('v', 0).\pack('v', 1).\pack('v', 1)
            .\pack('V', \strlen($central)).\pack('V', \strlen($local) + $size)
            .\pack('v', \strlen($comment)).$comment;

        // data after the archive: the real EOCD is no longer the canonical one
        $junk = "\x00\x00\x00\x00";

        return $local.$data.$central.$eocd.$junk;
    }
}
