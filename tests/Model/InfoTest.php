<?php

declare(strict_types=1);

namespace Archive7z\Tests\Model;

use Archive7z\Model\Info;
use Archive7z\Service\Parser\Parser;
use PHPUnit\Framework\TestCase;

class InfoTest extends TestCase
{
    public function test(): void
    {
        $info = new Info(new Parser([
            'header',
            '--',
            'Path = a.7z',
            'Type = 7z',
            'Physical Size = 100',
            'Headers Size = 10',
            'Method = LZMA2',
            'Solid = +',
            'Blocks = 1',
            'Code Page = UTF-8',
            '',
            '----------',
        ]));

        self::assertSame('a.7z', $info->getPath());
        self::assertSame('7z', $info->getType());
        self::assertSame(100, $info->getPhysicalSize());
        self::assertSame(10, $info->getHeadersSize());
        self::assertSame('LZMA2', $info->getMethod());
        self::assertTrue($info->isSolid());
        self::assertSame(1, $info->getBlocks());
        self::assertSame('UTF-8', $info->getCodePage());
        self::assertCount(12, $info->getData());
    }
}
