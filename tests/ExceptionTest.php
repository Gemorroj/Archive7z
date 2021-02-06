<?php

namespace Archive7z\Tests;

use Archive7z\Exception;
use PHPUnit\Framework\TestCase;

class ExceptionTest extends TestCase
{
    public function test(): void
    {
        self::assertInstanceOf(\Exception::class, new Exception());
    }
}
