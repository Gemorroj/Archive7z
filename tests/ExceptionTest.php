<?php
namespace Archive7z\Tests;

use Archive7z\Exception;

class ExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function test()
    {
        self::assertInstanceOf('Exception', new Exception);
    }
}
