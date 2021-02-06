<?php

namespace Archive7z\Tests;

use Archive7z\Archive7z;
use Archive7z\Entry;
use Archive7z\Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class Archive7zTest extends TestCase
{
    protected $tmpDir;
    protected $fixturesDir;

    /**
     * @var Archive7z
     */
    protected $mock;

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
        $result = $this->mock->setOutputDirectory($this->tmpDir);
        self::assertInstanceOf(Archive7z::class, $result);
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
        $result = $this->mock->setPassword($password);
        self::assertInstanceOf(Archive7z::class, $result);
        self::assertEquals($password, $this->mock->getPassword());
    }

    public function testSetGetEncryptFilenames(): void
    {
        $defaultValue = false;
        self::assertEquals($defaultValue, $this->mock->getEncryptFilenames());
        $encryptFilenames = true;
        $result = $this->mock->setEncryptFilenames($encryptFilenames);
        self::assertInstanceOf(Archive7z::class, $result);
        self::assertEquals($encryptFilenames, $this->mock->getEncryptFilenames());
    }

    public function testSetGetOverwriteMode(): void
    {
        $result = $this->mock->setOverwriteMode(Archive7z::OVERWRITE_MODE_U);
        self::assertInstanceOf(Archive7z::class, $result);
        self::assertEquals(Archive7z::OVERWRITE_MODE_U, $this->mock->getOverwriteMode());
    }

    public function extractProvider(): array
    {
        return [
            ['zip.7z'],
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
        self::assertFileEquals($archiveFile, $targetFile);

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

    public function entryProvider(): array
    {
        return [
            ['zip.7z'],
            ['7zip-18.05/test.7z'],
            ['7zip-18.05/test.tar'],
            ['7zip-18.05/test.wim'],
            ['7zip-18.05/test.zip'],
            ['totalcommander-9.21a/test.tar'],
            ['totalcommander-9.21a/test.zip'],
            ['winrar-5.61/test.zip'],
            //['winrar-5.61/test4.rar'], // not supported
            //['winrar-5.61/test5.rar'], // not supported
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

        $obj = new Archive7z($tempArchive);
        $obj->setPassword('111');
        $obj->addEntry(\realpath($this->tmpDir.'/file.txt'));
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

        $obj = new Archive7z($tempArchive);
        $obj->addEntry(\realpath($this->tmpDir.'/file.txt'));
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

    public function delProvider(): array
    {
        return [
            ['zip.7z'],
            ['7zip-18.05/test.7z'],
            ['7zip-18.05/test.tar'],
            ['7zip-18.05/test.wim'],
            ['7zip-18.05/test.zip'],
            ['totalcommander-9.21a/test.tar'],
            ['totalcommander-9.21a/test.zip'],
            ['winrar-5.61/test.zip'],
            //['winrar-5.61/test4.rar'], // not supported
            //['winrar-5.61/test5.rar'], // not supported
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
     * @dataProvider extractProvider
     */
    public function testIsValid(string $archiveName): void
    {
        $valid = new Archive7z($this->fixturesDir.'/'.$archiveName);
        self::assertTrue($valid->isValid());
    }

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

        $obj = new Archive7z($tempArchive);
        $obj->setPassword('abc123');
        $obj->setEncryptFilenames(true);
        $obj->addEntry(\realpath($this->tmpDir.'/file.txt'));

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
}
