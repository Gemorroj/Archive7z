<?php

declare(strict_types=1);

namespace Archive7z\Tests\Service\Container;

use Archive7z\Service\Container\GzipNameReader;
use PHPUnit\Framework\TestCase;

class GzipNameReaderTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = \dirname(__DIR__, 2).'/fixtures';
    }

    public function testName(): void
    {
        $names = (new GzipNameReader())->read($this->fixturesDir.'/windows/7zip-18.05/sample.tgz');

        self::assertCount(1, $names);
        self::assertSame('test.tar', $names[0]->value);
        self::assertFalse($names[0]->utf8);
    }
}
