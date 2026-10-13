<?php

declare(strict_types=1);

namespace Archive7z\Tests;

use Archive7z\Archive7z;
use Archive7z\Config\FallbackEncoding;
use Archive7z\Config\Options;
use Archive7z\Config\SolidMode;
use Archive7z\Exception\ExceptionInterface;
use Archive7z\Exception\RuntimeException;
use Archive7z\Model\Entry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class Archive7zTest extends TestCase
{
    private string $tmpDir;
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__.'/fixtures';

        $this->tmpDir = \sys_get_temp_dir().'/'.\uniqid('Archive7z-', false);
        \mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->cleanDir($this->tmpDir);
        \rmdir($this->tmpDir);
    }

    protected function cleanDir(string $dir): void
    {
        $h = \opendir($dir);
        if (!$h) {
            return;
        }

        while (($file = \readdir($h)) !== false) {
            if ('.' !== $file && '..' !== $file) {
                if (\is_dir($dir.'/'.$file)) {
                    $this->cleanDir($dir.'/'.$file);
                    \rmdir($dir.'/'.$file);
                } else {
                    \unlink($dir.'/'.$file);
                }
            }
        }
        \closedir($h);
    }

    private static function resolvePath(string $path): string
    {
        $real = \realpath($path);
        if (false === $real) {
            self::fail('Cannot resolve path: '.$path);
        }

        return $real;
    }

    public function testGet7zipInformation(): void
    {
        $info = Archive7z::get7zipInformation();
        self::assertStringContainsString('7-Zip', $info);
    }

    public function testConstructorWithBadBinaryThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Binary of 7-zip is not available');

        new Archive7z($this->fixturesDir.'/7z/windows/7zip-18.05/sample.7z', null, '/nonexistent/7z');
    }

    public function testGet7zipInformationBadBinaryThrows(): void
    {
        $this->expectException(RuntimeException::class);

        Archive7z::get7zipInformation('/nonexistent/7z');
    }

    public function testFallbackEncodingCodepage(): void
    {
        $obj = new Archive7z(
            $this->fixturesDir.'/tar/windows/totalcommander-9.21a/sample.tar',
            new Options(fallbackEncoding: FallbackEncoding::codepage('Windows-1251')),
        );

        $paths = [];
        foreach ($obj->getEntries() as $entry) {
            $paths[] = $entry->getUnixPath();
        }

        self::assertContains('чавес.jpg', $paths);
    }

    public function testFallbackEncodingDetect(): void
    {
        $obj = new Archive7z(
            $this->fixturesDir.'/tar/windows/totalcommander-9.21a/sample.tar',
            new Options(fallbackEncoding: FallbackEncoding::detect()),
        );

        $paths = [];
        foreach ($obj->getEntries() as $entry) {
            $paths[] = $entry->getUnixPath();
        }

        self::assertContains('чавес.jpg', $paths);
    }

    public function testFallbackEncodingDetectCustomCandidates(): void
    {
        $obj = new Archive7z(
            $this->fixturesDir.'/zip/windows/7zip-18.05/sample.zip',
            new Options(fallbackEncoding: FallbackEncoding::detect(['CP866'])),
        );

        $paths = [];
        foreach ($obj->getEntries() as $entry) {
            $paths[] = $entry->getUnixPath();
        }

        self::assertContains('чавес.jpg', $paths);
    }

    public function testFallbackEncodingCallback(): void
    {
        $obj = new Archive7z(
            $this->fixturesDir.'/tar/windows/totalcommander-9.21a/sample.tar',
            new Options(fallbackEncoding: FallbackEncoding::viaCallback(
                static function (string $raw): string {
                    return \mb_convert_encoding($raw, 'UTF-8', 'Windows-1251');
                },
            )),
        );

        $paths = [];
        foreach ($obj->getEntries() as $entry) {
            $paths[] = $entry->getUnixPath();
        }

        self::assertContains('чавес.jpg', $paths);
    }

    public function testFallbackEncodingCallbackExtract(): void
    {
        $obj = new Archive7z(
            $this->fixturesDir.'/tar/windows/totalcommander-9.21a/sample.tar',
            new Options(fallbackEncoding: FallbackEncoding::viaCallback(
                static function (string $raw): string {
                    return \mb_convert_encoding($raw, 'UTF-8', 'Windows-1251');
                },
                static function (string $value): string {
                    return \mb_convert_encoding($value, 'Windows-1251', 'UTF-8');
                },
            )),
        );

        $directory = $this->tmpDir.'/callback';
        \mkdir($directory);
        $obj->extract($directory);

        self::assertFileExists($directory.'/чавес.jpg');
    }

    public function testFallbackEncodingCodepageZip(): void
    {
        $obj = new Archive7z(
            $this->fixturesDir.'/zip/windows/7zip-18.05/sample.zip',
            new Options(fallbackEncoding: FallbackEncoding::codepage('CP866')),
        );

        $paths = [];
        foreach ($obj->getEntries() as $entry) {
            $paths[] = $entry->getUnixPath();
        }

        self::assertContains('чавес.jpg', $paths);
    }

    #[DataProvider('codepageNonZipProvider')]
    public function testFallbackEncodingCodepageNonZip(string $archiveName): void
    {
        $obj = new Archive7z(
            $this->fixturesDir.'/'.$archiveName,
            new Options(fallbackEncoding: FallbackEncoding::codepage('CP866')),
        );

        self::assertTrue($obj->isValid());
        self::assertNotEmpty($obj->getEntries());
    }

    /**
     * A configured code page must not break formats whose handler rejects the
     * "-mcp" method property.
     *
     * @return string[][]
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

    public function testExtractRepairsNonUtf8Names(): void
    {
        $obj = new Archive7z(
            $this->fixturesDir.'/tar/windows/totalcommander-9.21a/sample.tar',
            new Options(fallbackEncoding: FallbackEncoding::codepage('Windows-1251')),
        );
        $obj->extract($this->tmpDir);

        self::assertFileExists($this->tmpDir.'/чавес.jpg');
        self::assertFileExists($this->tmpDir.'/1.jpg');
        self::assertFileExists($this->tmpDir.'/test/test.txt');
    }

    public function testExtractDetectNonUtf8Names(): void
    {
        $obj = new Archive7z(
            $this->fixturesDir.'/tar/windows/totalcommander-9.21a/sample.tar',
            new Options(fallbackEncoding: FallbackEncoding::detect()),
        );
        $obj->extract($this->tmpDir);

        self::assertFileExists($this->tmpDir.'/чавес.jpg');
    }

    public function testFallbackEncodingCodepageMask(): void
    {
        $obj = new Archive7z(
            $this->fixturesDir.'/tar/windows/totalcommander-9.21a/sample.tar',
            new Options(fallbackEncoding: FallbackEncoding::codepage('Windows-1251')),
        );

        $entries = $obj->getEntries('чавес.jpg');

        self::assertCount(1, $entries);
        self::assertSame('чавес.jpg', $entries[0]->getUnixPath());
    }

    public function testFallbackEncodingCodepageExtractEntry(): void
    {
        $obj = new Archive7z(
            $this->fixturesDir.'/tar/windows/totalcommander-9.21a/sample.tar',
            new Options(fallbackEncoding: FallbackEncoding::codepage('Windows-1251')),
        );
        $obj->extractEntry('чавес.jpg', $this->tmpDir);

        self::assertFileExists($this->tmpDir.'/чавес.jpg');
    }

    public function testFallbackEncodingCodepageGetContent(): void
    {
        $obj = new Archive7z(
            $this->fixturesDir.'/tar/windows/totalcommander-9.21a/sample.tar',
            new Options(fallbackEncoding: FallbackEncoding::codepage('Windows-1251')),
        );

        $content = $obj->getContent('чавес.jpg');

        self::assertStringStartsWith("\xFF\xD8", $content);
    }

    public function testGetContentDoesNotExpandWildcards(): void
    {
        $obj = new Archive7z($this->fixturesDir.'/tar/linux/gnutar-1.34/sample.tar');

        self::assertSame('', $obj->getContent('*.jpg'));
    }

    public function testExtractEntryDoesNotExpandWildcards(): void
    {
        $obj = new Archive7z($this->fixturesDir.'/tar/linux/gnutar-1.34/sample.tar');
        $obj->extractEntry('*.jpg', $this->tmpDir);

        self::assertSame([], \glob($this->tmpDir.'/*') ?: []);
    }

    /**
     * @return string[][]
     */
    public static function extractProvider(): array
    {
        return [
            ['zip/windows/7zip-26.04/misnamed.7z'],
            ['zip/windows/7zip-26.04/warnings.zip'],
            ['7z/windows/7zip-18.05/sample.7z'],
            ['tar/windows/7zip-18.05/sample.tar'],
            ['wim/windows/7zip-18.05/sample.wim'],
            ['zip/windows/7zip-18.05/sample.zip'],
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
            ['tar/linux/bsdtar-3.6.2/sample.tar'],
            ['zip/linux/bsdtar-3.6.2/sample.zip'],
        ];
    }

    /**
     * Archives whose non-ASCII names are decodable with the default (UTF-8)
     * encoding. Archives whose names use a non-UTF-8 8-bit encoding live in the
     * KnownIssues suite, see tests/KnownIssues/NonUtf8NamesTest.php.
     *
     * @return string[][]
     */
    public static function utf8Provider(): array
    {
        return [
            ['zip/windows/7zip-26.04/misnamed.7z'],
            ['zip/windows/7zip-26.04/warnings.zip'],
            ['7z/windows/7zip-18.05/sample.7z'],
            ['tar/windows/7zip-18.05/sample.tar'],
            ['wim/windows/7zip-18.05/sample.wim'],
            ['zip/windows/winrar-5.61/sample.zip'],
            ['rar/windows/winrar-5.61/sample-rar4.rar'],
            ['rar/windows/winrar-5.61/sample-rar5.rar'],
            ['zip/linux/zip-0.3/sample.zip'],
            ['7z/linux/p7zip-16.02/sample.7z'],
            ['7z/linux/7zip-26.04/sample.7z'],
            ['tar/linux/p7zip-16.02/sample.tar'],
            ['zip/linux/p7zip-16.02/sample.zip'],
            ['tar/linux/gnutar-1.34/sample.tar'],
            ['tar/linux/bsdtar-3.6.2/sample.tar'],
            ['zip/linux/bsdtar-3.6.2/sample.zip'],
        ];
    }

    /**
     * @return string[][]
     */
    public static function basicProvider(): array
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

    #[DataProvider('utf8Provider')]
    public function testExtractNonAscii(string $archiveName): void
    {
        $dirNonAscii = $this->tmpDir.'/папка';
        $nonAsciiFile = 'чавес.jpg';

        if (!\mkdir($dirNonAscii)) {
            self::markTestIncomplete('Cant create non-ascii directory.');
        }

        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);
        $obj->extract($dirNonAscii);

        self::assertFileExists($dirNonAscii.'/1.jpg');
        self::assertFileExists($dirNonAscii.'/'.$nonAsciiFile);
        self::assertFileExists($dirNonAscii.'/test/test.txt');
    }

    /**
     * @return string[][]
     */
    public static function extractPasswdProvider(): array
    {
        return [
            ['7z/windows/7zip-26.04/encrypted.7z'],
            ['zip/windows/7zip-26.04/encrypted.zip'],
            ['rar/windows/winrar-3/encrypted-rar3.rar'],
            ['rar/linux/rar-7.30/encrypted-rar5.rar'],
        ];
    }

    #[DataProvider('extractPasswdProvider')]
    public function testExtractPasswdNonAscii(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName, new Options(password: '123'));
        $obj->extract($this->tmpDir);

        $nonAsciiFile = 'чавес.jpg';

        self::assertFileExists($this->tmpDir.'/1.jpg');
        self::assertFileExists($this->tmpDir.'/'.$nonAsciiFile);
        self::assertFileExists($this->tmpDir.'/test/test.txt');
    }

    #[DataProvider('extractProvider')]
    public function testExtractOverwrite(string $archiveName): void
    {
        if (!\mkdir($this->tmpDir.'/test')) {
            self::markTestIncomplete('Cant create directory.');
        }

        $sourceFile = $this->fixturesDir.'/test.txt';
        $targetFile = $this->tmpDir.'/test/test.txt';
        $archiveFile = $this->fixturesDir.'/testArchive.txt';
        $archivePath = $this->fixturesDir.'/'.$archiveName;

        $obj = new Archive7z($archivePath, new Options(overwriteMode: Options::OVERWRITE_MODE_A));
        \copy($sourceFile, $targetFile);
        $obj->extract($this->tmpDir);
        self::assertFileEquals($archiveFile, $targetFile, $archiveName);

        $obj = new Archive7z($archivePath, new Options(overwriteMode: Options::OVERWRITE_MODE_S));
        \copy($sourceFile, $targetFile);
        $obj->extract($this->tmpDir);
        self::assertFileEquals($sourceFile, $targetFile);

        $obj = new Archive7z($archivePath, new Options(overwriteMode: Options::OVERWRITE_MODE_T));
        \copy($sourceFile, $targetFile);
        $obj->extract($this->tmpDir);
        self::assertFileExists($this->tmpDir.'/test/test_1.txt');
        self::assertFileEquals($archiveFile, $targetFile);
        self::assertFileEquals($sourceFile, $this->tmpDir.'/test/test_1.txt');
        \unlink($this->tmpDir.'/test/test_1.txt');

        $obj = new Archive7z($archivePath, new Options(overwriteMode: Options::OVERWRITE_MODE_U));
        \copy($sourceFile, $targetFile);
        $obj->extract($this->tmpDir);
        self::assertFileExists($this->tmpDir.'/test/test_1.txt');
        self::assertFileEquals($sourceFile, $targetFile);
        self::assertFileEquals($archiveFile, $this->tmpDir.'/test/test_1.txt');
        \unlink($this->tmpDir.'/test/test_1.txt');
    }

    #[DataProvider('extractProvider')]
    public function testExtractEntry(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);
        $obj->extractEntry('test/2.jpg', $this->tmpDir);

        self::assertFileExists($this->tmpDir.'/test/2.jpg');
    }

    #[DataProvider('extractProvider')]
    public function testExtractEntryOverwrite(string $archiveName): void
    {
        if (!\mkdir($this->tmpDir.'/test')) {
            self::markTestIncomplete('Cant create directory.');
        }

        $sourceFile = $this->fixturesDir.'/test.txt';
        $targetFile = $this->tmpDir.'/test/test.txt';
        $archiveFile = $this->fixturesDir.'/testArchive.txt';
        $archivePath = $this->fixturesDir.'/'.$archiveName;

        $obj = new Archive7z($archivePath, new Options(overwriteMode: Options::OVERWRITE_MODE_A));
        \copy($sourceFile, $targetFile);
        $obj->extractEntry('test/test.txt', $this->tmpDir);
        self::assertFileEquals($archiveFile, $targetFile);

        $obj = new Archive7z($archivePath, new Options(overwriteMode: Options::OVERWRITE_MODE_S));
        \copy($sourceFile, $targetFile);
        $obj->extractEntry('test/test.txt', $this->tmpDir);
        self::assertFileEquals($sourceFile, $targetFile);

        $obj = new Archive7z($archivePath, new Options(overwriteMode: Options::OVERWRITE_MODE_T));
        \copy($sourceFile, $targetFile);
        $obj->extractEntry('test/test.txt', $this->tmpDir);
        self::assertFileExists($this->tmpDir.'/test/test_1.txt');
        self::assertFileEquals($archiveFile, $targetFile);
        self::assertFileEquals($sourceFile, $this->tmpDir.'/test/test_1.txt');
        \unlink($this->tmpDir.'/test/test_1.txt');

        $obj = new Archive7z($archivePath, new Options(overwriteMode: Options::OVERWRITE_MODE_U));
        \copy($sourceFile, $targetFile);
        $obj->extractEntry('test/test.txt', $this->tmpDir);
        self::assertFileExists($this->tmpDir.'/test/test_1.txt');
        self::assertFileEquals($sourceFile, $targetFile);
        self::assertFileEquals($archiveFile, $this->tmpDir.'/test/test_1.txt');
        \unlink($this->tmpDir.'/test/test_1.txt');
    }

    #[DataProvider('utf8Provider')]
    public function testExtractEntryNonAscii(string $archiveName): void
    {
        $file = 'чавес.jpg';

        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);
        $obj->extractEntry($file, $this->tmpDir);

        self::assertFileExists($this->tmpDir.'/'.$file);
    }

    #[DataProvider('extractPasswdProvider')]
    public function testExtractEntryPasswd(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName, new Options(password: '123'));
        $obj->extractEntry('1.jpg', $this->tmpDir);

        self::assertFileExists($this->tmpDir.'/1.jpg');
    }

    #[DataProvider('extractPasswdProvider')]
    public function testGetContentPasswd(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName, new Options(password: '123'));
        $result = $obj->getContent('test/test.txt');

        self::assertStringEqualsFile($this->fixturesDir.'/testArchive.txt', $result);
    }

    #[DataProvider('extractPasswdProvider')]
    public function testGetEntriesPasswd(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName, new Options(password: '123'));
        $result = $obj->getEntries();

        self::assertCount(5, $result); // 4 file + 1 directory
        self::assertInstanceOf(Entry::class, $result[0]);
    }

    #[DataProvider('extractPasswdProvider')]
    public function testGetEntryPasswd(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName, new Options(password: '123'));
        $result = $obj->getEntry('test/test.txt');

        self::assertInstanceOf(Entry::class, $result);
    }

    /**
     * @return string[][]
     */
    public static function entryProvider(): array
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
            // ['rar/windows/winrar-5.61/sample-rar4.rar'], // not supported
            // ['rar/windows/winrar-5.61/sample-rar5.rar'], // not supported
            ['zip/linux/zip-0.3/sample.zip'],
            ['7z/linux/p7zip-16.02/sample.7z'],
            ['7z/linux/7zip-26.04/sample.7z'],
            ['tar/linux/p7zip-16.02/sample.tar'],
            ['zip/linux/p7zip-16.02/sample.zip'],
        ];
    }

    public function testAddEntryExists(): void
    {
        $archive = $this->tmpDir.'/existing.7z';
        \copy($this->fixturesDir.'/7z/windows/7zip-18.05/sample.7z', $archive);

        $obj = new Archive7z($archive);
        self::assertInstanceOf(Entry::class, $obj->getEntry('1.jpg'));

        $obj->addEntry(__FILE__);

        $added = $obj->getEntry(\basename(__FILE__));
        self::assertInstanceOf(Entry::class, $added);
        self::assertEquals(\basename(__FILE__), $added->getPath());
        self::assertInstanceOf(Entry::class, $obj->getEntry('1.jpg'));
        self::assertTrue($obj->isValid());
    }

    #[DataProvider('entryProvider')]
    public function testAddEntryNew(string $archiveName): void
    {
        $tempArchive = \tempnam($this->tmpDir, 'archive7z_').'_'.\str_replace('/', '_', $archiveName);

        $obj = new Archive7z($tempArchive);
        $obj->addEntry(__FILE__);
        $result = $obj->getEntry(\basename(__FILE__));
        self::assertInstanceOf(Entry::class, $result);
        self::assertEquals(\basename(__FILE__), $result->getPath());
        self::assertTrue($obj->isValid());
    }

    public function testAddEntryRar(): void
    {
        $tempArchive = \tempnam($this->tmpDir, 'archive7z_').'_archive.rar';

        $obj = new Archive7z($tempArchive);
        $this->expectException(ProcessFailedException::class);
        $obj->addEntry(__FILE__);
    }

    public function testAddEntryFullPathPasswd(): void
    {
        \copy($this->fixturesDir.'/test.txt', $this->tmpDir.'/file.txt');
        $tempArchive = \tempnam($this->tmpDir, 'archive7z_').'.7z';
        $fullPath = self::resolvePath($this->tmpDir.'/file.txt');

        $obj = new Archive7z($tempArchive, new Options(password: '111'));
        $obj->addEntry($fullPath);
        $result = $obj->getEntry('file.txt');
        self::assertInstanceOf(Entry::class, $result);
        self::assertEquals('file.txt', $result->getPath());

        $new = new Archive7z($tempArchive);
        $this->expectException(ProcessFailedException::class);
        $new->getContent('file.txt');
    }

    public function testAddEntryFullPath(): void
    {
        \copy($this->fixturesDir.'/test.txt', $this->tmpDir.'/file.txt');
        $tempArchive = \tempnam($this->tmpDir, 'archive7z_').'.7z';
        $fullPath = self::resolvePath($this->tmpDir.'/file.txt');

        $obj = new Archive7z($tempArchive);
        $obj->addEntry($fullPath);
        $result = $obj->getEntry('file.txt');
        self::assertInstanceOf(Entry::class, $result);
        self::assertEquals('file.txt', $result->getPath());
    }

    public function testAddEntryFullPathStore(): void
    {
        \copy($this->fixturesDir.'/test.txt', $this->tmpDir.'/file.txt');
        $fullPath = self::resolvePath($this->tmpDir.'/file.txt');

        $obj = new Archive7z($this->tmpDir.'/test.7z');
        $obj->addEntry($fullPath, true);
        $result = $obj->getEntry($fullPath);
        self::assertInstanceOf(Entry::class, $result);
        self::assertEquals($fullPath, $result->getPath());
    }

    public function testAddEntryLocalPath(): void
    {
        \copy($this->fixturesDir.'/test.txt', $this->tmpDir.'/test.txt');
        $localPath = self::resolvePath($this->tmpDir.'/test.txt');

        $obj = new Archive7z($this->tmpDir.'/test.7z');
        $obj->addEntry($localPath, true);
        $result = $obj->getEntry($localPath);

        self::assertInstanceOf(Entry::class, $result);
        self::assertEquals($localPath, $result->getPath());
    }

    public function testAddEntryLocalPathSubFiles(): void
    {
        if (!\mkdir($this->tmpDir.'/test')) {
            self::markTestIncomplete('Cant create directory.');
        }

        \copy($this->fixturesDir.'/test.txt', $this->tmpDir.'/test/test.txt');
        $localPath = self::resolvePath($this->tmpDir);

        $obj = new Archive7z($this->tmpDir.'/test.7z');
        $obj->addEntry($localPath, true);
        $result = $obj->getEntry($localPath);
        self::assertInstanceOf(Entry::class, $result);
        self::assertEquals($localPath, $result->getPath());
    }

    /**
     * @return string[][]
     */
    public static function delProvider(): array
    {
        return [
            // ['zip/windows/7zip-26.04/misnamed.7z'], // 7-Zip 21.02+ swears now at this
            // ['zip/windows/7zip-26.04/warnings.zip'], // not supported
            ['7z/windows/7zip-18.05/sample.7z'],
            ['tar/windows/7zip-18.05/sample.tar'],
            ['wim/windows/7zip-18.05/sample.wim'],
            ['zip/windows/7zip-18.05/sample.zip'],
            // ['gzip/windows/7zip-18.05/sample.tgz'],
            ['tar/windows/totalcommander-9.21a/sample.tar'],
            ['zip/windows/totalcommander-9.21a/sample.zip'],
            ['zip/windows/winrar-5.61/sample.zip'],
            // ['rar/windows/winrar-5.61/sample-rar4.rar'], // not supported
            // ['rar/windows/winrar-5.61/sample-rar5.rar'], // not supported
            ['zip/linux/zip-0.3/sample.zip'],
            ['7z/linux/p7zip-16.02/sample.7z'],
            ['7z/linux/7zip-26.04/sample.7z'],
            ['tar/linux/p7zip-16.02/sample.tar'],
            ['zip/linux/p7zip-16.02/sample.zip'],
        ];
    }

    #[DataProvider('delProvider')]
    public function testDelEntry(string $fixtureArchiveName): void
    {
        \copy($this->fixturesDir.'/'.$fixtureArchiveName, $this->tmpDir.'/'.\str_replace('/', '_', $fixtureArchiveName));
        $obj = new Archive7z($this->tmpDir.'/'.\str_replace('/', '_', $fixtureArchiveName));

        self::assertInstanceOf(Entry::class, $obj->getEntry('test/test.txt'));

        $obj->delEntry('test/test.txt');
        self::assertNull($obj->getEntry('test/test.txt'));
    }

    /**
     * @return string[][]
     */
    public static function delPasswdProvider(): array
    {
        return [
            ['7z/windows/7zip-26.04/encrypted.7z'],
            ['zip/windows/7zip-26.04/encrypted.zip'],
        ];
    }

    #[DataProvider('delPasswdProvider')]
    public function testDelEntryPasswd(string $fixtureArchiveName): void
    {
        \copy($this->fixturesDir.'/'.$fixtureArchiveName, $this->tmpDir.'/'.\str_replace('/', '_', $fixtureArchiveName));
        $obj = new Archive7z($this->tmpDir.'/'.\str_replace('/', '_', $fixtureArchiveName), new Options(password: '123'));

        self::assertInstanceOf(Entry::class, $obj->getEntry('test/test.txt'));

        $obj->delEntry('test/test.txt');
        self::assertNull($obj->getEntry('test/test.txt'));
    }

    #[DataProvider('delPasswdProvider')]
    public function testDelEntryPasswdFail(string $fixtureArchiveName): void
    {
        \copy($this->fixturesDir.'/'.$fixtureArchiveName, $this->tmpDir.'/'.\str_replace('/', '_', $fixtureArchiveName));
        $obj = new Archive7z($this->tmpDir.'/'.\str_replace('/', '_', $fixtureArchiveName));

        // zip allows deleting files from encrypted archives
        if ('zip' === \pathinfo($fixtureArchiveName, \PATHINFO_EXTENSION)) {
            $obj->delEntry('test/test.txt');
            self::assertNull($obj->getEntry('test/test.txt'));

            return;
        }

        $this->expectException(ProcessFailedException::class);
        $obj->delEntry('test/test.txt');
    }

    #[DataProvider('delPasswdProvider')]
    public function testRenameEntryPasswd(string $fixtureArchiveName): void
    {
        \copy($this->fixturesDir.'/'.$fixtureArchiveName, $this->tmpDir.'/'.\str_replace('/', '_', $fixtureArchiveName));
        $obj = new Archive7z($this->tmpDir.'/'.\str_replace('/', '_', $fixtureArchiveName), new Options(password: '123'));

        $resultSrc = $obj->getEntry('test/test.txt');
        self::assertInstanceOf(Entry::class, $resultSrc);
        $resultDest = $obj->getEntry('test/newTest.txt');
        self::assertNull($resultDest);

        $obj->renameEntry('test/test.txt', 'test/newTest.txt');

        $resultSrc = $obj->getEntry('test/test.txt');
        self::assertNull($resultSrc);
        $resultDest = $obj->getEntry('test/newTest.txt');
        self::assertInstanceOf(Entry::class, $resultDest);
    }

    #[DataProvider('basicProvider')]
    public function testIsValid(string $archiveName): void
    {
        $valid = new Archive7z($this->fixturesDir.'/'.$archiveName);
        self::assertTrue($valid->isValid());
    }

    public function testIsValidNotArchiveReturnsFalse(): void
    {
        $archive = $this->tmpDir.'/not-an-archive.txt';
        \file_put_contents($archive, 'this is not an archive');

        $obj = new Archive7z($archive);

        self::assertFalse($obj->isValid());
    }

    public function testIsValidCorruptedArchiveReturnsFalse(): void
    {
        $archive = $this->tmpDir.'/truncated.zip';
        $data = (string) \file_get_contents($this->fixturesDir.'/zip/windows/7zip-18.05/sample.zip');
        \file_put_contents($archive, \substr($data, 0, \strlen($data) - 10));

        $obj = new Archive7z($archive);

        self::assertFalse($obj->isValid());
    }

    public function testIsValidMissingFileThrows(): void
    {
        $obj = new Archive7z($this->tmpDir.'/missing.7z');

        $this->expectException(RuntimeException::class);

        $obj->isValid();
    }

    public function testProcessFailureImplementsExceptionInterface(): void
    {
        $archive = $this->tmpDir.'/not-an-archive.txt';
        \file_put_contents($archive, 'this is not an archive');

        $obj = new Archive7z($archive);

        try {
            $obj->getEntries();
            self::fail('Expected a process failure.');
        } catch (ProcessFailedException $e) {
            self::assertInstanceOf(ExceptionInterface::class, $e);
            self::assertSame(2, $e->getProcess()->getExitCode());
        }
    }

    public function testGetWarnings(): void
    {
        $obj = new Archive7z($this->fixturesDir.'/zip/windows/7zip-26.04/warnings.zip');
        $warnings = $obj->getWarnings();

        self::assertCount(1, $warnings);
        self::assertSame('There are data after the end of archive', $warnings[0]);

        $obj = new Archive7z($this->fixturesDir.'/zip/windows/7zip-26.04/misnamed.7z');
        $noWarnings = $obj->getWarnings();
        self::assertEmpty($noWarnings);
    }

    public function testRenameNonUtf8Entry(): void
    {
        $archive = $this->tmpDir.'/rename.zip';
        \copy($this->fixturesDir.'/zip/windows/7zip-18.05/sample.zip', $archive);

        $obj = new Archive7z($archive, new Options(fallbackEncoding: FallbackEncoding::codepage('CP866')));
        $obj->renameEntry('чавес.jpg', 'renamed.jpg');

        $paths = [];
        foreach ($obj->getEntries() as $entry) {
            $paths[] = $entry->getUnixPath();
        }

        self::assertContains('renamed.jpg', $paths);
        self::assertNotContains('чавес.jpg', $paths);
    }

    public function testMacosUnicodeNames(): void
    {
        foreach (['tar/macos/bsdtar/unicode.tar', 'zip/macos/apple-archive/unicode.zip'] as $archiveName) {
            $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);

            $paths = [];
            foreach ($obj->getEntries() as $entry) {
                $paths[] = $entry->getUnixPath();
            }

            self::assertContains('café.txt', $paths, $archiveName);
            self::assertContains('й.txt', $paths, $archiveName);
            self::assertContains('чавес.jpg', $paths, $archiveName);

            $hasSidecar = false;
            foreach ($paths as $path) {
                if (\str_starts_with($path, '__MACOSX/') || \str_starts_with($path, '._')) {
                    $hasSidecar = true;
                    break;
                }
            }
            self::assertTrue($hasSidecar, $archiveName);
        }
    }

    public function testPaxLongNameWithFallbackEncoding(): void
    {
        $obj = new Archive7z(
            $this->fixturesDir.'/tar/linux/bsdtar-3.6.2/features.tar',
            new Options(fallbackEncoding: FallbackEncoding::codepage('UTF-8')),
        );

        $longNames = [];
        foreach ($obj->getEntries() as $entry) {
            $path = (string) $entry->getUnixPath();
            if (\str_starts_with($path, 'long-')) {
                $longNames[] = $path;
            }
        }

        self::assertCount(1, $longNames);
        self::assertGreaterThan(100, \strlen($longNames[0]));
    }

    /**
     * @return string[][]
     */
    public static function extractPasswdEncFilesProvider(): array
    {
        return [
            ['7z/windows/7zip-26.04/encrypted-header.7z'],
        ];
    }

    #[DataProvider('extractPasswdEncFilesProvider')]
    public function testExtractPasswdEncFiles(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName, new Options(password: 'abc123'));
        $obj->extract($this->tmpDir);

        self::assertFileExists($this->tmpDir.'/file.txt');
        self::assertFileExists($this->tmpDir.'/file1.txt');
    }

    #[DataProvider('extractPasswdEncFilesProvider')]
    public function testGetEntriesPasswdEncFiles(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName, new Options(password: 'abc123'));
        $result = $obj->getEntries();

        self::assertCount(2, $result); // 2 files
        self::assertInstanceOf(Entry::class, $result[0]);
    }

    #[DataProvider('extractPasswdEncFilesProvider')]
    public function testCantGetEntriesPasswdEncFiles(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);

        try {
            $obj->getEntries();
            self::fail(\sprintf("Expected '%s' Exception.", ProcessFailedException::class));
        } catch (ProcessFailedException $e) {
            self::assertInstanceOf(ProcessFailedException::class, $e);
            self::assertMatchesRegularExpression('/Can\s*not open encrypted archive\. Wrong password\?/', $e->getMessage());
            self::assertEquals($e->getProcess()->getExitCode(), 2);
        }
    }

    public function testAddEntryPasswdEncFiles(): void
    {
        \copy($this->fixturesDir.'/test.txt', $this->tmpDir.'/file.txt');
        $tempArchive = \tempnam($this->tmpDir, 'archive7z_').'.7z';
        $fullPath = self::resolvePath($this->tmpDir.'/file.txt');

        $obj = new Archive7z($tempArchive, new Options(password: 'abc123', encryptFilenames: true));
        $obj->addEntry($fullPath);

        $result = $obj->getEntry('file.txt');
        self::assertInstanceOf(Entry::class, $result);
        self::assertEquals('file.txt', $result->getPath());

        $new = new Archive7z($tempArchive);
        $this->expectException(ProcessFailedException::class);
        $this->expectExceptionMessageMatches('/Can\s*not open encrypted archive\. Wrong password\?/');
        $new->getEntry('file.txt');
    }

    #[DataProvider('extractPasswdEncFilesProvider')]
    public function testIsValidPasswdEncFiles(string $archiveName): void
    {
        $valid = new Archive7z($this->fixturesDir.'/'.$archiveName, new Options(password: 'abc123'));

        self::assertTrue($valid->isValid());
    }

    #[DataProvider('extractPasswdEncFilesProvider')]
    public function testIsValidEncryptedWithoutPasswordReturnsFalse(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);

        self::assertFalse($obj->isValid());
    }

    #[DataProvider('extractPasswdEncFilesProvider')]
    public function testDelEntryPasswdEncFiles(string $fixtureArchiveName): void
    {
        \copy($this->fixturesDir.'/'.$fixtureArchiveName, $this->tmpDir.'/'.\str_replace('/', '_', $fixtureArchiveName));
        $obj = new Archive7z($this->tmpDir.'/'.\str_replace('/', '_', $fixtureArchiveName), new Options(password: 'abc123'));

        self::assertInstanceOf(Entry::class, $obj->getEntry('file1.txt'));

        $obj->delEntry('file1.txt');
        self::assertNull($obj->getEntry('file1.txt'));
    }

    #[DataProvider('extractPasswdEncFilesProvider')]
    public function testDelEntryPasswdEncFilesFail(string $fixtureArchiveName): void
    {
        \copy($this->fixturesDir.'/'.$fixtureArchiveName, $this->tmpDir.'/'.\str_replace('/', '_', $fixtureArchiveName));
        $obj = new Archive7z($this->tmpDir.'/'.\str_replace('/', '_', $fixtureArchiveName));

        $this->expectException(ProcessFailedException::class);
        $this->expectExceptionMessageMatches('/Can\s*not open encrypted archive\. Wrong password\?/');

        $obj->delEntry('file1.txt');
    }

    public function testCreateSolidArchive(): void
    {
        \copy($this->fixturesDir.'/test.txt', $this->tmpDir.'/file1.txt');
        \copy($this->fixturesDir.'/testArchive.txt', $this->tmpDir.'/file2.txt');
        $tempArchive = \tempnam($this->tmpDir, 'archive7z_').'.7z';

        $solidMode = SolidMode::on();

        $obj = new Archive7z($tempArchive, new Options(solidMode: $solidMode));
        $obj->addEntry($this->tmpDir);

        self::assertTrue($obj->getInfo()->isSolid());
    }

    public function testCreateNonSolidArchive(): void
    {
        \copy($this->fixturesDir.'/test.txt', $this->tmpDir.'/file1.txt');
        \copy($this->fixturesDir.'/testArchive.txt', $this->tmpDir.'/file2.txt');
        $tempArchive = \tempnam($this->tmpDir, 'archive7z_').'.7z';

        $solidMode = SolidMode::off();

        $obj = new Archive7z($tempArchive, new Options(solidMode: $solidMode));
        $obj->addEntry($this->tmpDir);

        self::assertFalse($obj->getInfo()->isSolid());
    }

    public function testCreateLimitSolidArchive(): void
    {
        \copy($this->fixturesDir.'/test.txt', $this->tmpDir.'/file1.txt');
        \copy($this->fixturesDir.'/testArchive.txt', $this->tmpDir.'/file2.txt');
        $tempArchive = \tempnam($this->tmpDir, 'archive7z_').'.7z';

        $solidMode = SolidMode::limits(2, 1024);

        $obj = new Archive7z($tempArchive, new Options(solidMode: $solidMode));
        $obj->addEntry($this->tmpDir);

        self::assertTrue($obj->getInfo()->isSolid());
    }

    public function testCreateMixedSolidArchive(): void
    {
        \copy($this->fixturesDir.'/test.txt', $this->tmpDir.'/file1.txt');
        \copy($this->fixturesDir.'/testArchive.txt', $this->tmpDir.'/file2.txt');
        $tempArchive = \tempnam($this->tmpDir, 'archive7z_').'.7z';

        $solidMode = SolidMode::on();

        $obj = new Archive7z($tempArchive, new Options(solidMode: $solidMode));
        $obj->addEntry($this->tmpDir);

        \copy($this->fixturesDir.'/test.txt', $this->tmpDir.'/file3.txt');

        $obj->addEntry($this->tmpDir.'/file3.txt');

        self::assertTrue($obj->getInfo()->isSolid());
    }

    #[DataProvider('basicProvider')]
    public function testInfo(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);
        $info = $obj->getInfo();

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

    #[DataProvider('extractProvider')]
    public function testGetEntriesLimit(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);
        $entries = $obj->getEntries(null, 2);

        self::assertCount(2, $entries);
        self::assertInstanceOf(Entry::class, $entries[0]);
        self::assertInstanceOf(Entry::class, $entries[1]);
    }

    #[DataProvider('extractProvider')]
    public function testGetEntriesPathMask(string $archiveName): void
    {
        $path = 'test/test.txt';
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);
        $entries = $obj->getEntries($path);

        self::assertCount(1, $entries);
        self::assertInstanceOf(Entry::class, $entries[0]);
        self::assertSame($path, $entries[0]->getUnixPath());
    }

    #[DataProvider('utf8Provider')]
    public function testGetEntriesPathMaskNonAscii(string $archiveName): void
    {
        $path = 'чавес.jpg';
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);
        $entries = $obj->getEntries($path);

        self::assertCount(1, $entries);
        self::assertInstanceOf(Entry::class, $entries[0]);
        self::assertSame($path, $entries[0]->getUnixPath());
    }

    #[DataProvider('extractProvider')]
    public function testGetEntriesPathMaskWildcard(string $archiveName): void
    {
        $path = 'test';
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);
        $entries = $obj->getEntries($path);

        self::assertCount(3, $entries); // 1 folder + 2 files in the folder
    }

    public function testGetEntryExactNameWithWildcardChars(): void
    {
        if ('Windows' === \PHP_OS_FAMILY) {
            self::markTestSkipped('Windows file names cannot contain "*" or "?"');
        }

        \file_put_contents($this->tmpDir.'/qXname.txt', 'a');
        \file_put_contents($this->tmpDir.'/q?name.txt', 'b');

        $obj = new Archive7z($this->tmpDir.'/wild.7z');
        $obj->addEntry($this->tmpDir.'/qXname.txt');
        $obj->addEntry($this->tmpDir.'/q?name.txt');

        $entry = $obj->getEntry('q?name.txt');
        self::assertInstanceOf(Entry::class, $entry);
        self::assertSame('q?name.txt', $entry->getPath());
    }

    /**
     * @return string[][]
     */
    public static function featureProvider(): array
    {
        return [
            ['7z/windows/7zip-26.04/features.7z'],
            ['zip/windows/7zip-26.04/features.zip'],
            ['tar/linux/gnutar-1.34/features.tar'],
            ['tar/linux/bsdtar-3.6.2/features.tar'],
            ['zip/linux/bsdtar-3.6.2/features.zip'],
        ];
    }

    #[DataProvider('featureProvider')]
    public function testContentFeatures(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);

        $entries = [];
        foreach ($obj->getEntries() as $entry) {
            $entries[(string) $entry->getUnixPath()] = $entry;
        }

        self::assertArrayHasKey('name with spaces.txt', $entries);
        self::assertArrayHasKey('noextension', $entries);
        self::assertArrayHasKey('UPPER.TXT', $entries);
        self::assertArrayHasKey('zero.txt', $entries);
        self::assertSame('0', $entries['zero.txt']->getSize());
        self::assertArrayHasKey('nested/one/two/deep.txt', $entries);

        self::assertTrue($entries['empty']->isDirectory());
        self::assertTrue($entries['nested/one']->isDirectory());

        $longNames = [];
        foreach (\array_keys($entries) as $path) {
            if (\str_starts_with($path, 'long-')) {
                $longNames[] = $path;
            }
        }
        self::assertCount(1, $longNames);
        self::assertGreaterThan(100, \strlen((string) \current($longNames)));
    }

    public function testPartialRar(): void
    {
        $obj = new Archive7z($this->fixturesDir.'/rar/windows/winrar-7.0/multivolume.part1.rar');
        $entries = $obj->getEntries();

        self::assertCount(1, $entries);
    }
}
