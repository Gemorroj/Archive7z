<?php

namespace Archive7z\Tests;

use Archive7z\Archive7z;
use Archive7z\Entry;
use Archive7z\Exception;
use Archive7z\SolidMode;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class Archive7zTest extends TestCase
{
    private string $tmpDir;
    private string $fixturesDir;
    private Archive7z $mock;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__.'/fixtures';

        $this->tmpDir = \sys_get_temp_dir().'/'.\uniqid('Archive7z-', false);
        \mkdir($this->tmpDir);

        $this->mock = new Archive7z('fake.7z');
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

    public function testSetGetOutputDirectory(): void
    {
        $this->mock->setOutputDirectory($this->tmpDir);
        self::assertEquals(\realpath($this->tmpDir), $this->mock->getOutputDirectory());
    }

    public function testSetGetOutputDirectoryFail(): void
    {
        $outputDirectory = '/fake_path/test';
        $this->expectException(Exception::class);
        $this->mock->setOutputDirectory($outputDirectory);
    }

    public function testSetGetPassword(): void
    {
        $password = 'passw';
        $this->mock->setPassword($password);
        self::assertEquals($password, $this->mock->getPassword());
    }

    public function testSetGetEncryptFilenames(): void
    {
        $defaultValue = false;
        self::assertEquals($defaultValue, $this->mock->getEncryptFilenames());
        $encryptFilenames = true;
        $this->mock->setEncryptFilenames($encryptFilenames);
        self::assertEquals($encryptFilenames, $this->mock->getEncryptFilenames());
    }

    public function testSetGetOverwriteMode(): void
    {
        $this->mock->setOverwriteMode(Archive7z::OVERWRITE_MODE_U);
        self::assertEquals(Archive7z::OVERWRITE_MODE_U, $this->mock->getOverwriteMode());
    }

    /**
     * @return string[][]
     */
    public function extractProvider(): array
    {
        return [
            ['zip.7z'],
            ['warnings.zip'],
            ['7zip-18.05/test.7z'],
            ['7zip-18.05/test.tar'],
            ['7zip-18.05/test.wim'],
            ['7zip-18.05/test.zip'],
            ['totalcommander-9.21a/test.tar'],
            ['totalcommander-9.21a/test.zip'],
            ['winrar-5.61/test.zip'],
            ['winrar-5.61/test4.rar'],
            ['winrar-5.61/test5.rar'],
            ['linux/zip-0.3/test.zip'],
            ['linux/p7zip-16.02/test.7z'],
            ['linux/p7zip-16.02/test.tar'],
            ['linux/p7zip-16.02/test.zip'],
        ];
    }

    /**
     * @return string[][]
     */
    public function basicProvider(): array
    {
        return [
            ['zip.7z'],
            ['warnings.zip'],
            ['7zip-18.05/test.7z'],
            ['7zip-18.05/test.tar'],
            ['7zip-18.05/test.wim'],
            ['7zip-18.05/test.zip'],
            ['7zip-18.05/test.tgz'],
            ['totalcommander-9.21a/test.tar'],
            ['totalcommander-9.21a/test.zip'],
            ['winrar-5.61/test.zip'],
            ['winrar-5.61/test4.rar'],
            ['winrar-5.61/test5.rar'],
            ['linux/zip-0.3/test.zip'],
            ['linux/p7zip-16.02/test.7z'],
            ['linux/p7zip-16.02/test.tar'],
            ['linux/p7zip-16.02/test.zip'],
        ];
    }

    /**
     * @dataProvider extractProvider
     */
    public function testExtractCyrillic(string $archiveName): void
    {
        $dirCyrillic = $this->tmpDir.'/папка';
        $chavezFile = 'чавес.jpg';

        if (!\mkdir($dirCyrillic)) {
            self::markTestIncomplete('Cant create cyrillic directory.');
        }

        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);
        $obj->setOutputDirectory($dirCyrillic);
        $obj->extract();

        self::assertFileExists($dirCyrillic.'/1.jpg');
        self::assertFileExists($dirCyrillic.'/'.$chavezFile);
        self::assertFileExists($dirCyrillic.'/test/test.txt');
    }

    /**
     * @return string[][]
     */
    public function extractPasswdProvider(): array
    {
        return [
            ['testPasswd.7z'],
            ['testPasswd.zip'],
            ['testPasswd.rar'],
        ];
    }

    /**
     * @dataProvider extractPasswdProvider
     */
    public function testExtractPasswdCyrillic(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);
        $obj->setOutputDirectory($this->tmpDir);
        $obj->setPassword('123');
        $obj->extract();

        $chavezFile = 'чавес.jpg';

        self::assertFileExists($this->tmpDir.'/1.jpg');
        self::assertFileExists($this->tmpDir.'/'.$chavezFile);
        self::assertFileExists($this->tmpDir.'/test/test.txt');
    }

    /**
     * @dataProvider extractProvider
     */
    public function testExtractOverwrite(string $archiveName): void
    {
        if (!\mkdir($this->tmpDir.'/test')) {
            self::markTestIncomplete('Cant create directory.');
        }

        $sourceFile = $this->fixturesDir.'/test.txt';
        $targetFile = $this->tmpDir.'/test/test.txt';
        $archiveFile = $this->fixturesDir.'/testArchive.txt';

        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);
        $obj->setOutputDirectory($this->tmpDir);

        $obj->setOverwriteMode(Archive7z::OVERWRITE_MODE_A);
        \copy($sourceFile, $targetFile);
        $obj->extract();
        self::assertFileEquals($archiveFile, $targetFile, $archiveName);

        $obj->setOverwriteMode(Archive7z::OVERWRITE_MODE_S);
        \copy($sourceFile, $targetFile);
        $obj->extract();
        self::assertFileEquals($sourceFile, $targetFile);

        $obj->setOverwriteMode(Archive7z::OVERWRITE_MODE_T);
        \copy($sourceFile, $targetFile);
        $obj->extract();
        self::assertFileExists($this->tmpDir.'/test/test_1.txt');
        self::assertFileEquals($archiveFile, $targetFile);
        self::assertFileEquals($sourceFile, $this->tmpDir.'/test/test_1.txt');
        \unlink($this->tmpDir.'/test/test_1.txt');

        $obj->setOverwriteMode(Archive7z::OVERWRITE_MODE_U);
        \copy($sourceFile, $targetFile);
        $obj->extract();
        self::assertFileExists($this->tmpDir.'/test/test_1.txt');
        self::assertFileEquals($sourceFile, $targetFile);
        self::assertFileEquals($archiveFile, $this->tmpDir.'/test/test_1.txt');
        \unlink($this->tmpDir.'/test/test_1.txt');
    }

    /**
     * @dataProvider extractProvider
     */
    public function testExtractEntry(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);
        $obj->setOutputDirectory($this->tmpDir);
        $obj->extractEntry('test/2.jpg');
        self::assertFileExists($this->tmpDir.'/test/2.jpg');
    }

    /**
     * @dataProvider extractProvider
     */
    public function testExtractEntryOverwrite(string $archiveName): void
    {
        if (!\mkdir($this->tmpDir.'/test')) {
            self::markTestIncomplete('Cant create directory.');
        }

        $sourceFile = $this->fixturesDir.'/test.txt';
        $targetFile = $this->tmpDir.'/test/test.txt';
        $archiveFile = $this->fixturesDir.'/testArchive.txt';

        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);
        $obj->setOutputDirectory($this->tmpDir);

        $obj->setOverwriteMode(Archive7z::OVERWRITE_MODE_A);
        \copy($sourceFile, $targetFile);
        $obj->extractEntry('test/test.txt');
        self::assertFileEquals($archiveFile, $targetFile);

        $obj->setOverwriteMode(Archive7z::OVERWRITE_MODE_S);
        \copy($sourceFile, $targetFile);
        $obj->extractEntry('test/test.txt');
        self::assertFileEquals($sourceFile, $targetFile);

        $obj->setOverwriteMode(Archive7z::OVERWRITE_MODE_T);
        \copy($sourceFile, $targetFile);
        $obj->extractEntry('test/test.txt');
        self::assertFileExists($this->tmpDir.'/test/test_1.txt');
        self::assertFileEquals($archiveFile, $targetFile);
        self::assertFileEquals($sourceFile, $this->tmpDir.'/test/test_1.txt');
        \unlink($this->tmpDir.'/test/test_1.txt');

        $obj->setOverwriteMode(Archive7z::OVERWRITE_MODE_U);
        \copy($sourceFile, $targetFile);
        $obj->extractEntry('test/test.txt');
        self::assertFileExists($this->tmpDir.'/test/test_1.txt');
        self::assertFileEquals($sourceFile, $targetFile);
        self::assertFileEquals($archiveFile, $this->tmpDir.'/test/test_1.txt');
        \unlink($this->tmpDir.'/test/test_1.txt');
    }

    /**
     * @dataProvider extractProvider
     */
    public function testExtractEntryCyrillic(string $archiveName): void
    {
        $file = 'чавес.jpg';

        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);
        $obj->setOutputDirectory($this->tmpDir);
        $obj->extractEntry($file);

        self::assertFileExists($this->tmpDir.'/'.$file);
    }

    /**
     * @dataProvider extractPasswdProvider
     */
    public function testExtractEntryPasswd(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);
        $obj->setOutputDirectory($this->tmpDir);
        $obj->setPassword('123');
        $obj->extractEntry('1.jpg');
    }

    /**
     * @dataProvider extractPasswdProvider
     */
    public function testGetContentPasswd(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);
        $obj->setPassword('123');
        $result = $obj->getContent('test/test.txt');

        self::assertStringEqualsFile($this->fixturesDir.'/testArchive.txt', $result);
    }

    /**
     * @dataProvider extractPasswdProvider
     */
    public function testGetEntriesPasswd(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);
        $obj->setPassword('123');
        $result = $obj->getEntries();

        self::assertIsArray($result);
        self::assertCount(5, $result); // 4 file + 1 directory
        self::assertInstanceOf(Entry::class, $result[0]);
    }

    /**
     * @dataProvider extractPasswdProvider
     */
    public function testGetEntryPasswd(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);
        $obj->setPassword('123');
        $result = $obj->getEntry('test/test.txt');

        self::assertInstanceOf(Entry::class, $result);
    }

    /**
     * @return string[][]
     */
    public function entryProvider(): array
    {
        return [
            ['zip.7z'],
            ['warnings.zip'],
            ['7zip-18.05/test.7z'],
            ['7zip-18.05/test.tar'],
            ['7zip-18.05/test.wim'],
            ['7zip-18.05/test.zip'],
            ['7zip-18.05/test.tgz'],
            ['totalcommander-9.21a/test.tar'],
            ['totalcommander-9.21a/test.zip'],
            ['winrar-5.61/test.zip'],
            // ['winrar-5.61/test4.rar'], // not supported
            // ['winrar-5.61/test5.rar'], // not supported
            ['linux/zip-0.3/test.zip'],
            ['linux/p7zip-16.02/test.7z'],
            ['linux/p7zip-16.02/test.tar'],
            ['linux/p7zip-16.02/test.zip'],
        ];
    }

    /**
     * @dataProvider entryProvider
     */
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

    /**
     * @dataProvider entryProvider
     */
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
        $fullPath = \realpath($this->tmpDir.'/file.txt');

        $obj = new Archive7z($tempArchive);
        $obj->setPassword('111');
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
        $fullPath = \realpath($this->tmpDir.'/file.txt');

        $obj = new Archive7z($tempArchive);
        $obj->addEntry($fullPath);
        $result = $obj->getEntry('file.txt');
        self::assertInstanceOf(Entry::class, $result);
        self::assertEquals('file.txt', $result->getPath());
    }

    public function testAddEntryFullPathStore(): void
    {
        \copy($this->fixturesDir.'/test.txt', $this->tmpDir.'/file.txt');
        $fullPath = \realpath($this->tmpDir.'/file.txt');

        $obj = new Archive7z($this->tmpDir.'/test.7z');
        $obj->addEntry($fullPath, true);
        $result = $obj->getEntry($fullPath);
        self::assertInstanceOf(Entry::class, $result);
        self::assertEquals($fullPath, $result->getPath());
    }

    public function testAddEntryLocalPath(): void
    {
        \copy($this->fixturesDir.'/test.txt', $this->tmpDir.'/test.txt');
        $localPath = \realpath($this->tmpDir.'/test.txt');

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
        $localPath = \realpath($this->tmpDir);

        $obj = new Archive7z($this->tmpDir.'/test.7z');
        $obj->addEntry($localPath, true);
        $result = $obj->getEntry($localPath);
        self::assertInstanceOf(Entry::class, $result);
        self::assertEquals($localPath, $result->getPath());
    }

    /**
     * @return string[][]
     */
    public function delProvider(): array
    {
        return [
            // ['zip.7z'], // 7-Zip 21.02+ swears now at this
            // ['warnings.zip'], // not supported
            ['7zip-18.05/test.7z'],
            ['7zip-18.05/test.tar'],
            ['7zip-18.05/test.wim'],
            ['7zip-18.05/test.zip'],
            // ['7zip-18.05/test.tgz'],
            ['totalcommander-9.21a/test.tar'],
            ['totalcommander-9.21a/test.zip'],
            ['winrar-5.61/test.zip'],
            // ['winrar-5.61/test4.rar'], // not supported
            // ['winrar-5.61/test5.rar'], // not supported
            ['linux/zip-0.3/test.zip'],
            ['linux/p7zip-16.02/test.7z'],
            ['linux/p7zip-16.02/test.tar'],
            ['linux/p7zip-16.02/test.zip'],
        ];
    }

    /**
     * @dataProvider delProvider
     */
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
    public function delPasswdProvider(): array
    {
        return [
            ['testPasswd.7z'],
            ['testPasswd.zip'],
        ];
    }

    /**
     * @dataProvider delPasswdProvider
     */
    public function testDelEntryPasswd(string $fixtureArchiveName): void
    {
        \copy($this->fixturesDir.'/'.$fixtureArchiveName, $this->tmpDir.'/'.$fixtureArchiveName);
        $obj = new Archive7z($this->tmpDir.'/'.$fixtureArchiveName);
        $obj->setPassword('123');

        self::assertInstanceOf(Entry::class, $obj->getEntry('test/test.txt'));

        $obj->delEntry('test/test.txt');
        self::assertNull($obj->getEntry('test/test.txt'));
    }

    /**
     * @dataProvider delPasswdProvider
     */
    public function testDelEntryPasswdFail(string $fixtureArchiveName): void
    {
        \copy($this->fixturesDir.'/'.$fixtureArchiveName, $this->tmpDir.'/'.$fixtureArchiveName);
        $obj = new Archive7z($this->tmpDir.'/'.$fixtureArchiveName);

        if ('zip' !== \pathinfo($fixtureArchiveName, \PATHINFO_EXTENSION)) { // zip allow delete files from encrypted archives 😮
            $this->expectException(ProcessFailedException::class);
        }

        $obj->delEntry('test/test.txt');
    }

    /**
     * @dataProvider delPasswdProvider
     */
    public function testRenameEntryPasswd(string $fixtureArchiveName): void
    {
        \copy($this->fixturesDir.'/'.$fixtureArchiveName, $this->tmpDir.'/'.$fixtureArchiveName);
        $obj = new Archive7z($this->tmpDir.'/'.$fixtureArchiveName);
        $obj->setPassword('123');

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

    /**
     * @dataProvider basicProvider
     */
    public function testIsValid(string $archiveName): void
    {
        $valid = new Archive7z($this->fixturesDir.'/'.$archiveName);
        self::assertTrue($valid->isValid());
    }

    public function testGetWarnings(): void
    {
        $obj = new Archive7z($this->fixturesDir.'/warnings.zip');
        $warnings = $obj->getWarnings();

        self::assertCount(1, $warnings);
        self::assertSame('There are data after the end of archive', $warnings[0]);

        $obj = new Archive7z($this->fixturesDir.'/zip.7z');
        $noWarnings = $obj->getWarnings();
        self::assertEmpty($noWarnings);
    }

    /**
     * @return string[][]
     */
    public function extractPasswdEncFilesProvider(): array
    {
        return [
            ['testPasswdEncFiles.7z'],
        ];
    }

    /**
     * @dataProvider extractPasswdEncFilesProvider
     */
    public function testExtractPasswdEncFiles(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);
        $obj->setOutputDirectory($this->tmpDir);
        $obj->setPassword('abc123');
        $obj->extract();

        self::assertFileExists($this->tmpDir.'/file.txt');
        self::assertFileExists($this->tmpDir.'/file1.txt');
    }

    /**
     * @dataProvider extractPasswdEncFilesProvider
     */
    public function testGetEntriesPasswdEncFiles(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);
        $obj->setPassword('abc123');
        $result = $obj->getEntries();

        self::assertIsArray($result);
        self::assertCount(2, $result); // 2 files
        self::assertInstanceOf(Entry::class, $result[0]);
    }

    /**
     * @dataProvider extractPasswdEncFilesProvider
     */
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
        $fullPath = \realpath($this->tmpDir.'/file.txt');

        $obj = new Archive7z($tempArchive);
        $obj->setPassword('abc123');
        $obj->setEncryptFilenames(true);
        $obj->addEntry($fullPath);

        $result = $obj->getEntry('file.txt');
        self::assertInstanceOf(Entry::class, $result);
        self::assertEquals('file.txt', $result->getPath());

        $new = new Archive7z($tempArchive);
        $this->expectException(ProcessFailedException::class);
        $this->expectExceptionMessageMatches('/Can\s*not open encrypted archive\. Wrong password\?/');
        $new->getEntry('file.txt');
    }

    /**
     * @dataProvider extractPasswdEncFilesProvider
     */
    public function testIsValidPasswdEncFiles(string $archiveName): void
    {
        $valid = new Archive7z($this->fixturesDir.'/'.$archiveName);
        $valid->setPassword('abc123');

        self::assertTrue($valid->isValid());
    }

    /**
     * @dataProvider extractPasswdEncFilesProvider
     */
    public function testDelEntryPasswdEncFiles(string $fixtureArchiveName): void
    {
        \copy($this->fixturesDir.'/'.$fixtureArchiveName, $this->tmpDir.'/'.$fixtureArchiveName);
        $obj = new Archive7z($this->tmpDir.'/'.$fixtureArchiveName);
        $obj->setPassword('abc123');

        self::assertInstanceOf(Entry::class, $obj->getEntry('file1.txt'));

        $obj->delEntry('file1.txt');
        self::assertNull($obj->getEntry('file1.txt'));
    }

    /**
     * @dataProvider extractPasswdEncFilesProvider
     */
    public function testDelEntryPasswdEncFilesFail(string $fixtureArchiveName): void
    {
        \copy($this->fixturesDir.'/'.$fixtureArchiveName, $this->tmpDir.'/'.$fixtureArchiveName);
        $obj = new Archive7z($this->tmpDir.'/'.$fixtureArchiveName);

        $this->expectException(ProcessFailedException::class);
        $this->expectExceptionMessageMatches('/Can\s*not open encrypted archive\. Wrong password\?/');

        $obj->delEntry('file1.txt');
    }

    public function testCreateSolidArchive(): void
    {
        \copy($this->fixturesDir.'/test.txt', $this->tmpDir.'/file1.txt');
        \copy($this->fixturesDir.'/testArchive.txt', $this->tmpDir.'/file2.txt');
        $tempArchive = \tempnam($this->tmpDir, 'archive7z_').'.7z';

        $obj = new Archive7z($tempArchive);

        $solidMode = new SolidMode();
        $solidMode->setMode(SolidMode::ON);
        $obj->setSolidMode($solidMode);
        $obj->addEntry($this->tmpDir);

        self::assertTrue($obj->getInfo()->isSolid());
    }

    public function testCreateNonSolidArchive(): void
    {
        \copy($this->fixturesDir.'/test.txt', $this->tmpDir.'/file1.txt');
        \copy($this->fixturesDir.'/testArchive.txt', $this->tmpDir.'/file2.txt');
        $tempArchive = \tempnam($this->tmpDir, 'archive7z_').'.7z';

        $obj = new Archive7z($tempArchive);

        $solidMode = new SolidMode();
        $solidMode->setMode(SolidMode::OFF);
        $obj->setSolidMode($solidMode);
        $obj->addEntry($this->tmpDir);

        self::assertFalse($obj->getInfo()->isSolid());
    }

    public function testCreateLimitSolidArchive(): void
    {
        \copy($this->fixturesDir.'/test.txt', $this->tmpDir.'/file1.txt');
        \copy($this->fixturesDir.'/testArchive.txt', $this->tmpDir.'/file2.txt');
        $tempArchive = \tempnam($this->tmpDir, 'archive7z_').'.7z';

        $obj = new Archive7z($tempArchive);

        $solidMode = new SolidMode();
        $solidMode->setFilesLimit(2);
        $solidMode->setTotalSizeLimit(1024);
        $obj->setSolidMode($solidMode);
        $obj->addEntry($this->tmpDir);

        self::assertTrue($obj->getInfo()->isSolid());
    }

    public function testCreateMixedSolidArchive(): void
    {
        \copy($this->fixturesDir.'/test.txt', $this->tmpDir.'/file1.txt');
        \copy($this->fixturesDir.'/testArchive.txt', $this->tmpDir.'/file2.txt');
        $tempArchive = \tempnam($this->tmpDir, 'archive7z_').'.7z';

        $obj = new Archive7z($tempArchive);

        $solidMode = new SolidMode();
        $solidMode->setMode(SolidMode::ON);
        $obj->setSolidMode($solidMode);
        $obj->addEntry($this->tmpDir);

        \copy($this->fixturesDir.'/test.txt', $this->tmpDir.'/file3.txt');

        $obj->addEntry($this->tmpDir.'/file3.txt');

        self::assertTrue($obj->getInfo()->isSolid());
    }

    /**
     * @dataProvider basicProvider
     */
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

    /**
     * @dataProvider extractProvider
     */
    public function testGetEntriesLimit(string $archiveName): void
    {
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);
        $entries = $obj->getEntries(null, 2);

        self::assertIsArray($entries);
        self::assertCount(2, $entries);
        self::assertInstanceOf(Entry::class, $entries[0]);
        self::assertInstanceOf(Entry::class, $entries[1]);
    }

    /**
     * @dataProvider extractProvider
     */
    public function testGetEntriesPathMask(string $archiveName): void
    {
        $path = 'test/test.txt';
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);
        $entries = $obj->getEntries($path);

        self::assertIsArray($entries);
        self::assertCount(1, $entries);
        self::assertInstanceOf(Entry::class, $entries[0]);
        self::assertSame($path, $entries[0]->getUnixPath());
    }

    /**
     * @dataProvider extractProvider
     */
    public function testGetEntriesPathMaskCyrillic(string $archiveName): void
    {
        $path = 'чавес.jpg';
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);
        $entries = $obj->getEntries($path);

        self::assertIsArray($entries);
        self::assertCount(1, $entries);
        self::assertInstanceOf(Entry::class, $entries[0]);
        self::assertSame($path, $entries[0]->getUnixPath());
    }

    /**
     * @dataProvider extractProvider
     */
    public function testGetEntriesPathMaskWildcard(string $archiveName): void
    {
        $path = 'test';
        $obj = new Archive7z($this->fixturesDir.'/'.$archiveName);
        $entries = $obj->getEntries($path);

        self::assertIsArray($entries);
        self::assertCount(3, $entries); // 1 folder + 2 files in the folder
    }
}
