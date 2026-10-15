<?php

declare(strict_types=1);

namespace Archive7z\Tests\Service\Namer;

use Archive7z\Archive7z;
use Archive7z\Config\FallbackEncoding;
use Archive7z\Config\Options;
use Archive7z\Service\Container\NameReaderResolver;
use Archive7z\Service\Namer\NameMapping;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $mapping = $this->mapping('zip/windows/7zip-18.05/sample.zip', FallbackEncoding::asIs());

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
        $mapping = $this->mapping('tar/windows/totalcommander-9.21a/sample.tar', FallbackEncoding::detect());

        self::assertSame(\mb_convert_encoding('чавес.jpg', 'Windows-1251', 'UTF-8'), $mapping->encode('чавес.jpg'));
    }

    public function testNamesFromContainer(): void
    {
        $mapping = $this->mapping('zip/windows/7zip-18.05/sample.zip', FallbackEncoding::codepage('CP866'));

        $names = $mapping->container()->names(['1.jpg', 'test', 'test/2.jpg', 'test/test.txt', 'x.jpg']);

        self::assertCount(5, $names);
        self::assertSame('чавес.jpg', $names[4]);
    }

    public function testNamesFallbackForUnknownFormat(): void
    {
        $mapping = $this->mapping('x.7z', FallbackEncoding::codepage('Windows-1251'));
        $raw = \mb_convert_encoding('чавес.jpg', 'Windows-1251', 'UTF-8');

        self::assertSame(['чавес.jpg'], $mapping->container()->names([$raw]));
    }

    public function testTargets(): void
    {
        $mapping = $this->mapping('x.7z', FallbackEncoding::codepage('Windows-1251'));
        $raw = \mb_convert_encoding('чавес.jpg', 'Windows-1251', 'UTF-8');

        self::assertSame([['from' => $raw, 'to' => 'чавес.jpg']], $mapping->container()->targets([$raw]));
    }

    public function testRequiresRepairAsIs(): void
    {
        $mapping = $this->mapping('tar/linux/gnutar-1.34/sample.tar', FallbackEncoding::asIs());

        self::assertFalse($mapping->container()->requiresRepair());
    }

    public function testRequiresRepairFalseForUtf8Container(): void
    {
        $mapping = $this->mapping('tar/linux/gnutar-1.34/sample.tar', FallbackEncoding::codepage('Windows-1251'));

        self::assertFalse($mapping->container()->requiresRepair());
    }

    public function testRequiresRepairTrueForNonUtf8Container(): void
    {
        $mapping = $this->mapping('tar/windows/totalcommander-9.21a/sample.tar', FallbackEncoding::codepage('Windows-1251'));

        self::assertTrue($mapping->container()->requiresRepair());
    }

    public function testRequiresRepairTrueWithoutContainerReader(): void
    {
        $mapping = $this->mapping('x.7z', FallbackEncoding::codepage('Windows-1251'));

        self::assertTrue($mapping->container()->requiresRepair());
    }

    /**
     * @return list<list<string>>
     */
    public static function codepageNonZipProvider(): array
    {
        return [
            ['7z/windows/7zip-18.05/sample.7z'],
            ['gzip/windows/7zip-18.05/sample.tgz'],
            ['bz2/linux/gnutar-1.35/sample.tar.bz2'],
            ['wim/windows/7zip-18.05/sample.wim'],
        ];
    }

    public function testCodepageDecodesNames(): void
    {
        $archive = new Archive7z(
            $this->fixturesDir.'/tar/windows/totalcommander-9.21a/sample.tar',
            new Options(fallbackEncoding: FallbackEncoding::codepage('Windows-1251')),
        );

        self::assertContains('чавес.jpg', $this->paths($archive));
    }

    public function testDetectDecodesNames(): void
    {
        $archive = new Archive7z(
            $this->fixturesDir.'/tar/windows/totalcommander-9.21a/sample.tar',
            new Options(fallbackEncoding: FallbackEncoding::detect()),
        );

        self::assertContains('чавес.jpg', $this->paths($archive));
    }

    public function testDetectWithCustomCandidatesDecodesNames(): void
    {
        $archive = new Archive7z(
            $this->fixturesDir.'/zip/windows/7zip-18.05/sample.zip',
            new Options(fallbackEncoding: FallbackEncoding::detect(['CP866'])),
        );

        self::assertContains('чавес.jpg', $this->paths($archive));
    }

    public function testCallbackDecodesNames(): void
    {
        $archive = new Archive7z(
            $this->fixturesDir.'/tar/windows/totalcommander-9.21a/sample.tar',
            new Options(fallbackEncoding: FallbackEncoding::viaCallback(
                static function (string $raw): string {
                    return \mb_convert_encoding($raw, 'UTF-8', 'Windows-1251');
                },
            )),
        );

        self::assertContains('чавес.jpg', $this->paths($archive));
    }

    public function testCodepageDecodesZipNames(): void
    {
        $archive = new Archive7z(
            $this->fixturesDir.'/zip/windows/7zip-18.05/sample.zip',
            new Options(fallbackEncoding: FallbackEncoding::codepage('CP866')),
        );

        self::assertContains('чавес.jpg', $this->paths($archive));
    }

    /**
     * A configured code page must not break formats whose handler rejects the
     * "-mcp" method property.
     */
    #[DataProvider('codepageNonZipProvider')]
    public function testCodepageKeepsNonZipFormatsReadable(string $archiveName): void
    {
        $archive = new Archive7z(
            $this->fixturesDir.'/'.$archiveName,
            new Options(fallbackEncoding: FallbackEncoding::codepage('CP866')),
        );

        self::assertTrue($archive->isValid());
        self::assertNotEmpty($archive->getEntries());
    }

    /**
     * The default fallback encoding detects the code page of archives whose
     * names carry no encoding metadata.
     *
     * @see https://github.com/Gemorroj/Archive7z/issues/15
     */
    public function testDefaultEncodingDecodesNonUtf8Names(): void
    {
        $archive = new Archive7z($this->fixturesDir.'/tar/windows/totalcommander-9.21a/sample.tar');

        self::assertContains('чавес.jpg', $this->paths($archive));
    }

    /**
     * asIs() is the opt-out: the raw 8-bit names are left untouched.
     */
    public function testAsIsLeavesNonUtf8NamesRaw(): void
    {
        $archive = new Archive7z(
            $this->fixturesDir.'/tar/windows/totalcommander-9.21a/sample.tar',
            new Options(fallbackEncoding: FallbackEncoding::asIs()),
        );

        self::assertNotContains('чавес.jpg', $this->paths($archive));
    }

    public function testCodepageEncodesPathMask(): void
    {
        $archive = new Archive7z(
            $this->fixturesDir.'/tar/windows/totalcommander-9.21a/sample.tar',
            new Options(fallbackEncoding: FallbackEncoding::codepage('Windows-1251')),
        );

        $entries = $archive->getEntries('чавес.jpg');

        self::assertCount(1, $entries);
        self::assertSame('чавес.jpg', $entries[0]->getUnixPath());
    }

    public function testCodepageEncodesExtractEntryPath(): void
    {
        $directory = \sys_get_temp_dir().'/'.\uniqid('Archive7z-mask-', false);
        \mkdir($directory);

        try {
            $archive = new Archive7z(
                $this->fixturesDir.'/tar/windows/totalcommander-9.21a/sample.tar',
                new Options(fallbackEncoding: FallbackEncoding::codepage('Windows-1251')),
            );
            $archive->extractEntry('чавес.jpg', $directory);

            self::assertFileExists($directory.'/чавес.jpg');
        } finally {
            @\unlink($directory.'/чавес.jpg');
            @\rmdir($directory);
        }
    }

    public function testCodepageEncodesGetContentPath(): void
    {
        $archive = new Archive7z(
            $this->fixturesDir.'/tar/windows/totalcommander-9.21a/sample.tar',
            new Options(fallbackEncoding: FallbackEncoding::codepage('Windows-1251')),
        );

        self::assertStringStartsWith("\xFF\xD8", $archive->getContent('чавес.jpg'));
    }

    public function testCodepageEncodesRenamePaths(): void
    {
        $path = \sys_get_temp_dir().'/'.\uniqid('Archive7z-rename-', false).'.zip';
        \copy($this->fixturesDir.'/zip/windows/7zip-18.05/sample.zip', $path);

        try {
            $archive = new Archive7z($path, new Options(fallbackEncoding: FallbackEncoding::codepage('CP866')));
            $archive->renameEntry('чавес.jpg', 'renamed.jpg');

            $paths = $this->paths($archive);
            self::assertContains('renamed.jpg', $paths);
            self::assertNotContains('чавес.jpg', $paths);
        } finally {
            @\unlink($path);
        }
    }

    /**
     * @return list<string|null>
     */
    private function paths(Archive7z $archive): array
    {
        $paths = [];
        foreach ($archive->getEntries() as $entry) {
            $paths[] = $entry->getUnixPath();
        }

        return $paths;
    }

    private function mapping(string $file, FallbackEncoding $fallbackEncoding): NameMapping
    {
        return new NameMapping($fallbackEncoding, new NameReaderResolver(), $this->fixturesDir.'/'.$file);
    }
}
