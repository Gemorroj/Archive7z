<?php
namespace Archive7z\Tests;

use Archive7z\Archive7z;
use Archive7z\Entry;
use Archive7z\Exception;
use Symfony\Component\Process\Exception\ProcessFailedException;

class Archive7zTest extends \PHPUnit_Framework_TestCase
{
    protected $tmpDir;
    protected $fixturesDir;

    /**
     * @var Archive7z
     */
    protected $mock;

    protected function setUp()
    {
        $this->fixturesDir = __DIR__ . '/fixtures';

        $this->tmpDir = \sys_get_temp_dir() . '/' . \uniqid('Archive7z-', false);
        \mkdir($this->tmpDir);

        $this->mock = new Archive7z('fake.7z');
    }


    protected function getCurrentFilesystemEncoding()
    {
        if (\stripos(\PHP_OS, 'WIN') !== false) { // windows
            return 'Windows-1251';
        }
        return \exec('locale charmap');
    }


    protected function tearDown()
    {
        $this->cleanDir($this->tmpDir);
        \rmdir($this->tmpDir);
    }

    protected function cleanDir($dir)
    {
        $h = \opendir($dir);
        while (($file = \readdir($h)) !== false) {
            if ($file !== '.' && $file !== '..') {
                if (\is_dir($dir . '/' . $file)) {
                    $this->cleanDir($dir . '/' . $file);
                    \rmdir($dir . '/' . $file);
                } else {
                    \unlink($dir . '/' . $file);
                }
            }
        }
        \closedir($h);
    }

    public function testSetGetBinary7z()
    {
        // todo rewrite
        $binary = $this->mock->getBinary7z();

        $result = $this->mock->setBinary7z($binary);
        self::assertInstanceOf(Archive7z::class, $result);
        self::assertEquals(\realpath($binary), $this->mock->getBinary7z());
    }

    public function testSetBinary7zFail()
    {
        $this->expectException(Exception::class);
        $this->mock->setBinary7z('./fake_path');
    }


    public function testSetGetFilename()
    {
        $filename = '/custom_path/test.7z';
        $result = $this->mock->setFilename($filename);
        self::assertInstanceOf(Archive7z::class, $result);
        self::assertEquals($filename, $this->mock->getFilename());
    }

    public function testSetGetOutputDirectory()
    {
        $result = $this->mock->setOutputDirectory($this->tmpDir);
        self::assertInstanceOf(Archive7z::class, $result);
        self::assertEquals(\realpath($this->tmpDir), $this->mock->getOutputDirectory());
    }

    public function testSetGetOutputDirectoryFail()
    {
        $outputDirectory = '/fake_path/test';
        $this->expectException(Exception::class);
        $this->mock->setOutputDirectory($outputDirectory);
    }

    public function testSetGetPassword()
    {
        $password = 'passw';
        $result = $this->mock->setPassword($password);
        self::assertInstanceOf(Archive7z::class, $result);
        self::assertEquals($password, $this->mock->getPassword());
    }

    public function testSetGetOverwriteMode()
    {
        $result = $this->mock->setOverwriteMode(Archive7z::OVERWRITE_MODE_U);
        self::assertInstanceOf(Archive7z::class, $result);
        self::assertEquals(Archive7z::OVERWRITE_MODE_U, $this->mock->getOverwriteMode());
    }


    public function extractProvider()
    {
        return [
            ['test.7z'],
            ['test.zip'],
            ['test.tar'],
            ['test.rar'],
            ['testUnix.zip'],
        ];
    }


    /**
     * @param string $archiveName
     * @dataProvider extractProvider
     */
    public function testExtractCyrillic($archiveName)
    {
        $dirCyrillic = $this->tmpDir . '/папка';
        //$chavezFile = iconv('UTF-8', $this->getCurrentFilesystemEncoding(), 'чавес.jpg');
        $chavezFile = 'чавес.jpg';

        if (!\mkdir($dirCyrillic)) {
            self::markTestIncomplete('Cant create cyrillic directory.');
        }

        $obj = new Archive7z($this->fixturesDir . '/' . $archiveName);
        $obj->setOutputDirectory($dirCyrillic);
        $obj->extract();

        self::assertFileExists($dirCyrillic . '/1.jpg');
        self::assertFileExists($dirCyrillic . '/' . $chavezFile);
        self::assertFileExists($dirCyrillic . '/test/test.txt');
    }


