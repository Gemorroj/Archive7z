<?php

declare(strict_types=1);

namespace Archive7z\Tests\Service\Archive;

use Archive7z\Archive7z;
use Archive7z\Config\FallbackEncoding;
use Archive7z\Config\Options;
use Archive7z\Model\Entry;
use Archive7z\Model\Info;
use Archive7z\Service\Archive\ArchiveLister;
use Archive7z\Service\Container\NameReaderResolver;
use Archive7z\Service\Namer\NameMapping;
use Archive7z\Service\Process\ExecutableFinder;
use Archive7z\Service\Process\ProcessFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ArchiveListerTest extends TestCase
{
    private string $tmpDir;
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = \dirname(__DIR__, 2).'/fixtures';
        $this->tmpDir = \sys_get_temp_dir().'/'.\uniqid('Archive7z-lister-', false);
        \mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testEntries(): void
    {
        $entries = $this->lister('zip/windows/7zip-18.05/sample.zip')->entries();
        $paths = $this->paths($entries);

        self::assertContains('1.jpg', $paths);
        self::assertContains('test/test.txt', $paths);
    }

    public function testEntriesWithPathMask(): void
    {
        $entries = $this->lister('zip/windows/7zip-18.05/sample.zip')->entries('test/test.txt');

        self::assertCount(1, $entries);
    }

    public function testEntriesWithLimit(): void
    {
        $entries = $this->lister('zip/windows/7zip-18.05/sample.zip')->entries(null, 2);

        self::assertCount(2, $entries);
    }

    public function testInfo(): void
    {
        $info = $this->lister('zip/windows/7zip-18.05/sample.zip')->info();

        self::assertInstanceOf(Info::class, $info);
        self::assertSame('zip', $info->getType());
        self::assertGreaterThan(0, $info->getPhysicalSize());
    }

    public function testEntriesDecodeNonUtf8Names(): void
    {
        $lister = $this->lister(
            'tar/windows/totalcommander-9.21a/sample.tar',
            FallbackEncoding::codepage('Windows-1251'),
        );

        self::assertContains('чавес.jpg', $this->paths($lister->entries()));
    }

    /**
     * @param array<int, array<string, string|list<string>>> $entries
     *
     * @return array<int, string>
     */
    private function paths(array $entries): array
    {
        return \array_map(static function (array $entry): string {
            $path = $entry['Path'] ?? '';

            return \str_replace('\\', '/', \is_string($path) ? $path : '');
        }, $entries);
    }

    /**
     * @return list<list<string>>
     */
    public static function bareCompressedStreamProvider(): array
    {
        return [
            ['xz'],
            ['bz2'],
        ];
    }

    /**
     * Bare xz/bzip2 streams store no name and 7-Zip prints no "Path" for them.
     * The lister exposes the name 7-Zip uses for matching and extraction: the
     * archive file name without its final extension.
     *
     * @see https://github.com/Gemorroj/Archive7z/issues/30
     */
    #[DataProvider('bareCompressedStreamProvider')]
    public function testBareCompressedStreamEntryName(string $extension): void
    {
        $source = $this->tmpDir.'/payload.txt';
        \file_put_contents($source, 'payload content');

        $archive = $this->tmpDir.'/payload.'.$extension;
        (new Archive7z($archive))->addEntry($source);

        $obj = new Archive7z($archive);
        $entries = $obj->getEntries();

        self::assertCount(1, $entries);
        self::assertSame('payload', $entries[0]->getPath());
        self::assertInstanceOf(Entry::class, $obj->getEntry('payload'));
        self::assertSame('payload content', $obj->getContent('payload'));

        $extractDir = $this->tmpDir.'/extract-'.$extension;
        \mkdir($extractDir);
        $obj->extractEntry('payload', $extractDir);
        self::assertFileExists($extractDir.'/payload');
    }

    private function removeDir(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        foreach (\scandir($dir) ?: [] as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = $dir.'/'.$entry;
            if (\is_dir($path) && !\is_link($path)) {
                $this->removeDir($path);
            } else {
                @\unlink($path);
            }
        }

        \rmdir($dir);
    }

    private function lister(string $file, ?FallbackEncoding $fallbackEncoding = null): ArchiveLister
    {
        $filename = $this->fixturesDir.'/'.$file;
        $options = new Options(fallbackEncoding: $fallbackEncoding);
        $factory = new ProcessFactory(
            ExecutableFinder::find(),
            $filename,
            60.0,
            $options->getEnv(),
        );

        return new ArchiveLister(
            $factory,
            $options,
            new NameMapping($options->getFallbackEncoding(), new NameReaderResolver(), $filename),
        );
    }
}
