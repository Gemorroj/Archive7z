<?php
require_once 'Archive/7z.php';

class Archive_7zTest extends PHPUnit_Framework_TestCase
{
    protected $cliPath = 'c:\_SOFT_\Universal Extractor\bin\7z.exe';
    /**
     * @var Archive_7z
     */
    protected $mock;

    protected function setUp()
    {
        $this->mock = $this->getMock('Archive_7z', null, array('fake.7z', $this->cliPath));
    }


    public function testSetGetCli()
    {
        $result = $this->mock->setCli($this->cliPath);
        $this->assertInstanceOf('Archive_7z', $result);
        $this->assertEquals(realpath($this->cliPath), $this->mock->getCli());

    }

    public function testSetCliFail()
    {
        $this->setExpectedException('Archive_7z_Exception');
        $this->mock->setCli('./fake_path');
    }


    public function testSetGetFilename()
    {
        $filename = '/custom_path/test.7z';
        $result = $this->mock->setFilename($filename);
        $this->assertInstanceOf('Archive_7z', $result);
        $this->assertEquals($filename, $this->mock->getFilename());
    }

    public function testSetGetOutputDirectory()
    {
        $outputDirectory = dirname(__FILE__) . '/tmp';
        $result = $this->mock->setOutputDirectory($outputDirectory);
        $this->assertInstanceOf('Archive_7z', $result);
        $this->assertEquals(realpath($outputDirectory), $this->mock->getOutputDirectory());
    }

    public function testSetGetOutputDirectoryFail()
    {
        $outputDirectory = '/fake_path/test';
        $this->setExpectedException('Archive_7z_Exception');
        $this->mock->setOutputDirectory($outputDirectory);
    }

    public function testSetGetPassword()
    {
        $password = 'passw';
        $result = $this->mock->setPassword($password);
        $this->assertInstanceOf('Archive_7z', $result);
        $this->assertEquals($password, $this->mock->getPassword());
    }

    public function testSetGetOverwriteMode()
    {
        $result = $this->mock->setOverwriteMode(Archive_7z::OVERWRITE_MODE_U);
        $this->assertInstanceOf('Archive_7z', $result);
        $this->assertEquals(Archive_7z::OVERWRITE_MODE_U, $this->mock->getOverwriteMode());
    }


    /**
     * @todo test overwriteMode
     */
    public function testExtract()
    {
        $obj = new Archive_7z(dirname(__FILE__) . '/test.7z', $this->cliPath);
        $obj->setOutputDirectory(dirname(__FILE__) . '/tmp');
        $obj->extract();
    }

    public function testExtractPasswd()
    {
        $obj = new Archive_7z(dirname(__FILE__) . '/testPasswd.7z', $this->cliPath);
        $obj->setOutputDirectory(dirname(__FILE__) . '/tmp');
        $obj->setPassword('123');
        $obj->extract();
    }

    /**
     * @todo test overwriteMode
     */
    public function testExtractEntry()
    {
        $obj = new Archive_7z(dirname(__FILE__) . '/test.7z', $this->cliPath);
        $obj->setOutputDirectory(dirname(__FILE__) . '/tmp');
        $obj->extractEntry('test/2.jpg');
    }

    public function testExtractEntryDos()
    {
        $obj = new Archive_7z(dirname(__FILE__) . '/test.7z', $this->cliPath);
        $obj->setOutputDirectory(dirname(__FILE__) . '/tmp');
        $obj->extractEntry(iconv('UTF-8', 'CP866', 'чавес.jpg'));
    }

    public function testExtractEntryPasswd()
    {
        $obj = new Archive_7z(dirname(__FILE__) . '/testPasswd.7z', $this->cliPath);
        $obj->setOutputDirectory(dirname(__FILE__) . '/tmp');
        $obj->setPassword('123');
        $obj->extractEntry('1.jpg');
    }

    public function testGetContentPasswd()
    {
        $obj = new Archive_7z(dirname(__FILE__) . '/testPasswd.7z', $this->cliPath);
        $obj->setPassword('123');
        $result = $obj->getContent('test/test.txt');

        $this->assertEquals('test text', $result);
    }

    public function testGetEntriesPasswd()
    {
        $obj = new Archive_7z(dirname(__FILE__) . '/testPasswd.7z', $this->cliPath);
        $obj->setPassword('123');
        $result = $obj->getEntries();

        $this->assertTrue(is_array($result));
        $this->assertCount(5, $result); // 4 file + 1 directory
        $this->assertInstanceOf('Archive_7z_Entry', $result[0]);
    }
}
