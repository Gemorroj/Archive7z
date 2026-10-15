<?php

declare(strict_types=1);

namespace Archive7z\Tests\Config;

use Archive7z\Archive7z;
use Archive7z\Config\CompressOptions;
use Archive7z\Config\ExtractOptions;
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
        self::assertSame(9, $options->getCompress()->getCompressionLevel());
        self::assertSame(ExtractOptions::OVERWRITE_MODE_A, $options->getExtract()->getOverwriteMode());
        self::assertNull($options->getCompress()->getSolidMode());
        self::assertFalse($options->getCompress()->isEncryptFilenames());
        self::assertTrue($options->getFallbackEncoding()->isDetect());
        self::assertSame([], $options->getEnv());
    }

    public function testValues(): void
    {
        $solidMode = SolidMode::off();
        $extract = new ExtractOptions(ExtractOptions::OVERWRITE_MODE_U);
        $compress = new CompressOptions(
            compressionLevel: 5,
            solidMode: $solidMode,
            encryptFilenames: true,
        );
        $options = new Options(
            password: 'pass',
            fallbackEncoding: FallbackEncoding::codepage('Windows-1251'),
            env: ['LC_ALL' => 'ru_RU.UTF-8'],
            extract: $extract,
            compress: $compress,
        );

        self::assertSame('pass', $options->getPassword());
        self::assertSame(5, $options->getCompress()->getCompressionLevel());
        self::assertSame(ExtractOptions::OVERWRITE_MODE_U, $options->getExtract()->getOverwriteMode());
        self::assertSame($solidMode, $options->getCompress()->getSolidMode());
        self::assertTrue($options->getCompress()->isEncryptFilenames());
        self::assertSame('Windows-1251', $options->getFallbackEncoding()->getEncoding());
        self::assertSame(['LC_ALL' => 'ru_RU.UTF-8'], $options->getEnv());
        self::assertSame($extract, $options->getExtract());
        self::assertSame($compress, $options->getCompress());
    }

    public function testInvalidOverwriteMode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ExtractOptions('invalid');
    }

    public function testInvalidCompressionLevel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CompressOptions(compressionLevel: 10);
    }

    public function testGetOptions(): void
    {
        $options = new Options(password: 'pass');
        $obj = new Archive7z('fake.7z', $options);

        self::assertSame($options, $obj->getOptions());
    }
}
