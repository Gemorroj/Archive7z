<?php

declare(strict_types=1);

namespace Archive7z;

use Archive7z\Config\Options;
use Archive7z\Exception\ProcessFailedException;
use Archive7z\Exception\ProcessRuntimeException;
use Archive7z\Exception\ProcessTimedOutException;
use Archive7z\Exception\RuntimeException;
use Archive7z\Model\Entry;
use Archive7z\Model\Info;
use Archive7z\Service\Archive\ArchiveExtractor;
use Archive7z\Service\Archive\ArchiveLister;
use Archive7z\Service\Container\NameReaderResolver;
use Archive7z\Service\Namer\NameMapping;
use Archive7z\Service\Namer\NameRepairer;
use Archive7z\Service\Process\ExecutableFinder;
use Archive7z\Service\Process\ProcessFactory;

final class Archive7z
{
    private Options $options;

    private string $filename;

    private ProcessFactory $processFactory;

    private NameMapping $nameMapping;

    private ArchiveLister $lister;

    private ArchiveExtractor $extractor;

    /**
     * @param string       $filename 7z archive filename
     * @param Options|null $options  Archive configuration
     * @param string|null  $binary7z 7-zip binary path
     * @param float|null   $timeout  Timeout of system process
     *
     * @throws RuntimeException
     */
    public function __construct(
        string $filename,
        ?Options $options = null,
        ?string $binary7z = null,
        ?float $timeout = 60.0,
    ) {
        $this->options = $options ?? new Options();
        $this->filename = $filename;
        $this->processFactory = new ProcessFactory(
            ExecutableFinder::find($binary7z),
            $filename,
            $timeout,
            $this->options->getEnv(),
        );
        $this->nameMapping = new NameMapping(
            $this->options->getFallbackEncoding(),
            new NameReaderResolver(),
            $filename,
        );
        $this->lister = new ArchiveLister($this->processFactory, $this->options, $this->nameMapping);
        $this->extractor = new ArchiveExtractor(
            $this->processFactory,
            $this->options,
            $this->nameMapping,
            new NameRepairer($this->options->getOverwriteMode()),
        );
    }

    /**
     * @param string|null $binary7z 7-zip binary path
     *
     * @throws RuntimeException
     * @throws ProcessFailedException
     * @throws ProcessRuntimeException
     * @throws ProcessTimedOutException
     */
    public static function get7zipInformation(?string $binary7z = null): string
    {
        $binary7z = ExecutableFinder::find($binary7z);

        $process = new \Symfony\Component\Process\Process([
            \str_replace('\\', '/', $binary7z),
            'i',
        ]);

        try {
            $process->mustRun();
        } catch (\Symfony\Component\Process\Exception\ProcessFailedException $e) {
            throw new ProcessFailedException($e->getProcess());
        } catch (\Symfony\Component\Process\Exception\ProcessTimedOutException $e) {
            throw new ProcessTimedOutException($e->getProcess(), $e->isIdleTimeout() ? ProcessTimedOutException::TYPE_IDLE : ProcessTimedOutException::TYPE_GENERAL);
        } catch (\Symfony\Component\Process\Exception\RuntimeException $e) {
            throw new ProcessRuntimeException($e->getMessage(), (int) $e->getCode(), $e);
        }

        return $process->getOutput();
    }

