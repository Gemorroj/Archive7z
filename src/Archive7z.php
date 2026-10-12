<?php

declare(strict_types=1);

namespace Archive7z;

use Archive7z\Config\ArchiveOptions;
use Archive7z\Exception\RuntimeException;
use Archive7z\Model\Entry;
use Archive7z\Model\Info;
use Archive7z\Service\Encoder\NameEncoder;
use Archive7z\Service\Encoder\NameRepairer;
use Archive7z\Service\Parser\Parser;
use Archive7z\Service\Process\ExecutableFinder;
use Archive7z\Service\Process\ProcessFactory;
use Symfony\Component\Process\Process;

final class Archive7z
{
    private ArchiveOptions $options;

    private ProcessFactory $processFactory;

    private NameRepairer $nameRepairer;

    private ?NameEncoder $nameEncoder = null;

    /**
     * @param string              $filename 7z archive filename
     * @param ArchiveOptions|null $options  Archive configuration
     * @param string|null         $binary7z 7-zip binary path
     * @param float|null          $timeout  Timeout of system process
     *
     * @throws RuntimeException
     */
    public function __construct(
        string $filename,
        ?ArchiveOptions $options = null,
        ?string $binary7z = null,
        ?float $timeout = 60.0,
    ) {
        $this->options = $options ?? new ArchiveOptions();
        $this->processFactory = new ProcessFactory(
            ExecutableFinder::find($binary7z),
            $filename,
            $timeout,
            ExecutableFinder::isWindows(),
            $this->options->getEnv(),
        );
        $this->nameRepairer = new NameRepairer($this->options->getOverwriteMode());
    }

    /**
     * @param string|null $binary7z 7-zip binary path
     *
     * @throws RuntimeException
     */
    public static function get7zipInformation(?string $binary7z = null): string
    {
        $binary7z = ExecutableFinder::find($binary7z);

        $cmd = [
            \str_replace('\\', '/', $binary7z),
            'i',
        ];

        $process = new Process($cmd);
        $process->mustRun();

        return $process->getOutput();
    }

