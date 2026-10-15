<?php

declare(strict_types=1);

namespace Archive7z\Tests\KnownIssues;

use Archive7z\Archive7z;
use Archive7z\Config\FallbackEncoding;
use Archive7z\Config\Options;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Expected failures, kept out of the default "Archive7z" test suite (see
 * phpunit.xml.dist) and surfaced by the dedicated, non-blocking CI job
 * "Known issues (expected failures)".
 *
 * They describe the behaviour users expect by default: archive names stored in
 * a non-UTF-8 8-bit encoding (CP866, CP1251, ...) should be readable without
 * extra configuration. Today they require an explicit
 * Options::fallbackEncoding hint, see
 * https://github.com/Gemorroj/Archive7z/issues/15.
 *
 * Run with: vendor/bin/phpunit --testsuite KnownIssues
 *
 * @internal
 */
final class NonUtf8NamesTest extends TestCase
{
    private string $tmpDir;
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = \dirname(__DIR__).'/fixtures';
        $this->tmpDir = \sys_get_temp_dir().'/'.\uniqid('Archive7z-known-', false);
        \mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    /**
     * Archives whose names are stored in a non-UTF-8 8-bit encoding (CP866/CP1251).
     *
     * @return list<list<string>>
     */
    public static function nonUtf8Provider(): array
    {
        return [
            ['zip/windows/7zip-18.05/sample.zip'],
            ['tar/windows/totalcommander-9.21a/sample.tar'],
            ['zip/windows/totalcommander-9.21a/sample.zip'],
        ];
    }

    #[DataProvider('nonUtf8Provider')]
    public function testExtractNonAscii(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName, $this->defaultOptions());
        $obj->extract($this->tmpDir);

        self::assertFileExists($this->tmpDir.'/чавес.jpg');
    }

    #[DataProvider('nonUtf8Provider')]
    public function testExtractEntryNonAscii(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName, $this->defaultOptions());
        $obj->extractEntry('чавес.jpg', $this->tmpDir);

        self::assertFileExists($this->tmpDir.'/чавес.jpg');
    }

    #[DataProvider('nonUtf8Provider')]
    public function testGetEntriesPathMaskNonAscii(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName, $this->defaultOptions());
        $entries = $obj->getEntries('чавес.jpg');

        self::assertCount(1, $entries);
    }

    private function defaultOptions(): Options
    {
        return new Options(fallbackEncoding: FallbackEncoding::asIs());
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
