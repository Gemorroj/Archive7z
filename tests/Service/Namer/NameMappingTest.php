<?php

declare(strict_types=1);

namespace Archive7z\Tests\Service\Namer;

use Archive7z\Config\FallbackEncoding;
use Archive7z\Service\Container\NameReaderResolver;
use Archive7z\Service\Namer\NameMapping;
use PHPUnit\Framework\TestCase;

class NameMappingTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = \dirname(__DIR__, 2).'/fixtures';
    }

    public function testAsIs(): void
    {
        $mapping = $this->mapping('windows/7zip-18.05/sample.zip', FallbackEncoding::asIs());

        self::assertTrue($mapping->isAsIs());
        self::assertSame('чавес.jpg', $mapping->encode('чавес.jpg'));
        self::assertSame("a\nb", $mapping->decodeOutput("a\nb"));
    }

    public function testEncode(): void
    {
        $mapping = $this->mapping('x.7z', FallbackEncoding::codepage('Windows-1251'));

        self::assertSame(\mb_convert_encoding('чавес', 'Windows-1251', 'UTF-8'), $mapping->encode('чавес'));
    }

    public function testEncodeDetectFromContainer(): void
    {
        $mapping = $this->mapping('windows/totalcommander-9.21a/sample.tar', FallbackEncoding::detect());

        self::assertSame(\mb_convert_encoding('чавес.jpg', 'Windows-1251', 'UTF-8'), $mapping->encode('чавес.jpg'));
    }

    public function testNamesFromContainer(): void
    {
        $mapping = $this->mapping('windows/7zip-18.05/sample.zip', FallbackEncoding::codepage('CP866'));

        $names = $mapping->names(['1.jpg', 'test', 'test/2.jpg', 'test/test.txt', 'x.jpg']);

        self::assertCount(5, $names);
        self::assertSame('чавес.jpg', $names[4]);
    }

    public function testNamesFallbackForUnknownFormat(): void
    {
        $mapping = $this->mapping('x.7z', FallbackEncoding::codepage('Windows-1251'));
        $raw = \mb_convert_encoding('чавес.jpg', 'Windows-1251', 'UTF-8');

        self::assertSame(['чавес.jpg'], $mapping->names([$raw]));
    }

    public function testTargets(): void
    {
        $mapping = $this->mapping('x.7z', FallbackEncoding::codepage('Windows-1251'));
        $raw = \mb_convert_encoding('чавес.jpg', 'Windows-1251', 'UTF-8');

        self::assertSame([['from' => $raw, 'to' => 'чавес.jpg']], $mapping->targets([$raw]));
    }

    private function mapping(string $file, FallbackEncoding $fallbackEncoding): NameMapping
    {
        return new NameMapping($fallbackEncoding, new NameReaderResolver(), $this->fixturesDir.'/'.$file);
    }
}
