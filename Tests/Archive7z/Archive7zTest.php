<?php
namespace Tests\Archive7z;

use Archive7z\Archive7z;

class Archive7zTest extends \PHPUnit_Framework_TestCase
{
    protected $cliPath /* = 'c:\_SOFT_\Universal Extractor\bin\7z.exe'*/;
    protected $tmpDir;
    protected $filesDir;

    /**
     * @var Archive7z
     */
    protected $mock;

    protected function setUp()
    {
        $this->tmpDir = dirname(__DIR__) . '/tmp';
        $this->filesDir = dirname(__DIR__);
        $this->mock = $this->getMock('Archive7z\Archive7z', null, array('fake.7z', $this->cliPath));
    }

    protected function tearDown()
    {
        $this->cleanDir($this->tmpDir);
        touch($this->tmpDir . '/index.html');
    }

    protected function cleanDir($dir)
    {
        $h = opendir($dir);
        while (($file = readdir($h)) !== false) {
            if ($file !== '.' && $file !== '..') {
                if (is_dir($dir . '/' . $file) === true) {
                    $this->cleanDir($dir . '/' . $file);
                    rmdir($dir . '/' . $file);
                } else {
                    unlink($dir . '/' . $file);
                }
            }
        }
        closedir($h);
    }

    public function testSetGetCli()
    {
        // todo rewrite
        $cli = $this->mock->getCli();

        $result = $this->mock->setCli($cli);
        self::assertInstanceOf('Archive7z\Archive7z', $result);
        self::assertEquals(realpath($cli), $this->mock->getCli());
    }

    public function testSetCliFail()
    {
        $this->setExpectedException('Archive7z\Exception');
        $this->mock->setCli('./fake_path');
    }


    public function testSetGetFilename()
    {
        $filename = '/custom_path/test.7z';
        $result = $this->mock->setFilename($filename);
        self::assertInstanceOf('Archive7z\Archive7z', $result);
        self::assertEquals($filename, $this->mock->getFilename());
    }

    public function testSetGetOutputDirectory()
    {
        $result = $this->mock->setOutputDirectory($this->tmpDir);
        self::assertInstanceOf('Archive7z\Archive7z', $result);
        self::assertEquals(realpath($this->tmpDir), $this->mock->getOutputDirectory());
    }

    public function testSetGetOutputDirectoryFail()
    {
        $outputDirectory = '/fake_path/test';
        $this->setExpectedException('Archive7z\Exception');
        $this->mock->setOutputDirectory($outputDirectory);
    }

    public function testSetGetPassword()
    {
        $password = 'passw';
        $result = $this->mock->setPassword($password);
        self::assertInstanceOf('Archive7z\Archive7z', $result);
        self::assertEquals($password, $this->mock->getPassword());
    }

    public function testSetGetOverwriteMode()
    {
        $result = $this->mock->setOverwriteMode(Archive7z::OVERWRITE_MODE_U);
        self::assertInstanceOf('Archive7z\Archive7z', $result);
        self::assertEquals(Archive7z::OVERWRITE_MODE_U, $this->mock->getOverwriteMode());
    }


    public function testExtract()
    {
        $obj = new Archive7z($this->filesDir . '/test.7z', $this->cliPath);
        $obj->setOutputDirectory($this->tmpDir);
        $obj->extract();
    }

    public function testExtractCyrillic()
    {
        $dirCyrillic = $this->tmpDir . '/папка';
        $chavezFile = iconv('UTF-8', 'Windows-1251', 'чавес.jpg');

        if (!mkdir($dirCyrillic)) {
            self::markTestIncomplete('Cant create cyrillic directory.');
        }

        $obj = new Archive7z($this->filesDir . '/test.7z', $this->cliPath);
        $obj->setOutputDirectory($dirCyrillic);
        $obj->extract();

        self::assertFileExists($dirCyrillic . '/1.jpg');
        self::assertFileExists($dirCyrillic . '/' . $chavezFile);
        self::assertFileExists($dirCyrillic . '/test/test.txt');
    }

