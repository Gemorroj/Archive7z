<?php
require_once 'Archive/7z/Exception.php';

class Archive_7z_ExceptionTest extends PHPUnit_Framework_TestCase
{
    public function test()
    {
        $this->assertInstanceOf('Exception', new Archive_7z_Exception);
    }
}
