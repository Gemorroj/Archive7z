<?php

declare(strict_types=1);

namespace Archive7z\Tests\Service\Process;

use Archive7z\Config\ArchiveOptions;
use Archive7z\Config\FallbackEncoding;
use Archive7z\Config\SolidMode;
use Archive7z\Service\Process\ProcessFactory;
use PHPUnit\Framework\TestCase;

class ProcessFactoryTest extends TestCase
{
    private function factory(bool $isWindows = false): ProcessFactory
    {
        return new ProcessFactory('/usr/bin/7z', 'archive.7z', 30.0, $isWindows);
    }

    public function testCreate(): void
    {
        $commandLine = $this->factory()->create('l', ['-slt'])->getCommandLine();

        self::assertStringContainsString('archive.7z', $commandLine);
        self::assertStringContainsString('-slt', $commandLine);
    }

    public function testDecorateExtractNoPassword(): void
    {
        $args = $this->factory()->decorateExtract(new ArchiveOptions());

        self::assertContains('-y', $args);
        self::assertContains('-p ', $args);
    }

    public function testDecorateExtractPasswordAndCodepage(): void
    {
        $options = new ArchiveOptions(password: 'pass', fallbackEncoding: FallbackEncoding::codepage('Windows-1251'));
        $args = $this->factory()->decorateExtract($options);

        self::assertContains('-ppass', $args);
        self::assertContains('-mcp=1251', $args);
    }

    public function testDecorateExtractWindowsCharset(): void
    {
        $args = $this->factory(true)->decorateExtract(new ArchiveOptions());

        self::assertContains('-sccUTF-8', $args);
        self::assertContains('-scsUTF-8', $args);
    }

    public function testDecorateCompressSolidMode(): void
    {
        $args = $this->factory()->decorateCompress(new ArchiveOptions(solidMode: SolidMode::off()));

        self::assertContains('-ms=off', $args);
    }

    public function testCreateWithEnv(): void
    {
        $factory = new ProcessFactory('/usr/bin/7z', 'archive.7z', 30.0, false, ['LC_ALL' => 'ru_RU.UTF-8']);

        self::assertSame(['LC_ALL' => 'ru_RU.UTF-8'], $factory->create('l', [])->getEnv());
    }
}