    public function testExtractPasswd()
    {
        $obj = new Archive7z($this->filesDir . '/testPasswd.7z', $this->cliPath);
        $obj->setOutputDirectory($this->tmpDir);
        $obj->setPassword('123');
        $obj->extract();
    }

    public function testExtractOverwrite()
    {
        if (!mkdir($this->tmpDir . '/test')) {
            self::markTestIncomplete('Cant create directory.');
        }

        $sourceFile = $this->filesDir . '/test.txt';
        $targetFile = $this->tmpDir . '/test/test.txt';
        $archiveFile = $this->filesDir . '/testArchive.txt';

        $obj = new Archive7z($this->filesDir . '/test.7z', $this->cliPath);
        $obj->setOutputDirectory($this->tmpDir);


        $obj->setOverwriteMode(Archive7z::OVERWRITE_MODE_A);
        copy($sourceFile, $targetFile);
        $obj->extract();
        self::assertFileEquals($archiveFile, $targetFile);


        $obj->setOverwriteMode(Archive7z::OVERWRITE_MODE_S);
        copy($sourceFile, $targetFile);
        $obj->extract();
        self::assertFileEquals($sourceFile, $targetFile);


        $obj->setOverwriteMode(Archive7z::OVERWRITE_MODE_T);
        copy($sourceFile, $targetFile);
        $obj->extract();
        self::assertFileExists($this->tmpDir . '/test/test_1.txt');
        self::assertFileEquals($archiveFile, $targetFile);
        self::assertFileEquals($sourceFile, $this->tmpDir . '/test/test_1.txt');
        unlink($this->tmpDir . '/test/test_1.txt');


        $obj->setOverwriteMode(Archive7z::OVERWRITE_MODE_U);
        copy($sourceFile, $targetFile);
        $obj->extract();
        self::assertFileExists($this->tmpDir . '/test/test_1.txt');
        self::assertFileEquals($sourceFile, $targetFile);
        self::assertFileEquals($archiveFile, $this->tmpDir . '/test/test_1.txt');
        unlink($this->tmpDir . '/test/test_1.txt');
    }


    public function testExtractEntry()
    {
        $obj = new Archive7z($this->filesDir . '/test.7z', $this->cliPath);
        $obj->setOutputDirectory($this->tmpDir);
        $obj->extractEntry('test/2.jpg');
        self::assertFileExists($this->tmpDir . '/test/2.jpg');
    }

    public function testExtractEntryOverwrite()
    {
        if (!mkdir($this->tmpDir . '/test')) {
            self::markTestIncomplete('Cant create directory.');
        }

        $sourceFile = $this->filesDir . '/test.txt';
        $targetFile = $this->tmpDir . '/test/test.txt';
        $archiveFile = $this->filesDir . '/testArchive.txt';

        $obj = new Archive7z($this->filesDir . '/test.7z', $this->cliPath);
        $obj->setOutputDirectory($this->tmpDir);


        $obj->setOverwriteMode(Archive7z::OVERWRITE_MODE_A);
        copy($sourceFile, $targetFile);
        $obj->extractEntry('test/test.txt');
        self::assertFileEquals($archiveFile, $targetFile);


        $obj->setOverwriteMode(Archive7z::OVERWRITE_MODE_S);
        copy($sourceFile, $targetFile);
        $obj->extractEntry('test/test.txt');
        self::assertFileEquals($sourceFile, $targetFile);


        $obj->setOverwriteMode(Archive7z::OVERWRITE_MODE_T);
        copy($sourceFile, $targetFile);
        $obj->extractEntry('test/test.txt');
        self::assertFileExists($this->tmpDir . '/test/test_1.txt');
        self::assertFileEquals($archiveFile, $targetFile);
        self::assertFileEquals($sourceFile, $this->tmpDir . '/test/test_1.txt');
        unlink($this->tmpDir . '/test/test_1.txt');


        $obj->setOverwriteMode(Archive7z::OVERWRITE_MODE_U);
        copy($sourceFile, $targetFile);
        $obj->extractEntry('test/test.txt');
        self::assertFileExists($this->tmpDir . '/test/test_1.txt');
        self::assertFileEquals($sourceFile, $targetFile);
        self::assertFileEquals($archiveFile, $this->tmpDir . '/test/test_1.txt');
        unlink($this->tmpDir . '/test/test_1.txt');
    }


