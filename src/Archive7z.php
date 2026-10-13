<?php

declare(strict_types=1);

namespace Archive7z;

use Archive7z\Config\ArchiveOptions;
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
    private ArchiveOptions $options;

    private ProcessFactory $processFactory;

    private NameMapping $nameMapping;

    private ArchiveLister $lister;

    private ArchiveExtractor $extractor;

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
     */
    public static function get7zipInformation(?string $binary7z = null): string
    {
        $binary7z = ExecutableFinder::find($binary7z);

        $process = new \Symfony\Component\Process\Process([
            \str_replace('\\', '/', $binary7z),
            'i',
        ]);
        $process->mustRun();

        return $process->getOutput();
    }

    public function getOptions(): ArchiveOptions
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
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException|RuntimeException
     */
    public function extract(?string $directory = null): void
    {
        $this->extractor->extract($this->resolveOutputDirectory($directory));
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException|RuntimeException
     */
    public function extractEntry(string $path, ?string $directory = null): void
    {
        $this->extractor->extract($this->resolveOutputDirectory($directory), $path);
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
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
        $list = [];
        foreach ($this->lister->entries($pathMask, $limit) as $v) {
            $list[] = new Entry($this, $v);
        }

        return $list;
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
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

        $this->processFactory->run($process);
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
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
     * 7-zip >= 7.30 ( http://sourceforge.net/p/p7zip/discussion/383043/thread/f54fe89a/ ).
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function renameEntry(string $pathSrc, string $pathDest): void
    {
        $windows = $this->processFactory->isWindows();

        // Windows receives a Unicode command line and decodes archive names
        // itself (via -mcp), so UTF-8 is passed as is. On *nix the command line
        // is byte-oriented and compared against the archive's stored bytes, so
        // -mcp must be omitted (it would make 7-Zip decode names and break it).
        $names = $windows
            ? [$pathSrc, $pathDest]
            : [$this->nameMapping->encode($pathSrc), $this->nameMapping->encode($pathDest)];

        $decorate = $this->processFactory->decorateExtract($this->options);
        if (!$windows) {
            $decorate = \array_values(\array_filter($decorate, static function (string $argument): bool {
                return !\str_starts_with($argument, '-mcp');
            }));
        }

        $process = $this->processFactory->create('rn', \array_merge($names, $decorate));

        $this->processFactory->run($process);
    }

    /**
     * Is valid archive?
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function isValid(): bool
    {
        $process = $this->processFactory->create('t', $this->processFactory->decorateExtract($this->options));

        return \str_contains($this->processFactory->run($process)->getOutput(), 'Everything is Ok');
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

        $output = $this->processFactory->run($process)->getOutput();

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
}
