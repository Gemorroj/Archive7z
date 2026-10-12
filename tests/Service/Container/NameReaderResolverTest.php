<?php

declare(strict_types=1);

namespace Archive7z\Tests\Service\Container;

use Archive7z\Service\Container\GzipNameReader;
use Archive7z\Service\Container\NameReaderResolver;
use Archive7z\Service\Container\TarNameReader;
use Archive7z\Service\Container\ZipNameReader;
use PHPUnit\Framework\TestCase;

class NameReaderResolverTest extends TestCase
{
    public function testReaderFor(): void
    {
        $resolver = new NameReaderResolver();

        self::assertInstanceOf(ZipNameReader::class, $resolver->readerFor('zip'));
        self::assertInstanceOf(TarNameReader::class, $resolver->readerFor('tar'));
        self::assertInstanceOf(GzipNameReader::class, $resolver->readerFor('gzip'));
        self::assertNull($resolver->readerFor('7z'));
    }

    public function testResolve(): void
    {
        $resolver = new NameReaderResolver();
        $fixtures = \dirname(__DIR__, 2).'/fixtures';

        self::assertInstanceOf(ZipNameReader::class, $resolver->resolve($fixtures.'/zip/windows/7zip-18.05/sample.zip'));
        self::assertInstanceOf(TarNameReader::class, $resolver->resolve($fixtures.'/tar/windows/7zip-18.05/sample.tar'));
        self::assertInstanceOf(GzipNameReader::class, $resolver->resolve($fixtures.'/gzip/windows/7zip-18.05/sample.tgz'));
        self::assertNull($resolver->resolve($fixtures.'/7z/windows/7zip-18.05/sample.7z'));
    }
}
