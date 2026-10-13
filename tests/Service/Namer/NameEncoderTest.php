<?php

declare(strict_types=1);

namespace Archive7z\Tests\Service\Namer;

use Archive7z\Config\FallbackEncoding;
use Archive7z\Service\Namer\NameEncoder;
use PHPUnit\Framework\TestCase;

class NameEncoderTest extends TestCase
{
    public function testAsIs(): void
    {
        $encoder = NameEncoder::resolve(FallbackEncoding::asIs());

        self::assertTrue($encoder->isAsIs());
        self::assertSame('abc', $encoder->decode('abc'));
        self::assertSame('abc', $encoder->encode('abc'));
    }

    public function testCodepage(): void
    {
        $encoder = NameEncoder::resolve(FallbackEncoding::codepage('Windows-1251'));
        $raw = \mb_convert_encoding('чавес', 'Windows-1251', 'UTF-8');

        self::assertFalse($encoder->isAsIs());
        self::assertSame('чавес', $encoder->decode($raw));
        self::assertSame($raw, $encoder->encode('чавес'));
        // already valid UTF-8 is never re-decoded
        self::assertSame('чавес', $encoder->decode('чавес'));
    }

    public function testAsIsUnescapes(): void
    {
        $encoder = NameEncoder::resolve(FallbackEncoding::asIs());
        $escaped = "\u{EFF7}\u{EFE0}\u{EFE2}\u{EFE5}\u{EFF1}.jpg";

        self::assertSame("\xF7\xE0\xE2\xE5\xF1.jpg", $encoder->decode($escaped));
    }

    public function testDecodeEscapedBytes(): void
    {
        $encoder = NameEncoder::resolve(FallbackEncoding::codepage('Windows-1251'));

        // 7-Zip escapes bytes it cannot represent as the code point U+EF00 + byte.
        $escaped = "\u{EFF7}\u{EFE0}\u{EFE2}\u{EFE5}\u{EFF1}.jpg";

        self::assertSame('чавес.jpg', $encoder->decode($escaped));
    }

    public function testDecodeKeepsNonEscapePrivateUse(): void
    {
        $encoder = NameEncoder::resolve(FallbackEncoding::codepage('Windows-1251'));

        // Only U+EF80..U+EFFF are escape points (IS_ESCAPE_POINT masks 0xFFFFFF80).
        self::assertSame("\u{EF00}", $encoder->decode("\u{EF00}"));
    }

    public function testDetect(): void
    {
        $raw = \mb_convert_encoding('чавес', 'Windows-1251', 'UTF-8');
        $encoder = NameEncoder::resolve(FallbackEncoding::detect(), $raw);

        self::assertSame('чавес', $encoder->decode($raw));
    }

    public function testDetectEscapedBytes(): void
    {
        $escaped = "\u{EFF7}\u{EFE0}\u{EFE2}\u{EFE5}\u{EFF1}.jpg";
        $encoder = NameEncoder::resolve(FallbackEncoding::detect(), $escaped);

        self::assertFalse($encoder->isAsIs());
        self::assertSame('чавес.jpg', $encoder->decode($escaped));
    }

    public function testDetectWithoutSample(): void
    {
        self::assertTrue(NameEncoder::resolve(FallbackEncoding::detect())->isAsIs());
    }
}