    public function extractPasswdProvider()
    {
        return [
            ['testPasswd.7z'],
            ['testPasswd.zip'],
            ['testPasswd.rar'],
        ];
    }


    /**
     * @param string $archiveName
     * @dataProvider extractPasswdProvider
     */
    public function testExtractPasswd($archiveName)
    {
        $obj = new Archive7z($this->fixturesDir . '/' . $archiveName);
        $obj->setOutputDirectory($this->tmpDir);
        $obj->setPassword('123');
        $obj->extract();

        //$chavezFile = iconv('UTF-8', $this->getCurrentFilesystemEncoding(), 'чавес.jpg');
        $chavezFile = 'чавес.jpg';

        self::assertFileExists($this->tmpDir . '/1.jpg');
        self::assertFileExists($this->tmpDir . '/' . $chavezFile);
        self::assertFileExists($this->tmpDir . '/test/test.txt');
    }


    /**
     * @param string $archiveName
     * @dataProvider extractProvider
     */
    public function testExtractOverwrite($archiveName)
    {
        if (!\mkdir($this->tmpDir . '/test')) {
            self::markTestIncomplete('Cant create directory.');
        }

        $sourceFile = $this->fixturesDir . '/test.txt';
        $targetFile = $this->tmpDir . '/test/test.txt';
        $archiveFile = $this->fixturesDir . '/testArchive.txt';

        $obj = new Archive7z($this->fixturesDir . '/' . $archiveName);
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
        self::assertFileExists($this->tmpDir . '/test/test_1.txt');
        self::assertFileEquals($archiveFile, $targetFile);
        self::assertFileEquals($sourceFile, $this->tmpDir . '/test/test_1.txt');
        \unlink($this->tmpDir . '/test/test_1.txt');


        $obj->setOverwriteMode(Archive7z::OVERWRITE_MODE_U);
        \copy($sourceFile, $targetFile);
        $obj->extract();
        self::assertFileExists($this->tmpDir . '/test/test_1.txt');
        self::assertFileEquals($sourceFile, $targetFile);
        self::assertFileEquals($archiveFile, $this->tmpDir . '/test/test_1.txt');
        \unlink($this->tmpDir . '/test/test_1.txt');
    }


    /**
     * @param string $archiveName
     * @dataProvider extractProvider
     */
    public function testExtractEntry($archiveName)
    {
        $obj = new Archive7z($this->fixturesDir . '/' . $archiveName);
        $obj->setOutputDirectory($this->tmpDir);
        $obj->extractEntry('test/2.jpg');
        self::assertFileExists($this->tmpDir . '/test/2.jpg');
    }


    /**
     * @param string $archiveName
     * @dataProvider extractProvider
     */
    public function testExtractEntryOverwrite($archiveName)
    {
        if (!\mkdir($this->tmpDir . '/test')) {
            self::markTestIncomplete('Cant create directory.');
        }

        $sourceFile = $this->fixturesDir . '/test.txt';
        $targetFile = $this->tmpDir . '/test/test.txt';
        $archiveFile = $this->fixturesDir . '/testArchive.txt';

        $obj = new Archive7z($this->fixturesDir . '/' . $archiveName);
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
        self::assertFileExists($this->tmpDir . '/test/test_1.txt');
        self::assertFileEquals($archiveFile, $targetFile);
        self::assertFileEquals($sourceFile, $this->tmpDir . '/test/test_1.txt');
        \unlink($this->tmpDir . '/test/test_1.txt');


        $obj->setOverwriteMode(Archive7z::OVERWRITE_MODE_U);
        \copy($sourceFile, $targetFile);
        $obj->extractEntry('test/test.txt');
        self::assertFileExists($this->tmpDir . '/test/test_1.txt');
        self::assertFileEquals($sourceFile, $targetFile);
        self::assertFileEquals($archiveFile, $this->tmpDir . '/test/test_1.txt');
        \unlink($this->tmpDir . '/test/test_1.txt');
    }


