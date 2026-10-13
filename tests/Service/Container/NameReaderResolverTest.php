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
    public function testResolve(): void
    {
        $resolver = new NameReaderResolver();
        $fixtures = \dirname(__DIR__, 2).'/fixtures';

        self::assertInstanceOf(ZipNameReader::class, $resolver->resolve($fixtures.'/zip/windows/7zip-18.05/sample.zip'));
        self::assertInstanceOf(TarNameReader::class, $resolver->resolve($fixtures.'/tar/windows/7zip-18.05/sample.tar'));
        self::assertInstanceOf(GzipNameReader::class, $resolver->resolve($fixtures.'/gzip/windows/7zip-18.05/sample.tgz'));
        self::assertNull($resolver->resolve($fixtures.'/7z/windows/7zip-18.05/sample.7z'));
    }

    public function testResolveSelfExtractingZip(): void
    {
        $resolver = new NameReaderResolver();
        $zip = (string) \file_get_contents(\dirname(__DIR__, 2).'/fixtures/zip/windows/7zip-18.05/sample.zip');

        $sfx = \tempnam(\sys_get_temp_dir(), 'archive7z_sfx_');
        \file_put_contents($sfx, 'MZ'.\str_repeat("\0", 512).$zip);

        $notSfx = \tempnam(\sys_get_temp_dir(), 'archive7z_no_');
        \file_put_contents($notSfx, 'XX'.\str_repeat("\0", 512).$zip);

        try {
            self::assertInstanceOf(ZipNameReader::class, $resolver->resolve($sfx));
            // Without the executable stub signature it must not be a ZIP.
            self::assertNull($resolver->resolve($notSfx));
        } finally {
            \unlink($sfx);
            \unlink($notSfx);
        }
    }
}