    public function testExtractEntryUnicode()
    {
        $file = iconv('UTF-8', 'Windows-1251', 'чавес.jpg');
        $obj = new Archive7z($this->filesDir . '/test.7z', $this->cliPath);
        $obj->setOutputDirectory($this->tmpDir);
        $obj->extractEntry($file);

        self::assertFileExists($this->tmpDir . '/' . $file);
    }

    public function testExtractEntryPasswd()
    {
        $obj = new Archive7z($this->filesDir . '/testPasswd.7z', $this->cliPath);
        $obj->setOutputDirectory($this->tmpDir);
        $obj->setPassword('123');
        $obj->extractEntry('1.jpg');
    }

    public function testGetContentPasswd()
    {
        $obj = new Archive7z($this->filesDir . '/testPasswd.7z', $this->cliPath);
        $obj->setPassword('123');
        $result = $obj->getContent('test/test.txt');

        self::assertEquals(file_get_contents($this->filesDir . '/testArchive.txt'), $result);
    }

    public function testGetEntriesPasswd()
    {
        $obj = new Archive7z($this->filesDir . '/testPasswd.7z', $this->cliPath);
        $obj->setPassword('123');
        $result = $obj->getEntries();

        self::assertTrue(is_array($result));
        self::assertCount(5, $result); // 4 file + 1 directory
        self::assertInstanceOf('Archive7z\Entry', $result[0]);
    }

    public function testGetEntryPasswd()
    {
        $obj = new Archive7z($this->filesDir . '/testPasswd.7z', $this->cliPath);
        $obj->setPassword('123');
        $result = $obj->getEntry('test' . DIRECTORY_SEPARATOR . 'test.txt');

        self::assertInstanceOf('Archive7z\Entry', $result);
    }

    public function testAddEntryFullPathPasswd()
    {
        //copy($this->filesDir . '/test.7z', $this->tmpDir . '/test.7z');
        copy($this->filesDir . '/test.txt', $this->tmpDir . '/file.txt');

        $obj = new Archive7z($this->tmpDir . '/test.7z', $this->cliPath);
        $obj->setPassword('111');
        $obj->addEntry(realpath($this->tmpDir . '/file.txt'), false, false);
        $result = $obj->getEntry('file.txt');
        self::assertInstanceOf('Archive7z\Entry', $result);
        self::assertEquals('file.txt', $result->getPath());

        $new = new Archive7z($this->tmpDir . '/test.7z', $this->cliPath);
        $this->setExpectedException('Archive7z\Exception');
        $new->getContent('file.txt');
    }

    public function testAddEntryFullPath()
    {
        //copy($this->filesDir . '/test.7z', $this->tmpDir . '/test.7z');
        copy($this->filesDir . '/test.txt', $this->tmpDir . '/file.txt');

        $obj = new Archive7z($this->tmpDir . '/test.7z', $this->cliPath);
        $obj->addEntry(realpath($this->tmpDir . '/file.txt'), false, false);
        $result = $obj->getEntry('file.txt');
        self::assertInstanceOf('Archive7z\Entry', $result);
        self::assertEquals('file.txt', $result->getPath());
    }

    public function testAddEntryFullPathStore()
    {
        //copy($this->filesDir . '/test.7z', $this->tmpDir . '/test.7z');
        copy($this->filesDir . '/test.txt', $this->tmpDir . '/file.txt');
        $fullPath = realpath($this->tmpDir . '/file.txt');

        $obj = new Archive7z($this->tmpDir . '/test.7z', $this->cliPath);
        $obj->addEntry($fullPath, false, true);
        $result = $obj->getEntry($fullPath);
        self::assertInstanceOf('Archive7z\Entry', $result);
        self::assertEquals($fullPath, $result->getPath());
    }