    /**
     * @param string $archiveName
     * @dataProvider extractProvider
     */
    public function testExtractEntryCyrillic($archiveName)
    {
        //$file = iconv('UTF-8', $this->getCurrentFilesystemEncoding(), 'чавес.jpg');
        $file = 'чавес.jpg';

        $obj = new Archive7z($this->fixturesDir . '/' . $archiveName);
        $obj->setOutputDirectory($this->tmpDir);
        $obj->extractEntry($file);

        self::assertFileExists($this->tmpDir . '/' . $file);
    }

    /**
     * @param string $archiveName
     * @dataProvider extractPasswdProvider
     */
    public function testExtractEntryPasswd($archiveName)
    {
        $obj = new Archive7z($this->fixturesDir . '/' . $archiveName);
        $obj->setOutputDirectory($this->tmpDir);
        $obj->setPassword('123');
        $obj->extractEntry('1.jpg');
    }


    /**
     * @param string $archiveName
     * @dataProvider extractPasswdProvider
     */
    public function testGetContentPasswd($archiveName)
    {
        $obj = new Archive7z($this->fixturesDir . '/' . $archiveName);
        $obj->setPassword('123');
        $result = $obj->getContent('test/test.txt');

        self::assertStringEqualsFile($this->fixturesDir . '/testArchive.txt', $result);
    }


    /**
     * @param string $archiveName
     * @dataProvider extractPasswdProvider
     */
    public function testGetEntriesPasswd($archiveName)
    {
        $obj = new Archive7z($this->fixturesDir . '/' . $archiveName);
        $obj->setPassword('123');
        $result = $obj->getEntries();

        self::assertInternalType('array', $result);
        self::assertCount(5, $result); // 4 file + 1 directory
        self::assertInstanceOf(Entry::class, $result[0]);
    }


    /**
     * @param string $archiveName
     * @dataProvider extractPasswdProvider
     */
    public function testGetEntryPasswd($archiveName)
    {
        $obj = new Archive7z($this->fixturesDir . '/' . $archiveName);
        $obj->setPassword('123');
        $result = $obj->getEntry('test/test.txt');

        self::assertInstanceOf(Entry::class, $result);
    }

    public function entryProvider()
    {
        return [
            ['test.7z'],
            ['test.zip'],
            ['test.tar'],
            ['testUnix.zip'],
        ];
    }

    /**
     * @param string $archiveName
     * @dataProvider entryProvider
     */
    public function testAddEntryExists($archiveName)
    {
        \copy($this->fixturesDir . '/' . $archiveName, $this->tmpDir . '/' . $archiveName);
        $tempArchive = \tempnam($this->tmpDir, 'archive7z_') . '_' . $archiveName;

        $obj = new Archive7z($tempArchive);
        $obj->addEntry(__FILE__);
        $result = $obj->getEntry(\basename(__FILE__));
        self::assertInstanceOf(Entry::class, $result);
        self::assertEquals(\basename(__FILE__), $result->getPath());
        self::assertTrue($obj->isValid());
    }

    /**
     * @param string $archiveName
     * @dataProvider entryProvider
     */
    public function testAddEntryNew($archiveName)
    {
        $tempArchive = \tempnam($this->tmpDir, 'archive7z_') . '_' . $archiveName;

        $obj = new Archive7z($tempArchive);
        $obj->addEntry(__FILE__);
        $result = $obj->getEntry(\basename(__FILE__));
        self::assertInstanceOf(Entry::class, $result);
        self::assertEquals(\basename(__FILE__), $result->getPath());
        self::assertTrue($obj->isValid());
    }

