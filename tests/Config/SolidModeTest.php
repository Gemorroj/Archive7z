<?php

declare(strict_types=1);

namespace Archive7z\Tests\Config;

use Archive7z\Config\SolidMode;
use PHPUnit\Framework\TestCase;

class SolidModeTest extends TestCase
{
    public function testOn(): void
    {
        self::assertSame('on', (string) SolidMode::on());
    }

    public function testOff(): void
    {
        self::assertSame('off', (string) SolidMode::off());
    }

    public function testPerExtension(): void
    {
        self::assertSame('e', (string) SolidMode::perExtension());
    }

    public function testFilesLimit(): void
    {
        self::assertSame('2f', (string) SolidMode::filesLimit(2));
    }

    public function testTotalSizeLimit(): void
    {
        self::assertSame('1024b', (string) SolidMode::totalSizeLimit(1024));
    }

    public function testLimits(): void
    {
        self::assertSame('2f1024b', (string) SolidMode::limits(2, 1024));
    }
}
