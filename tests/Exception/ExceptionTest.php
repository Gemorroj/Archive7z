<?php

declare(strict_types=1);

namespace Archive7z\Tests\Exception;

use Archive7z\Exception\ExceptionInterface;
use Archive7z\Exception\InvalidArgumentException;
use Archive7z\Exception\RuntimeException;
use PHPUnit\Framework\TestCase;

class ExceptionTest extends TestCase
{
    public function testRuntimeException(): void
    {
        $exception = new RuntimeException();

        self::assertInstanceOf(\RuntimeException::class, $exception);
        self::assertInstanceOf(ExceptionInterface::class, $exception);
    }

    public function testInvalidArgumentException(): void
    {
        $exception = new InvalidArgumentException();

        self::assertInstanceOf(\InvalidArgumentException::class, $exception);
        self::assertInstanceOf(ExceptionInterface::class, $exception);
    }
}