    public function testAddEntryLocalPath()
    {
        //copy($this->filesDir . '/test.7z', $this->tmpDir . '/test.7z');
        copy($this->filesDir . '/test.txt', $this->tmpDir . '/test.txt');
        $localPath = basename($this->filesDir) . DIRECTORY_SEPARATOR . basename(
                $this->tmpDir
            ) . DIRECTORY_SEPARATOR . 'test.txt';

        $obj = new Archive7z($this->tmpDir . '/test.7z', $this->cliPath);
        $obj->addEntry($localPath, false, true);
        $result = $obj->getEntry($localPath);
        self::assertInstanceOf('Archive7z\Entry', $result);
        self::assertEquals($localPath, $result->getPath());
    }

    public function testAddEntryLocalPathSubFiles()
    {
        if (!mkdir($this->tmpDir . '/test')) {
            self::markTestIncomplete('Cant create directory.');
        }

        copy($this->filesDir . '/test.txt', $this->tmpDir . '/test/test.txt');
        $localPath = basename($this->filesDir) . DIRECTORY_SEPARATOR . basename($this->tmpDir);

        $obj = new Archive7z($this->tmpDir . '/test.7z', $this->cliPath);
        $obj->addEntry($localPath, true, true);
        $result = $obj->getEntry($localPath);
        self::assertInstanceOf('Archive7z\Entry', $result);
        self::assertEquals($localPath, $result->getPath());
    }

    public function testAddEntryFullPathSubFiles()
    {
        if (!mkdir($this->tmpDir . '/test')) {
            self::markTestIncomplete('Cant create directory.');
        }

        copy($this->filesDir . '/test.txt', $this->tmpDir . '/test/test.txt');

        $obj = new Archive7z($this->tmpDir . '/test.7z', $this->cliPath);
        $obj->addEntry(realpath($this->tmpDir), true, false);
        $result = $obj->getEntry(basename($this->tmpDir));
        self::assertInstanceOf('Archive7z\Entry', $result);
        self::assertEquals(basename($this->tmpDir), $result->getPath());
    }

    public function testDelEntry()
    {
        copy($this->filesDir . '/test.7z', $this->tmpDir . '/test.7z');
        $obj = new Archive7z($this->tmpDir . '/test.7z', $this->cliPath);
        $obj->delEntry('test/test.txt');
        self::assertNull($obj->getEntry('test/test.txt'));
    }

    public function testDelEntryPasswd()
    {
        copy($this->filesDir . '/testPasswd.7z', $this->tmpDir . '/test.7z');
        $obj = new Archive7z($this->tmpDir . '/test.7z', $this->cliPath);
        $obj->setPassword('123');
        $obj->delEntry('test/test.txt');
        self::assertNull($obj->getEntry('test/test.txt'));
    }

    public function testDelEntryPasswdFail()
    {
        copy($this->filesDir . '/testPasswd.7z', $this->tmpDir . '/test.7z');
        $obj = new Archive7z($this->tmpDir . '/test.7z', $this->cliPath);
        $this->setExpectedException('Archive7z\Exception');
        $obj->delEntry('test/test.txt');
    }

    public function testRenameEntryPasswd()
    {
        copy($this->filesDir . '/testPasswd.7z', $this->tmpDir . '/test.7z');
        $obj = new Archive7z($this->tmpDir . '/test.7z', $this->cliPath);
        $obj->setPassword('123');
        $obj->renameEntry('test' . DIRECTORY_SEPARATOR . 'test.txt', 'test' . DIRECTORY_SEPARATOR . 'newTest.txt');
        $resultSrc = $obj->getEntry('test' . DIRECTORY_SEPARATOR . 'test.txt');
        self::assertNull($resultSrc);
        $resultDest = $obj->getEntry('test' . DIRECTORY_SEPARATOR . 'newTest.txt');
        self::assertInstanceOf('Archive7z\Entry', $resultDest);
    }
}
