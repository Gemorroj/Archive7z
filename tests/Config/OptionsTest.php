<?php

declare(strict_types=1);

namespace Archive7z\Tests\Config;

use Archive7z\Archive7z;
use Archive7z\Config\FallbackEncoding;
use Archive7z\Config\Options;
use Archive7z\Config\SolidMode;
use Archive7z\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class OptionsTest extends TestCase
{
    public function testDefaults(): void
    {
        $options = new Options();

        self::assertNull($options->getPassword());
        self::assertSame(9, $options->getCompressionLevel());
        self::assertSame(Options::OVERWRITE_MODE_A, $options->getOverwriteMode());
        self::assertNull($options->getSolidMode());
        self::assertFalse($options->isEncryptFilenames());
        self::assertTrue($options->getFallbackEncoding()->isAsIs());
        self::assertSame([], $options->getEnv());
    }

    public function testValues(): void
    {
        $solidMode = SolidMode::off();
        $options = new Options(
            password: 'pass',
            compressionLevel: 5,
            overwriteMode: Options::OVERWRITE_MODE_U,
            solidMode: $solidMode,
            encryptFilenames: true,
            fallbackEncoding: FallbackEncoding::codepage('Windows-1251'),
            env: ['LC_ALL' => 'ru_RU.UTF-8'],
        );

        self::assertSame('pass', $options->getPassword());
        self::assertSame(5, $options->getCompressionLevel());
        self::assertSame(Options::OVERWRITE_MODE_U, $options->getOverwriteMode());
        self::assertSame($solidMode, $options->getSolidMode());
        self::assertTrue($options->isEncryptFilenames());
        self::assertSame('Windows-1251', $options->getFallbackEncoding()->getEncoding());
        self::assertSame(['LC_ALL' => 'ru_RU.UTF-8'], $options->getEnv());
    }

    public function testInvalidOverwriteMode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Options(overwriteMode: 'invalid');
    }

    public function testInvalidCompressionLevel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Options(compressionLevel: 10);
    }

    public function testGetOptions(): void
    {
        $options = new Options(password: 'pass');
        $obj = new Archive7z('fake.7z', $options);

        self::assertSame($options, $obj->getOptions());
    }
}