    public function getOptions(): ArchiveOptions
    {
        return $this->options;
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     *
     * @return string[]
     */
    private function getRawListing(?string $pathMask): array
    {
        $process = $this->processFactory->create('l', \array_merge(
            ['-slt'],
            $this->processFactory->decorateExtract($this->options),
            null !== $pathMask ? [$pathMask] : [],
        ));

        $this->execute($process);

        return \explode(\PHP_EOL, $process->getOutput());
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    private function nameEncoder(): NameEncoder
    {
        if (null === $this->nameEncoder) {
            $fallbackEncoding = $this->options->getFallbackEncoding();
            $sample = $fallbackEncoding->isDetect()
                ? \implode(\PHP_EOL, $this->getRawListing(null))
                : null;

            $this->nameEncoder = NameEncoder::resolve($fallbackEncoding, $sample);
        }

        return $this->nameEncoder;
    }

    private function convertOutput(string $output): string
    {
        return $this->nameEncoder()->decode($output);
    }

    private function encodeName(string $name): string
    {
        return $this->nameEncoder()->encode($name);
    }

    /**
     * @throws RuntimeException
     */
    private function resolveOutputDirectory(?string $directory): string
    {
        if (null === $directory) {
            return '.';
        }

        $outputDirectory = \realpath($directory);

        if (false === $outputDirectory) {
            throw new RuntimeException('Output directory is not available');
        }

        if (!\is_writable($outputDirectory)) {
            throw new RuntimeException('Output directory is not writable');
        }

        return $outputDirectory;
    }

    /**
     * Renames extracted entries whose names were not valid UTF-8 on disk.
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    private function repairExtractedNames(string $directory, ?string $pathMask): void
    {
        if ($this->options->getFallbackEncoding()->isAsIs()) {
            return;
        }

        $rawLines = $this->getRawListing($pathMask);
        $nameEncoder = NameEncoder::resolve($this->options->getFallbackEncoding(), \implode(\PHP_EOL, $rawLines));
        if ($nameEncoder->isAsIs()) {
            return;
        }

        $paths = [];
        $parser = new Parser($rawLines);
        foreach ($parser->parseEntries() as $data) {
            $path = $data['Path'] ?? null;
            if (\is_string($path)) {
                $paths[] = $path;
            }
        }

        $this->nameRepairer->repair($directory, $paths, $nameEncoder);
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException|RuntimeException
     */
    public function extract(?string $directory = null): void
    {
        $directory = $this->resolveOutputDirectory($directory);

        $process = $this->processFactory->create('x', \array_merge([
            $this->options->getOverwriteMode(),
            '-o'.$directory,
        ], $this->processFactory->decorateExtract($this->options)));

        $this->execute($process);

        $this->repairExtractedNames($directory, null);
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException|RuntimeException
     */
    public function extractEntry(string $path, ?string $directory = null): void
    {
        $directory = $this->resolveOutputDirectory($directory);
        $path = $this->encodeName($path);

        $process = $this->processFactory->create('x', \array_merge([
            $this->options->getOverwriteMode(),
            '-o'.$directory,
        ], $this->processFactory->decorateExtract($this->options), [$path]));

        $this->execute($process);

        $this->repairExtractedNames($directory, $path);
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function getContent(string $path): string
    {
        $path = $this->encodeName($path);

        $process = $this->processFactory->create('x', \array_merge([
            '-so',
            $path,
        ], $this->processFactory->decorateExtract($this->options)));

        return $this->execute($process)->getOutput();
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function getEntry(string $path): ?Entry
    {
        $entries = $this->getEntries($path, 1);

        return $entries ? $entries[0] : null;
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     *
     * @return Entry[]
     */
    public function getEntries(?string $pathMask = null, ?int $limit = null): array
    {
        if (null !== $pathMask) {
            $pathMask = $this->encodeName($pathMask);
        }

        $process = $this->processFactory->create('l', \array_merge(
            ['-slt'],
            $this->processFactory->decorateExtract($this->options),
            null !== $pathMask ? [$pathMask] : [],
        ));

        $this->execute($process);

        $out = \explode(\PHP_EOL, $this->convertOutput($process->getOutput()));

        $list = [];
        $parser = new Parser($out);
        foreach ($parser->parseEntries($limit) as $v) {
            $list[] = new Entry($this, $v);
        }

        return $list;
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function getInfo(): Info
    {
        $process = $this->processFactory->create('l', \array_merge([
            '-slt',
        ], $this->processFactory->decorateExtract($this->options), ['Archive7z fake path']));

        $this->execute($process);

        $out = \explode(\PHP_EOL, $this->convertOutput($process->getOutput()));

        $parser = new Parser($out);

        return new Info($parser);
    }

    /**
     * 7-zip >= 7.25.
     *
     * @param bool $storePath store real filesystem path in archive
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException|RuntimeException
     */
    public function addEntry(string $path, bool $storePath = false): void
    {
        $args = [];
        $args[] = '-mx='.$this->options->getCompressionLevel();

        if ($storePath) {
            $args[] = '-spf';
            $args[] = $path;
        } else {
            $realPath = \realpath($path);
            if (false === $realPath) {
                throw new RuntimeException('Can not resolve absolute path for "'.$path.'"');
            }

            if (\is_dir($realPath)) {
                $realPath .= '/*';
            }

            $args[] = $realPath;
        }

        $process = $this->processFactory->create('a', \array_merge($args, $this->processFactory->decorateCompress($this->options)));

        $this->execute($process);
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function delEntry(string $path): void
    {
        $path = $this->encodeName($path);

        $process = $this->processFactory->create('d', \array_merge([
            $path,
        ], $this->processFactory->decorateExtract($this->options)));

        $this->execute($process);
    }

    /**
     * 7-zip >= 7.30 ( http://sourceforge.net/p/p7zip/discussion/383043/thread/f54fe89a/ ).
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function renameEntry(string $pathSrc, string $pathDest): void
    {
        $pathSrc = $this->encodeName($pathSrc);
        $pathDest = $this->encodeName($pathDest);

        $process = $this->processFactory->create('rn', \array_merge([
            $pathSrc,
            $pathDest,
        ], $this->processFactory->decorateExtract($this->options)));

        $this->execute($process);
    }

    /**
     * Is valid archive?
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function isValid(): bool
    {
        $process = $this->processFactory->create('t', $this->processFactory->decorateExtract($this->options));

        $this->execute($process);

        return \str_contains($process->getOutput(), 'Everything is Ok');
    }

    /**
     * List archive warnings.
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     *
     * @return string[]
     */
    public function getWarnings(): array
    {
        $process = $this->processFactory->create('t', $this->processFactory->decorateExtract($this->options));

        $this->execute($process);

        $output = $process->getOutput();

        $pos = \strpos($output, \PHP_EOL.'--');
        if (false === $pos) {
            return [];
        }
        $header = \substr($output, 0, $pos);
        $warningsHeader = \PHP_EOL.'WARNINGS:';
        $warningsPos = \strrpos($header, $warningsHeader);
        if (false === $warningsPos) {
            return [];
        }

        $warningsStr = \substr($header, $warningsPos + \strlen($warningsHeader));
        $warningsStr = \trim($warningsStr);

        return \explode(\PHP_EOL, $warningsStr);
    }

    /**
     * Exit codes
     * 0 - Normal (no errors or warnings detected)
     * 1 - Warning (Non fatal error(s)). For example, some files cannot be read during compressing. So they were not compressed
     * 2 - Fatal error
     * 7 - Bad command line parameters
     * 8 - Not enough memory for operation
     * 255 - User stopped the process with control-C (or similar).
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    private function execute(Process $process): Process
    {
        return $this->processFactory->run($process);
    }
}
