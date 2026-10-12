<?php

declare(strict_types=1);

namespace Archive7z\Tests\Config;

use Archive7z\Config\FallbackEncoding;
use Archive7z\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FallbackEncodingTest extends TestCase
{
    public function testAsIs(): void
    {
        $encoding = FallbackEncoding::asIs();

        self::assertTrue($encoding->isAsIs());
        self::assertFalse($encoding->isDetect());
        self::assertNull($encoding->getEncoding());
    }

    public function testDetect(): void
    {
        $encoding = FallbackEncoding::detect();

        self::assertTrue($encoding->isDetect());
        self::assertFalse($encoding->isAsIs());
        self::assertNull($encoding->getEncoding());
    }

    public function testCodepage(): void
    {
        $encoding = FallbackEncoding::codepage('Windows-1251');

        self::assertFalse($encoding->isAsIs());
        self::assertFalse($encoding->isDetect());
        self::assertSame('Windows-1251', $encoding->getEncoding());
        self::assertSame(1251, $encoding->getCodepage());
    }

    public function testUnknownCodepageNumber(): void
    {
        self::assertNull(FallbackEncoding::codepage('X-Unknown')->getCodepage());
    }

    public function testEmptyCodepage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FallbackEncoding::codepage('');
    }
}
