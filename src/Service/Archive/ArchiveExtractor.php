<?php

declare(strict_types=1);

namespace Archive7z\Service\Archive;

use Archive7z\Config\ArchiveOptions;
use Archive7z\Service\Namer\NameMapping;
use Archive7z\Service\Namer\NameRepairer;
use Archive7z\Service\Parser\Parser;
use Archive7z\Service\Process\ProcessFactory;

/**
 * Extracts the archive through the 7-Zip CLI and repairs the written names.
 */
final class ArchiveExtractor
{
    public function __construct(
        private readonly ProcessFactory $processFactory,
        private readonly ArchiveOptions $options,
        private readonly NameMapping $nameMapping,
        private readonly NameRepairer $nameRepairer,
    ) {
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function extract(string $directory, ?string $pathMask = null): void
    {
        $mask = null === $pathMask ? null : $this->nameMapping->encode($pathMask);

        $this->run($directory, $mask);
        $this->repair($directory, $mask);
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    private function run(string $directory, ?string $mask): void
    {
        [$arguments, $maskFile] = $this->processFactory->withMask(
            \array_merge(
                [$this->options->getOverwriteMode(), '-o'.$directory],
                $this->processFactory->decorateExtract($this->options),
            ),
            $this->options,
            $mask,
        );

        $process = $this->processFactory->create('x', $arguments);

        try {
            $this->processFactory->run($process);
        } finally {
            if (null !== $maskFile) {
                $maskFile->delete();
            }
        }
    }

    /**
     * Renames the extracted entries to the names read from the container.
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    private function repair(string $directory, ?string $mask): void
    {
        if (!$this->nameMapping->requiresRepair()) {
            return;
        }

        [$arguments, $maskFile] = $this->processFactory->withMask(
            \array_merge(['-slt'], $this->processFactory->decorateExtract($this->options)),
            $this->options,
            $mask,
        );

        $process = $this->processFactory->create('l', $arguments);

        try {
            $this->processFactory->run($process);
        } finally {
            if (null !== $maskFile) {
                $maskFile->delete();
            }
        }

        // Raw output: these are the names 7-Zip actually wrote to disk.
        $parser = new Parser(\explode(\PHP_EOL, $process->getOutput()));

        $sources = [];
        foreach ($parser->parseEntries() as $entry) {
            $path = $entry['Path'] ?? null;
            if (\is_string($path) && '' !== $path) {
                $sources[] = $path;
            }
        }
        if ([] === $sources) {
            return;
        }

        $renames = $this->nameMapping->targets($sources);
        if ([] === $renames) {
            return;
        }

        $this->nameRepairer->rename($directory, $renames);
    }
}