    public function getOptions(): Options
    {
        return $this->options;
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
     * @throws ProcessFailedException|RuntimeException
     */
    public function extract(?string $directory = null): void
    {
        $this->extractor->extract($this->resolveOutputDirectory($directory));
    }

    /**
     * @throws ProcessFailedException|RuntimeException
     */
    public function extractEntry(string $path, ?string $directory = null): void
    {
        $this->extractor->extract($this->resolveOutputDirectory($directory), $path);
    }

    /**
     * @throws ProcessFailedException
     */
    public function getContent(string $path): string
    {
        [$arguments, $maskFile] = $this->processFactory->withMask(
            // Single entry by exact name: do not expand wildcards.
            \array_merge(['-so', '-spd'], $this->processFactory->decorateExtract($this->options)),
            $this->options,
            $this->nameMapping->encode($path),
        );

        $process = $this->processFactory->create('x', $arguments);

        try {
            return $this->processFactory->run($process)->getOutput();
        } finally {
            if (null !== $maskFile) {
                $maskFile->delete();
            }
        }
    }

    /**
     * @throws ProcessFailedException
     */
    public function getEntry(string $path): ?Entry
    {
        $list = [];
        foreach ($this->lister->entries($path, 1, true) as $v) {
            $list[] = new Entry($this, $v);
        }

        return $list ? $list[0] : null;
    }

    /**
     * @throws ProcessFailedException
     *
     * @return list<Entry>
     */
    public function getEntries(?string $pathMask = null, ?int $limit = null): array
    {
        $list = [];
        foreach ($this->lister->entries($pathMask, $limit) as $v) {
            $list[] = new Entry($this, $v);
        }

        return $list;
    }

    /**
     * @throws ProcessFailedException
     */
    public function getInfo(): Info
    {
        return $this->lister->info();
    }

    /**
     * 7-zip >= 7.25.
     *
     * @param bool $storePath store real filesystem path in archive
     *
     * @throws ProcessFailedException|RuntimeException
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

        $this->processFactory->run($process);
    }

    /**
     * @throws ProcessFailedException
     */
    public function delEntry(string $path): void
    {
        [$arguments, $maskFile] = $this->processFactory->withMask(
            $this->processFactory->decorateExtract($this->options),
            $this->options,
            $this->nameMapping->encode($path),
        );

        $process = $this->processFactory->create('d', $arguments);

        try {
            $this->processFactory->run($process);
        } finally {
            if (null !== $maskFile) {
                $maskFile->delete();
            }
        }
    }

    /**
     * @param list<string> $names
     * @param list<string> $decorate
     *
     * @throws ProcessFailedException
     */
    private function runRename(array $names, array $decorate): void
    {
        $process = $this->processFactory->create('rn', \array_merge($names, $decorate));

        $this->processFactory->run($process);
    }

    /**
     * Extract switches with the archive code page (-mcp) removed.
     *
     * @return list<string>
     */
    private function withoutCodepage(): array
    {
        return \array_values(\array_filter(
            $this->processFactory->decorateExtract($this->options),
            static function (string $argument): bool {
                return !\str_starts_with($argument, '-mcp');
            },
        ));
    }

    /**
     * Renames an entry inside the archive.
     *
     * @throws ProcessFailedException
     */
    public function renameEntry(string $pathSrc, string $pathDest): void
    {
        // Windows receives a Unicode command line and decodes archive names
        // itself (via -mcp), so UTF-8 is passed as is.
        if ('Windows' === \PHP_OS_FAMILY) {
            $this->runRename(
                [$pathSrc, $pathDest],
                $this->processFactory->decorateExtract($this->options),
            );

            return;
        }

        // On *nix the command-line-name decoding is build dependent: upstream
        // 7-Zip matches the raw bytes against the archive (so -mcp must be
        // omitted), while some distro builds (Debian/Ubuntu "7zip") force a
        // UTF-8 command line (so names stay UTF-8 and -mcp must be kept).
        // rn exits 0 both when it renames and when nothing matched, so both
        // forms are issued; the one that does not apply is a harmless no-op.
        $this->runRename(
            [$this->nameMapping->encode($pathSrc), $this->nameMapping->encode($pathDest)],
            $this->withoutCodepage(),
        );

        // With as-is names both forms are identical.
        if ($this->nameMapping->isAsIs()) {
            return;
        }

        $this->runRename(
            [$pathSrc, $pathDest],
            $this->processFactory->decorateExtract($this->options),
        );
    }

    /**
     * Whether 7-Zip validates the archive.
     *
     * The file is checked first: a missing or unreadable path is a precondition
     * error, not a property of the archive, so it is reported as an exception.
     * With the file present, false means 7-Zip could not validate the archive
     * (unknown/wrong format, corrupted data, encrypted without a password).
     *
     * @throws RuntimeException
     */
    public function isValid(): bool
    {
        if (!\is_file($this->filename)) {
            throw new RuntimeException('Archive file is not available');
        }

        if (!\is_readable($this->filename)) {
            throw new RuntimeException('Archive file is not readable');
        }

        $process = $this->processFactory->create('t', $this->processFactory->decorateExtract($this->options));

        try {
            $output = $this->processFactory->run($process)->getOutput();
        } catch (ProcessFailedException) {
            return false;
        }

        return \str_contains($output, 'Everything is Ok');
    }

    /**
     * List archive warnings.
     *
     * @throws ProcessFailedException
     *
     * @return list<string>
     */
    public function getWarnings(): array
    {
        $process = $this->processFactory->create('t', $this->processFactory->decorateExtract($this->options));

        $output = $this->processFactory->textOutput($this->processFactory->run($process));

        $pos = \strpos($output, "\n--");
        if (false === $pos) {
            return [];
        }
        $header = \substr($output, 0, $pos);
        $warningsHeader = "\nWARNINGS:";
        $warningsPos = \strrpos($header, $warningsHeader);
        if (false === $warningsPos) {
            return [];
        }

        $warningsStr = \substr($header, $warningsPos + \strlen($warningsHeader));
        $warningsStr = \trim($warningsStr);

        return \explode("\n", $warningsStr);
    }
}
