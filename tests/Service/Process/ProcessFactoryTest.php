<?php

declare(strict_types=1);

namespace Archive7z\Tests\Service\Process;

use Archive7z\Config\FallbackEncoding;
use Archive7z\Config\Options;
use Archive7z\Config\SolidMode;
use Archive7z\Exception\ExceptionInterface;
use Archive7z\Exception\ProcessRuntimeException;
use Archive7z\Exception\ProcessTimedOutException;
use Archive7z\Service\Process\MaskListFile;
use Archive7z\Service\Process\ProcessFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ProcessFactoryTest extends TestCase
{
    private function factory(): ProcessFactory
    {
        return new ProcessFactory('/usr/bin/7z', 'archive.7z', 30.0);
    }

    public function testCreate(): void
    {
        $commandLine = $this->factory()->create('l', ['-slt'])->getCommandLine();

        self::assertStringContainsString('archive.7z', $commandLine);
        self::assertStringContainsString('-slt', $commandLine);
    }

    public function testDecorateExtractNoPassword(): void
    {
        $args = $this->factory()->decorateExtract(new Options());

        self::assertContains('-y', $args);
        self::assertContains('-p ', $args);
    }

    public function testDecorateExtractPasswordAndCodepage(): void
    {
        $options = new Options(password: 'pass', fallbackEncoding: FallbackEncoding::codepage('Windows-1251'));

        $zip = \tempnam(\sys_get_temp_dir(), 'archive7z_');
        \file_put_contents($zip, "PK\x03\x04");

        try {
            $args = (new ProcessFactory('/usr/bin/7z', $zip, 30.0))->decorateExtract($options);
        } finally {
            \unlink($zip);
        }

        self::assertContains('-ppass', $args);
        self::assertContains('-mcp=1251', $args);
    }

    public function testDecorateExtractCodepageSkippedForNonZipTar(): void
    {
        // A handler other than zip/tar rejects "-mcp" and fails to open the
        // archive, so it must not be added.
        $options = new Options(fallbackEncoding: FallbackEncoding::codepage('Windows-1251'));

        self::assertNotContains('-mcp=1251', $this->factory()->decorateExtract($options));
    }

    public function testDecorateExtractCodepageForTar(): void
    {
        $options = new Options(fallbackEncoding: FallbackEncoding::codepage('Windows-1251'));

        $tar = \tempnam(\sys_get_temp_dir(), 'archive7z_');
        \file_put_contents($tar, \str_repeat("\0", 257).'ustar'.\str_repeat("\0", 100));

        try {
            $args = (new ProcessFactory('/usr/bin/7z', $tar, 30.0))->decorateExtract($options);
        } finally {
            \unlink($tar);
        }

        self::assertContains('-mcp=1251', $args);
    }

    public function testDecorateExtractWindowsCharset(): void
    {
        $args = $this->factory()->decorateExtract(new Options());

        if ('Windows' === \PHP_OS_FAMILY) {
            self::assertContains('-sccUTF-8', $args);
        } else {
            self::assertNotContains('-sccUTF-8', $args);
        }
    }

    public function testDecorateCompressSolidMode(): void
    {
        $args = $this->factory()->decorateCompress(new Options(solidMode: SolidMode::off()));

        self::assertContains('-ms=off', $args);
    }

    public function testTextOutputNormalizesOnlyCrlf(): void
    {
        $process = new Process([\PHP_BINARY, '-r', 'echo "a\r\nb\rc";']);
        $this->factory()->run($process);

        // CRLF becomes "\n"; a lone "\r" is content and is preserved.
        self::assertSame("a\nb\rc", $this->factory()->textOutput($process));
    }

    public function testWithMaskDirectly(): void
    {
        [$arguments, $file] = $this->factory()->withMask(['-slt'], new Options(), 'mask');

        self::assertSame(['-slt', 'mask'], $arguments);
        self::assertNull($file);
    }

    public function testWithMaskViaListFile(): void
    {
        $options = new Options(fallbackEncoding: FallbackEncoding::codepage('Windows-1251'));

        [$arguments, $file] = $this->factory()
            ->withMask(['-slt', '-scsUTF-8', '-sccUTF-8'], $options, 'mask');

        self::assertInstanceOf(MaskListFile::class, $file);
        self::assertNotContains('-scsUTF-8', $arguments);
        self::assertContains('-sccUTF-8', $arguments);
        self::assertContains('-scs1251', $arguments);
        self::assertContains('@'.$file->getPath(), $arguments);
        self::assertFileExists($file->getPath());

        $file->delete();
        self::assertFileDoesNotExist($file->getPath());
    }

    public function testRunToleratesWarningExitCode(): void
    {
        $process = new Process([\PHP_BINARY, '-r', 'exit(1);']);

        self::assertSame($process, $this->factory()->run($process));
        self::assertSame(1, $process->getExitCode());
    }

    public function testRunThrowsOnFatalExitCode(): void
    {
        $process = new Process([\PHP_BINARY, '-r', 'exit(2);']);

        $this->expectException(ProcessFailedException::class);
        $this->factory()->run($process);
    }

    public function testRunThrowsTypedTimeout(): void
    {
        $process = new Process([\PHP_BINARY, '-r', 'sleep(5);'], timeout: 0.1);

        try {
            $this->factory()->run($process);
            self::fail('Expected a timeout.');
        } catch (ProcessTimedOutException $e) {
            self::assertInstanceOf(ExceptionInterface::class, $e);
            self::assertSame($process, $e->getProcess());
        }
    }

    public function testRunThrowsTypedRuntimeFailure(): void
    {
        $process = new Process([\PHP_BINARY, '-r', 'echo 1;'], '/nonexistent/cwd');

        try {
            $this->factory()->run($process);
            self::fail('Expected a runtime failure.');
        } catch (ProcessRuntimeException $e) {
            self::assertInstanceOf(ExceptionInterface::class, $e);
        }
    }

    public function testCreateWithEnv(): void
    {
        $factory = new ProcessFactory('/usr/bin/7z', 'archive.7z', 30.0, ['LC_ALL' => 'ru_RU.UTF-8']);

        self::assertSame(['LC_ALL' => 'ru_RU.UTF-8'], $factory->create('l', [])->getEnv());
    }
}
