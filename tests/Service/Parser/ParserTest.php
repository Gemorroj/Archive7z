<?php

declare(strict_types=1);

namespace Archive7z\Tests\Service\Parser;

use Archive7z\Service\Parser\Parser;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser([
            'header mess',
            '--',
            'Path = archive.7z',
            'Type = 7z',
            '',
            '----------',
            'Path = test',
            'Size = 0',
            '',
            'Path = 1.jpg',
            'Size = 12',
            '',
        ]);
    }

    public function testParseInfo(): void
    {
        self::assertSame('header mess'."\n", $this->parser->parseInfo());
    }

    public function testParseHeader(): void
    {
        self::assertSame([
            'Path' => 'archive.7z',
            'Type' => '7z',
        ], $this->parser->parseHeader());
    }

    public function testParseEntries(): void
    {
        $entries = $this->parser->parseEntries();

        self::assertCount(2, $entries);
        self::assertSame('test', $entries[0]['Path']);
        self::assertSame('0', $entries[0]['Size']);
        self::assertSame('1.jpg', $entries[1]['Path']);
        self::assertSame('12', $entries[1]['Size']);
    }

    public function testParseEntriesLimit(): void
    {
        self::assertCount(1, $this->parser->parseEntries(1));
    }

    public function testGetData(): void
    {
        self::assertCount(12, $this->parser->getData());
    }
}
