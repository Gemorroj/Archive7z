<?php

declare(strict_types=1);

namespace Archive7z\Tests\Model;

use Archive7z\Archive7z;
use Archive7z\Model\Info;
use Archive7z\Service\Parser\Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InfoTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = \dirname(__DIR__).'/fixtures';
    }

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

    /**
     * @return list<list<string>>
     */
    public static function archiveProvider(): array
    {
        return [
            ['zip/windows/7zip-26.04/misnamed.7z'],
            ['zip/windows/7zip-26.04/warnings.zip'],
            ['7z/windows/7zip-18.05/sample.7z'],
            ['tar/windows/7zip-18.05/sample.tar'],
            ['wim/windows/7zip-18.05/sample.wim'],
            ['zip/windows/7zip-18.05/sample.zip'],
            ['gzip/windows/7zip-18.05/sample.tgz'],
            ['tar/windows/totalcommander-9.21a/sample.tar'],
            ['zip/windows/totalcommander-9.21a/sample.zip'],
            ['zip/windows/winrar-5.61/sample.zip'],
            ['rar/windows/winrar-5.61/sample-rar4.rar'],
            ['rar/windows/winrar-5.61/sample-rar5.rar'],
            ['zip/linux/zip-0.3/sample.zip'],
            ['7z/linux/p7zip-16.02/sample.7z'],
            ['7z/linux/7zip-26.04/sample.7z'],
            ['tar/linux/p7zip-16.02/sample.tar'],
            ['zip/linux/p7zip-16.02/sample.zip'],
            ['tar/linux/gnutar-1.34/sample.tar'],
            ['gzip/linux/gnutar-1.34/sample.tar.gz'],
            ['bz2/linux/gnutar-1.35/sample.tar.bz2'],
            ['tar/linux/bsdtar-3.6.2/sample.tar'],
            ['zip/linux/bsdtar-3.6.2/sample.zip'],
            ['zip/macos/apple-archive/sample.zip'],
            ['zip/macos/apple-archive/unicode.zip'],
            ['zip/macos/zip/sample.zip'],
            ['tar/macos/bsdtar/sample.tar'],
            ['tar/macos/bsdtar/unicode.tar'],
        ];
    }

    #[DataProvider('archiveProvider')]
    public function testInfoFromArchives(string $archiveName): void
    {
        $archive = new Archive7z($this->fixturesDir.'/'.$archiveName);
        $info = $archive->getInfo();

        self::assertIsString($info->getPath());
        self::assertIsString($info->getType());
        self::assertGreaterThan(0, $info->getPhysicalSize());

        // Version line format depends on the build:
        //   Windows:        7-Zip 26.04 (x64) : Copyright (c) 1999-2026 Igor Pavlov : 2026-10-05
        //   Linux official: 7-Zip (z) 26.04 (x64) : Copyright ...
        //   Linux distro:   7-Zip 25.01 (x64) : Copyright ...
        $versionLine = $info->getData()[1];

        self::assertMatchesRegularExpression('/^7\-Zip .+/', $versionLine);
    }
}
