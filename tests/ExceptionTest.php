<?php
namespace Tests\Archive7z;

use Archive7z\Exception;

class ExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function test()
    {
        self::assertInstanceOf('Exception', new Exception);
    }
}
