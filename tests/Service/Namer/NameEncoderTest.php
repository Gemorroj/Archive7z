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

    public function testDetect(): void
    {
        $raw = \mb_convert_encoding('чавес', 'Windows-1251', 'UTF-8');
        $encoder = NameEncoder::resolve(FallbackEncoding::detect(), $raw);

        self::assertSame('чавес', $encoder->decode($raw));
    }

    public function testDetectWithoutSample(): void
    {
        self::assertTrue(NameEncoder::resolve(FallbackEncoding::detect())->isAsIs());
    }
}
