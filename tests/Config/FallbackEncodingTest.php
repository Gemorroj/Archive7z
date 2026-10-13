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
        self::assertFalse($encoding->isCallback());
        self::assertNull($encoding->getEncoding());
        self::assertSame(FallbackEncoding::DETECT_CANDIDATES, $encoding->getDetectCandidates());
    }

    public function testDetectCustomCandidates(): void
    {
        self::assertSame(['CP866', 'KOI8-R'], FallbackEncoding::detect(['CP866', 'KOI8-R'])->getDetectCandidates());
    }

    public function testDetectEmptyCandidatesUseDefault(): void
    {
        self::assertSame(FallbackEncoding::DETECT_CANDIDATES, FallbackEncoding::detect([])->getDetectCandidates());
    }

    public function testDetectUnknownCandidateThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FallbackEncoding::detect(['NOPE']);
    }

    public function testCodepage(): void
    {
        $encoding = FallbackEncoding::codepage('Windows-1251');

        self::assertFalse($encoding->isAsIs());
        self::assertFalse($encoding->isDetect());
        self::assertSame('Windows-1251', $encoding->getEncoding());
        self::assertSame(1251, $encoding->getCodepage());
    }

    public function testCodepageWithoutZipNumber(): void
    {
        // A valid encoding that has no 7-Zip -mcp number yields no code page.
        self::assertNull(FallbackEncoding::codepage('SJIS')->getCodepage());
    }

    public function testEmptyCodepage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FallbackEncoding::codepage('');
    }

    public function testUnknownCodepageThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown encoding "NOPE"');

        FallbackEncoding::codepage('NOPE');
    }

    public function testCallback(): void
    {
        $encoding = FallbackEncoding::viaCallback(static function (string $raw): string {
            return $raw;
        });

        self::assertTrue($encoding->isCallback());
        self::assertFalse($encoding->isAsIs());
        self::assertFalse($encoding->isDetect());
        self::assertNull($encoding->getEncoding());
        self::assertNull($encoding->getCodepage());
        self::assertInstanceOf(\Closure::class, $encoding->getDecodeCallback());
        self::assertNull($encoding->getEncodeCallback());
    }

    public function testCallbackWithEncoder(): void
    {
        $encoding = FallbackEncoding::viaCallback(
            static function (string $raw): string {
                return $raw;
            },
            static function (string $value): string {
                return $value;
            },
        );

        self::assertInstanceOf(\Closure::class, $encoding->getEncodeCallback());
    }
}
