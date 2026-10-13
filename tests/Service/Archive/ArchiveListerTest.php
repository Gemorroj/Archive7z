<?php

declare(strict_types=1);

namespace Archive7z\Tests\Service\Archive;

use Archive7z\Config\ArchiveOptions;
use Archive7z\Config\FallbackEncoding;
use Archive7z\Model\Info;
use Archive7z\Service\Archive\ArchiveLister;
use Archive7z\Service\Container\NameReaderResolver;
use Archive7z\Service\Namer\NameMapping;
use Archive7z\Service\Process\ExecutableFinder;
use Archive7z\Service\Process\ProcessFactory;
use PHPUnit\Framework\TestCase;

class ArchiveListerTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = \dirname(__DIR__, 2).'/fixtures';
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
     * @param array<int, array<string, string|string[]>> $entries
     *
     * @return string[]
     */
    private function paths(array $entries): array
    {
        return \array_map(static function (array $entry): string {
            $path = $entry['Path'] ?? '';

            return \str_replace('\\', '/', \is_string($path) ? $path : '');
        }, $entries);
    }

    private function lister(string $file, ?FallbackEncoding $fallbackEncoding = null): ArchiveLister
    {
        $filename = $this->fixturesDir.'/'.$file;
        $options = new ArchiveOptions(fallbackEncoding: $fallbackEncoding);
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