    public function testAddEntryRar()
    {
        $tempArchive = \tempnam($this->tmpDir, 'archive7z_') . '_archive.rar';

        $obj = new Archive7z($tempArchive);
        $this->expectException(ProcessFailedException::class);
        $obj->addEntry(__FILE__);
    }


    public function testAddEntryFullPathPasswd()
    {
        \copy($this->fixturesDir . '/test.txt', $this->tmpDir . '/file.txt');
        $tempArchive = \tempnam($this->tmpDir, 'archive7z_') . '7z';

        $obj = new Archive7z($tempArchive);
        $obj->setPassword('111');
        $obj->addEntry(\realpath($this->tmpDir . '/file.txt'), false, false);
        $result = $obj->getEntry('file.txt');
        self::assertInstanceOf(Entry::class, $result);
        self::assertEquals('file.txt', $result->getPath());

        $new = new Archive7z($tempArchive);
        $this->expectException(ProcessFailedException::class);
        $new->getContent('file.txt');
    }

    public function testAddEntryFullPath()
    {
        \copy($this->fixturesDir . '/test.txt', $this->tmpDir . '/file.txt');
        $tempArchive = \tempnam($this->tmpDir, 'archive7z_') . '7z';

        $obj = new Archive7z($tempArchive);
        $obj->addEntry(\realpath($this->tmpDir . '/file.txt'), false, false);
        $result = $obj->getEntry('file.txt');
        self::assertInstanceOf(Entry::class, $result);
        self::assertEquals('file.txt', $result->getPath());
    }

    public function testAddEntryFullPathStore()
    {
        \copy($this->fixturesDir . '/test.txt', $this->tmpDir . '/file.txt');
        $fullPath = \realpath($this->tmpDir . '/file.txt');

        $obj = new Archive7z($this->tmpDir . '/test.7z');
        $obj->addEntry($fullPath, false, true);
        $result = $obj->getEntry($fullPath);
        self::assertInstanceOf(Entry::class, $result);
        self::assertEquals($fullPath, $result->getPath());
    }

    public function testAddEntryLocalPath()
    {
        \copy($this->fixturesDir . '/test.txt', $this->tmpDir . '/test.txt');
        $localPath = \realpath($this->tmpDir . '/test.txt');

        $obj = new Archive7z($this->tmpDir . '/test.7z');
        $obj->addEntry($localPath, false, true);
        $result = $obj->getEntry($localPath);

        self::assertInstanceOf(Entry::class, $result);
        self::assertEquals($localPath, $result->getPath());
    }

    public function testAddEntryLocalPathSubFiles()
    {
        if (!\mkdir($this->tmpDir . '/test')) {
            self::markTestIncomplete('Cant create directory.');
        }

        \copy($this->fixturesDir . '/test.txt', $this->tmpDir . '/test/test.txt');
        $localPath = \realpath($this->tmpDir);

        $obj = new Archive7z($this->tmpDir . '/test.7z');
        $obj->addEntry($localPath, true, true);
        $result = $obj->getEntry($localPath);
        self::assertInstanceOf(Entry::class, $result);
        self::assertEquals($localPath, $result->getPath());
    }

    public function testAddEntryFullPathSubFiles()
    {
        if (!\mkdir($this->tmpDir . '/test')) {
            self::markTestIncomplete('Cant create directory.');
        }

        \copy($this->fixturesDir . '/test.txt', $this->tmpDir . '/test/test.txt');

        $obj = new Archive7z($this->tmpDir . '/test.7z');
        $obj->addEntry(\realpath($this->tmpDir), true, false);
        $result = $obj->getEntry(\basename($this->tmpDir));
        self::assertInstanceOf(Entry::class, $result);
        self::assertEquals(\basename($this->tmpDir), $result->getPath());
    }

    public function delProvider()
    {
        return [
            ['test.7z', 'test.7z'],
            ['test.zip', 'test.zip'],
            ['testUnix.zip', 'testUnix.zip'],
        ];
    }

