<?php

declare(strict_types=1);

namespace Archive7z\Tests\Service\Archive;

use Archive7z\Config\FallbackEncoding;
use Archive7z\Config\Options;
use Archive7z\Service\Archive\ArchiveExtractor;
use Archive7z\Service\Container\NameReaderResolver;
use Archive7z\Service\Namer\NameMapping;
use Archive7z\Service\Namer\NameRepairer;
use Archive7z\Service\Process\ExecutableFinder;
use Archive7z\Service\Process\ProcessFactory;
use PHPUnit\Framework\TestCase;

class ArchiveExtractorTest extends TestCase
{
    private string $tmpDir;
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = \dirname(__DIR__, 2).'/fixtures';
        $this->tmpDir = \sys_get_temp_dir().'/'.\uniqid('Archive7z-extract-', false);
        \mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testExtract(): void
    {
        $this->extractor('zip/windows/7zip-18.05/sample.zip')->extract($this->tmpDir);

        self::assertFileExists($this->tmpDir.'/1.jpg');
        self::assertFileExists($this->tmpDir.'/test/test.txt');
    }

    public function testExtractEntry(): void
    {
        $this->extractor('zip/windows/7zip-18.05/sample.zip')->extract($this->tmpDir, 'test/test.txt');

        self::assertFileExists($this->tmpDir.'/test/test.txt');
        self::assertFileDoesNotExist($this->tmpDir.'/1.jpg');
    }

    public function testExtractRepairsNonUtf8Names(): void
    {
        $extractor = $this->extractor(
            'tar/windows/totalcommander-9.21a/sample.tar',
            FallbackEncoding::codepage('Windows-1251'),
        );

        $extractor->extract($this->tmpDir);

        self::assertFileExists($this->tmpDir.'/чавес.jpg');
    }

    public function testExtractUtf8NamesWithDetect(): void
    {
        $extractor = $this->extractor('tar/linux/gnutar-1.34/sample.tar', FallbackEncoding::detect());
        $extractor->extract($this->tmpDir);

        self::assertFileExists($this->tmpDir.'/чавес.jpg');
        self::assertFileExists($this->tmpDir.'/test/test.txt');
    }

    private function extractor(string $file, ?FallbackEncoding $fallbackEncoding = null): ArchiveExtractor
    {
        $filename = $this->fixturesDir.'/'.$file;
        $options = new Options(fallbackEncoding: $fallbackEncoding);
        $factory = new ProcessFactory(
            ExecutableFinder::find(),
            $filename,
            60.0,
            $options->getEnv(),
        );

        return new ArchiveExtractor(
            $factory,
            $options,
            new NameMapping($options->getFallbackEncoding(), new NameReaderResolver(), $filename),
            new NameRepairer($options->getExtract()->getOverwriteMode()),
        );
    }

    private function removeDir(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        $entries = \scandir($dir);
        if (false === $entries) {
            return;
        }

        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = $dir.'/'.$entry;
            if (\is_dir($path)) {
                $this->removeDir($path);
            } else {
                \unlink($path);
            }
        }

        \rmdir($dir);
    }
}
