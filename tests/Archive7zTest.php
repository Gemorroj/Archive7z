<?php

declare(strict_types=1);

namespace Archive7z\Tests;

use Archive7z\Archive7z;
use Archive7z\Config\ArchiveOptions;
use Archive7z\Config\FallbackEncoding;
use Archive7z\Config\SolidMode;
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

    public function testFallbackEncodingCodepage(): void
    {
        $obj = new Archive7z(
            $this->fixturesDir.'/windows/totalcommander-9.21a/sample.tar',
            new ArchiveOptions(fallbackEncoding: FallbackEncoding::codepage('Windows-1251')),
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
            $this->fixturesDir.'/windows/totalcommander-9.21a/sample.tar',
            new ArchiveOptions(fallbackEncoding: FallbackEncoding::detect()),
        );

        $paths = [];
        foreach ($obj->getEntries() as $entry) {
            $paths[] = $entry->getUnixPath();
        }

        self::assertContains('чавес.jpg', $paths);
    }

    public function testExtractRepairsLegacyNames(): void
    {
        $obj = new Archive7z(
            $this->fixturesDir.'/windows/totalcommander-9.21a/sample.tar',
            new ArchiveOptions(fallbackEncoding: FallbackEncoding::codepage('Windows-1251')),
        );
        $obj->extract($this->tmpDir);

        self::assertFileExists($this->tmpDir.'/чавес.jpg');
        self::assertFileExists($this->tmpDir.'/1.jpg');
        self::assertFileExists($this->tmpDir.'/test/test.txt');
    }

    public function testFallbackEncodingCodepageMask(): void
    {
        $obj = new Archive7z(
            $this->fixturesDir.'/windows/totalcommander-9.21a/sample.tar',
            new ArchiveOptions(fallbackEncoding: FallbackEncoding::codepage('Windows-1251')),
        );

        $entries = $obj->getEntries('чавес.jpg');

        self::assertCount(1, $entries);
        self::assertSame('чавес.jpg', $entries[0]->getUnixPath());
    }

    public function testFallbackEncodingCodepageExtractEntry(): void
    {
        $obj = new Archive7z(
            $this->fixturesDir.'/windows/totalcommander-9.21a/sample.tar',
            new ArchiveOptions(fallbackEncoding: FallbackEncoding::codepage('Windows-1251')),
        );
        $obj->extractEntry('чавес.jpg', $this->tmpDir);

        self::assertFileExists($this->tmpDir.'/чавес.jpg');
    }

    public function testFallbackEncodingCodepageGetContent(): void
    {
        $obj = new Archive7z(
            $this->fixturesDir.'/windows/totalcommander-9.21a/sample.tar',
            new ArchiveOptions(fallbackEncoding: FallbackEncoding::codepage('Windows-1251')),
        );

        $content = $obj->getContent('чавес.jpg');

        self::assertStringStartsWith("\xFF\xD8", $content);
    }

    /**
     * @return string[][]
     */
    public static function extractProvider(): array
    {
        return [
            ['windows/7zip-26.04/misnamed.7z'],
            ['windows/7zip-26.04/warnings.zip'],
            ['windows/7zip-18.05/sample.7z'],
            ['windows/7zip-18.05/sample.tar'],
            ['windows/7zip-18.05/sample.wim'],
            ['windows/7zip-18.05/sample.zip'],
            ['windows/totalcommander-9.21a/sample.tar'],
            ['windows/totalcommander-9.21a/sample.zip'],
            ['windows/winrar-5.61/sample.zip'],
            ['windows/winrar-5.61/sample-rar4.rar'],
            ['windows/winrar-5.61/sample-rar5.rar'],
            ['linux/zip-0.3/sample.zip'],
            ['linux/p7zip-16.02/sample.7z'],
            ['linux/p7zip-16.02/sample.tar'],
            ['linux/p7zip-16.02/sample.zip'],
            ['linux/gnutar-1.34/sample.tar'],
            ['linux/bsdtar-3.6.2/sample.tar'],
            ['linux/bsdtar-3.6.2/sample.zip'],
        ];
    }

    /**
     * @return string[][]
     */
    public static function basicProvider(): array
    {
        return [
            ['windows/7zip-26.04/misnamed.7z'],
            ['windows/7zip-26.04/warnings.zip'],
            ['windows/7zip-18.05/sample.7z'],
            ['windows/7zip-18.05/sample.tar'],
            ['windows/7zip-18.05/sample.wim'],
            ['windows/7zip-18.05/sample.zip'],
            ['windows/7zip-18.05/sample.tgz'],
            ['windows/totalcommander-9.21a/sample.tar'],
            ['windows/totalcommander-9.21a/sample.zip'],
            ['windows/winrar-5.61/sample.zip'],
            ['windows/winrar-5.61/sample-rar4.rar'],
            ['windows/winrar-5.61/sample-rar5.rar'],
            ['linux/zip-0.3/sample.zip'],
            ['linux/p7zip-16.02/sample.7z'],
            ['linux/p7zip-16.02/sample.tar'],
            ['linux/p7zip-16.02/sample.zip'],
            ['linux/gnutar-1.34/sample.tar'],
            ['linux/gnutar-1.34/sample.tar.gz'],
            ['linux/bsdtar-3.6.2/sample.tar'],
            ['linux/bsdtar-3.6.2/sample.zip'],
            ['macos/apple-archive/sample.zip'],
            ['macos/zip/sample.zip'],
            ['macos/bsdtar/sample.tar'],
        ];
    }

    #[DataProvider('extractProvider')]
    public function testExtractCyrillic(string $archiveName): void
    {
        $dirCyrillic = $this->tmpDir.'/папка';
        $chavezFile = 'чавес.jpg';

        if (!\mkdir($dirCyrillic)) {
            self::markTestIncomplete('Cant create cyrillic directory.');
        }

        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);
        $obj->extract($dirCyrillic);

        self::assertFileExists($dirCyrillic.'/1.jpg');
        self::assertFileExists($dirCyrillic.'/'.$chavezFile);
        self::assertFileExists($dirCyrillic.'/test/test.txt');
    }

    /**
     * @return string[][]
     */
    public static function extractPasswdProvider(): array
    {
        return [
            ['windows/7zip-26.04/encrypted.7z'],
            ['windows/7zip-26.04/encrypted.zip'],
            ['windows/winrar-3/encrypted-rar3.rar'],
            ['linux/rar-7.30/encrypted-rar5.rar'],
        ];
    }

    #[DataProvider('extractPasswdProvider')]
    public function testExtractPasswdCyrillic(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName, new ArchiveOptions(password: '123'));
        $obj->extract($this->tmpDir);

        $chavezFile = 'чавес.jpg';

        self::assertFileExists($this->tmpDir.'/1.jpg');
        self::assertFileExists($this->tmpDir.'/'.$chavezFile);
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

        $obj = new Archive7z($archivePath, new ArchiveOptions(overwriteMode: ArchiveOptions::OVERWRITE_MODE_A));
        \copy($sourceFile, $targetFile);
        $obj->extract($this->tmpDir);
        self::assertFileEquals($archiveFile, $targetFile, $archiveName);

        $obj = new Archive7z($archivePath, new ArchiveOptions(overwriteMode: ArchiveOptions::OVERWRITE_MODE_S));
        \copy($sourceFile, $targetFile);
        $obj->extract($this->tmpDir);
        self::assertFileEquals($sourceFile, $targetFile);

        $obj = new Archive7z($archivePath, new ArchiveOptions(overwriteMode: ArchiveOptions::OVERWRITE_MODE_T));
        \copy($sourceFile, $targetFile);
        $obj->extract($this->tmpDir);
        self::assertFileExists($this->tmpDir.'/test/test_1.txt');
        self::assertFileEquals($archiveFile, $targetFile);
        self::assertFileEquals($sourceFile, $this->tmpDir.'/test/test_1.txt');
        \unlink($this->tmpDir.'/test/test_1.txt');

        $obj = new Archive7z($archivePath, new ArchiveOptions(overwriteMode: ArchiveOptions::OVERWRITE_MODE_U));
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

        $obj = new Archive7z($archivePath, new ArchiveOptions(overwriteMode: ArchiveOptions::OVERWRITE_MODE_A));
        \copy($sourceFile, $targetFile);
        $obj->extractEntry('test/test.txt', $this->tmpDir);
        self::assertFileEquals($archiveFile, $targetFile);

        $obj = new Archive7z($archivePath, new ArchiveOptions(overwriteMode: ArchiveOptions::OVERWRITE_MODE_S));
        \copy($sourceFile, $targetFile);
        $obj->extractEntry('test/test.txt', $this->tmpDir);
        self::assertFileEquals($sourceFile, $targetFile);

        $obj = new Archive7z($archivePath, new ArchiveOptions(overwriteMode: ArchiveOptions::OVERWRITE_MODE_T));
        \copy($sourceFile, $targetFile);
        $obj->extractEntry('test/test.txt', $this->tmpDir);
        self::assertFileExists($this->tmpDir.'/test/test_1.txt');
        self::assertFileEquals($archiveFile, $targetFile);
        self::assertFileEquals($sourceFile, $this->tmpDir.'/test/test_1.txt');
        \unlink($this->tmpDir.'/test/test_1.txt');

        $obj = new Archive7z($archivePath, new ArchiveOptions(overwriteMode: ArchiveOptions::OVERWRITE_MODE_U));
        \copy($sourceFile, $targetFile);
        $obj->extractEntry('test/test.txt', $this->tmpDir);
        self::assertFileExists($this->tmpDir.'/test/test_1.txt');
        self::assertFileEquals($sourceFile, $targetFile);
        self::assertFileEquals($archiveFile, $this->tmpDir.'/test/test_1.txt');
        \unlink($this->tmpDir.'/test/test_1.txt');
    }

    #[DataProvider('extractProvider')]
    public function testExtractEntryCyrillic(string $archiveName): void
    {
        $file = 'чавес.jpg';

        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);
        $obj->extractEntry($file, $this->tmpDir);

        self::assertFileExists($this->tmpDir.'/'.$file);
    }

    #[DataProvider('extractPasswdProvider')]
    public function testExtractEntryPasswd(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName, new ArchiveOptions(password: '123'));
        $obj->extractEntry('1.jpg', $this->tmpDir);

        self::assertFileExists($this->tmpDir.'/1.jpg');
    }

    #[DataProvider('extractPasswdProvider')]
    public function testGetContentPasswd(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName, new ArchiveOptions(password: '123'));
        $result = $obj->getContent('test/test.txt');

        self::assertStringEqualsFile($this->fixturesDir.'/testArchive.txt', $result);
    }

    #[DataProvider('extractPasswdProvider')]
    public function testGetEntriesPasswd(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName, new ArchiveOptions(password: '123'));
        $result = $obj->getEntries();

        self::assertCount(5, $result); // 4 file + 1 directory
        self::assertInstanceOf(Entry::class, $result[0]);
    }

    #[DataProvider('extractPasswdProvider')]
    public function testGetEntryPasswd(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName, new ArchiveOptions(password: '123'));
        $result = $obj->getEntry('test/test.txt');

        self::assertInstanceOf(Entry::class, $result);
    }

    /**
     * @return string[][]
     */
    public static function entryProvider(): array
    {
        return [
            ['windows/7zip-26.04/misnamed.7z'],
            ['windows/7zip-26.04/warnings.zip'],
            ['windows/7zip-18.05/sample.7z'],
            ['windows/7zip-18.05/sample.tar'],
            ['windows/7zip-18.05/sample.wim'],
            ['windows/7zip-18.05/sample.zip'],
            ['windows/7zip-18.05/sample.tgz'],
            ['windows/totalcommander-9.21a/sample.tar'],
            ['windows/totalcommander-9.21a/sample.zip'],
            ['windows/winrar-5.61/sample.zip'],
            // ['windows/winrar-5.61/sample-rar4.rar'], // not supported
            // ['windows/winrar-5.61/sample-rar5.rar'], // not supported
            ['linux/zip-0.3/sample.zip'],
            ['linux/p7zip-16.02/sample.7z'],
            ['linux/p7zip-16.02/sample.tar'],
            ['linux/p7zip-16.02/sample.zip'],
        ];
    }

    #[DataProvider('entryProvider')]
    public function testAddEntryExists(string $archiveName): void
    {
        \copy($this->fixturesDir.'/'.$archiveName, $this->tmpDir.'/'.\str_replace('/', '_', $archiveName));
        $tempArchive = \tempnam($this->tmpDir, 'archive7z_').'_'.\str_replace('/', '_', $archiveName);

        $obj = new Archive7z($tempArchive);
        $obj->addEntry(__FILE__);
        $result = $obj->getEntry(\basename(__FILE__));
        self::assertInstanceOf(Entry::class, $result);
        self::assertEquals(\basename(__FILE__), $result->getPath());
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

        $obj = new Archive7z($tempArchive, new ArchiveOptions(password: '111'));
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
            // ['windows/7zip-26.04/misnamed.7z'], // 7-Zip 21.02+ swears now at this
            // ['windows/7zip-26.04/warnings.zip'], // not supported
            ['windows/7zip-18.05/sample.7z'],
            ['windows/7zip-18.05/sample.tar'],
            ['windows/7zip-18.05/sample.wim'],
            ['windows/7zip-18.05/sample.zip'],
            // ['windows/7zip-18.05/sample.tgz'],
            ['windows/totalcommander-9.21a/sample.tar'],
            ['windows/totalcommander-9.21a/sample.zip'],
            ['windows/winrar-5.61/sample.zip'],
            // ['windows/winrar-5.61/sample-rar4.rar'], // not supported
            // ['windows/winrar-5.61/sample-rar5.rar'], // not supported
            ['linux/zip-0.3/sample.zip'],
            ['linux/p7zip-16.02/sample.7z'],
            ['linux/p7zip-16.02/sample.tar'],
            ['linux/p7zip-16.02/sample.zip'],
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
            ['windows/7zip-26.04/encrypted.7z'],
            ['windows/7zip-26.04/encrypted.zip'],
        ];
    }

    #[DataProvider('delPasswdProvider')]
    public function testDelEntryPasswd(string $fixtureArchiveName): void
    {
        \copy($this->fixturesDir.'/'.$fixtureArchiveName, $this->tmpDir.'/'.\str_replace('/', '_', $fixtureArchiveName));
        $obj = new Archive7z($this->tmpDir.'/'.\str_replace('/', '_', $fixtureArchiveName), new ArchiveOptions(password: '123'));

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
        $obj = new Archive7z($this->tmpDir.'/'.\str_replace('/', '_', $fixtureArchiveName), new ArchiveOptions(password: '123'));

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

    public function testGetWarnings(): void
    {
        $obj = new Archive7z($this->fixturesDir.'/windows/7zip-26.04/warnings.zip');
        $warnings = $obj->getWarnings();

        self::assertCount(1, $warnings);
        self::assertSame('There are data after the end of archive', $warnings[0]);

        $obj = new Archive7z($this->fixturesDir.'/windows/7zip-26.04/misnamed.7z');
        $noWarnings = $obj->getWarnings();
        self::assertEmpty($noWarnings);
    }

    /**
     * @return string[][]
     */
    public static function extractPasswdEncFilesProvider(): array
    {
        return [
            ['windows/7zip-26.04/encrypted-header.7z'],
        ];
    }

    #[DataProvider('extractPasswdEncFilesProvider')]
    public function testExtractPasswdEncFiles(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName, new ArchiveOptions(password: 'abc123'));
        $obj->extract($this->tmpDir);

        self::assertFileExists($this->tmpDir.'/file.txt');
        self::assertFileExists($this->tmpDir.'/file1.txt');
    }

    #[DataProvider('extractPasswdEncFilesProvider')]
    public function testGetEntriesPasswdEncFiles(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName, new ArchiveOptions(password: 'abc123'));
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

        $obj = new Archive7z($tempArchive, new ArchiveOptions(password: 'abc123', encryptFilenames: true));
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
        $valid = new Archive7z($this->fixturesDir.'/'.$archiveName, new ArchiveOptions(password: 'abc123'));

        self::assertTrue($valid->isValid());
    }

    #[DataProvider('extractPasswdEncFilesProvider')]
    public function testDelEntryPasswdEncFiles(string $fixtureArchiveName): void
    {
        \copy($this->fixturesDir.'/'.$fixtureArchiveName, $this->tmpDir.'/'.\str_replace('/', '_', $fixtureArchiveName));
        $obj = new Archive7z($this->tmpDir.'/'.\str_replace('/', '_', $fixtureArchiveName), new ArchiveOptions(password: 'abc123'));

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

        $obj = new Archive7z($tempArchive, new ArchiveOptions(solidMode: $solidMode));
        $obj->addEntry($this->tmpDir);

        self::assertTrue($obj->getInfo()->isSolid());
    }

    public function testCreateNonSolidArchive(): void
    {
        \copy($this->fixturesDir.'/test.txt', $this->tmpDir.'/file1.txt');
        \copy($this->fixturesDir.'/testArchive.txt', $this->tmpDir.'/file2.txt');
        $tempArchive = \tempnam($this->tmpDir, 'archive7z_').'.7z';

        $solidMode = SolidMode::off();

        $obj = new Archive7z($tempArchive, new ArchiveOptions(solidMode: $solidMode));
        $obj->addEntry($this->tmpDir);

        self::assertFalse($obj->getInfo()->isSolid());
    }

    public function testCreateLimitSolidArchive(): void
    {
        \copy($this->fixturesDir.'/test.txt', $this->tmpDir.'/file1.txt');
        \copy($this->fixturesDir.'/testArchive.txt', $this->tmpDir.'/file2.txt');
        $tempArchive = \tempnam($this->tmpDir, 'archive7z_').'.7z';

        $solidMode = SolidMode::limits(2, 1024);

        $obj = new Archive7z($tempArchive, new ArchiveOptions(solidMode: $solidMode));
        $obj->addEntry($this->tmpDir);

        self::assertTrue($obj->getInfo()->isSolid());
    }

    public function testCreateMixedSolidArchive(): void
    {
        \copy($this->fixturesDir.'/test.txt', $this->tmpDir.'/file1.txt');
        \copy($this->fixturesDir.'/testArchive.txt', $this->tmpDir.'/file2.txt');
        $tempArchive = \tempnam($this->tmpDir, 'archive7z_').'.7z';

        $solidMode = SolidMode::on();

        $obj = new Archive7z($tempArchive, new ArchiveOptions(solidMode: $solidMode));
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

        // p7zip: 7-Zip [64] 17.04 : Copyright (c) 1999-2021 Igor Pavlov : 2017-08-28
        // 7-zip: 7-Zip 23.00 (x64) : Copyright (c) 1999-2023 Igor Pavlov : 2023-05-07
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

    #[DataProvider('extractProvider')]
    public function testGetEntriesPathMaskCyrillic(string $archiveName): void
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

    /**
     * @return string[][]
     */
    public static function featureProvider(): array
    {
        return [
            ['windows/7zip-26.04/features.7z'],
            ['windows/7zip-26.04/features.zip'],
            ['linux/gnutar-1.34/features.tar'],
            ['linux/bsdtar-3.6.2/features.tar'],
            ['linux/bsdtar-3.6.2/features.zip'],
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
        $obj = new Archive7z($this->fixturesDir.'/windows/winrar-7.0/multivolume.part1.rar');
        $entries = $obj->getEntries();

        self::assertCount(1, $entries);
    }
}