    /**
     * @param string $fixtureArchiveName
     * @param string $tmpArchiveName
     * @dataProvider delProvider
     */
    public function testDelEntry($fixtureArchiveName, $tmpArchiveName)
    {
        \copy($this->fixturesDir . '/' . $fixtureArchiveName, $this->tmpDir . '/' . $tmpArchiveName);
        $obj = new Archive7z($this->tmpDir . '/' . $tmpArchiveName);

        self::assertInstanceOf(Entry::class, $obj->getEntry('test/test.txt'));

        $obj->delEntry('test/test.txt');
        self::assertNull($obj->getEntry('test/test.txt'));
    }


    public function delPasswdProvider()
    {
        return [
            ['testPasswd.7z', 'testPasswd.7z'],
            ['testPasswd.zip', 'testPasswd.zip'],
        ];
    }

    /**
     * @param string $fixtureArchiveName
     * @param string $tmpArchiveName
     * @dataProvider delPasswdProvider
     */
    public function testDelEntryPasswd($fixtureArchiveName, $tmpArchiveName)
    {
        \copy($this->fixturesDir . '/' . $fixtureArchiveName, $this->tmpDir . '/' . $tmpArchiveName);
        $obj = new Archive7z($this->tmpDir . '/' . $tmpArchiveName);
        $obj->setPassword('123');

        self::assertInstanceOf(Entry::class, $obj->getEntry('test/test.txt'));

        $obj->delEntry('test/test.txt');
        self::assertNull($obj->getEntry('test/test.txt'));
    }

    /**
     * @param string $fixtureArchiveName
     * @param string $tmpArchiveName
     * @dataProvider delPasswdProvider
     */
    public function testDelEntryPasswdFail($fixtureArchiveName, $tmpArchiveName)
    {
        \copy($this->fixturesDir . '/' . $fixtureArchiveName, $this->tmpDir . '/' . $tmpArchiveName);
        $obj = new Archive7z($this->tmpDir . '/' . $tmpArchiveName);

        if (\pathinfo($fixtureArchiveName, PATHINFO_EXTENSION) !== 'zip') { // zip allow delete files from encrypted archives 😮
            $this->expectException(ProcessFailedException::class);
        }

        $obj->delEntry('test/test.txt');
    }


    /**
     * @param string $fixtureArchiveName
     * @param string $tmpArchiveName
     * @dataProvider delPasswdProvider
     */
    public function testRenameEntryPasswd($fixtureArchiveName, $tmpArchiveName)
    {
        \copy($this->fixturesDir . '/' . $fixtureArchiveName, $this->tmpDir . '/' . $tmpArchiveName);
        $obj = new Archive7z($this->tmpDir . '/' . $tmpArchiveName);
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
     * @param string $archiveName
     * @dataProvider extractProvider
     */
    public function testChangeSystemLocale($archiveName)
    {
        //$file = iconv('UTF-8', $this->getCurrentFilesystemEncoding(), 'чавес.jpg');
        $file = 'чавес.jpg';

        $obj = new Archive7z($this->fixturesDir . '/' . $archiveName);
        $obj->setChangeSystemLocale(true);
        $obj->setOutputDirectory($this->tmpDir);
        $obj->extractEntry($file);

        self::assertFileExists($this->tmpDir . '/' . $file);
    }


    /**
     * @param string $archiveName
     * @dataProvider extractProvider
     */
    public function testChangeSystemLocaleFail($archiveName)
    {
        $new = new Archive7z($this->tmpDir . '/' . $archiveName);
        $new->setChangeSystemLocale(true);
        $this->expectException(ProcessFailedException::class);
        $new->getContent('file.txt');
    }


    /**
     * @param string $archiveName
     * @dataProvider extractProvider
     */
    public function testIsValid($archiveName)
    {
        $valid = new Archive7z($this->fixturesDir . '/' . $archiveName);
        self::assertTrue($valid->isValid());
    }
}
