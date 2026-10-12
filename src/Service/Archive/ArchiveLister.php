<?php

declare(strict_types=1);

namespace Archive7z\Service\Archive;

use Archive7z\Config\ArchiveOptions;
use Archive7z\Model\Info;
use Archive7z\Service\Namer\NameMapping;
use Archive7z\Service\Parser\Parser;
use Archive7z\Service\Process\ProcessFactory;

/**
 * Lists the archive contents through the 7-Zip CLI and maps the entry names.
 */
final class ArchiveLister
{
    public function __construct(
        private readonly ProcessFactory $processFactory,
        private readonly ArchiveOptions $options,
        private readonly NameMapping $nameMapping,
    ) {
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     *
     * @return array<int, array<string, string|string[]>>
     */
    public function entries(?string $pathMask = null, ?int $limit = null): array
    {
        $mask = null === $pathMask ? null : $this->nameMapping->encode($pathMask);

        $process = $this->processFactory->create('l', \array_merge(
            ['-slt'],
            $this->processFactory->decorateExtract($this->options),
            null !== $mask ? [$mask] : [],
        ));
        $this->processFactory->run($process);

        $parser = new Parser(\explode(\PHP_EOL, $this->nameMapping->decodeOutput($process->getOutput())));
        $entries = $parser->parseEntries($limit);

        return null === $pathMask ? $this->withContainerNames($entries) : $entries;
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function info(): Info
    {
        $process = $this->processFactory->create('l', \array_merge(
            ['-slt'],
            $this->processFactory->decorateExtract($this->options),
            ['Archive7z fake path'],
        ));
        $this->processFactory->run($process);

        $out = \explode(\PHP_EOL, $this->nameMapping->decodeOutput($process->getOutput()));

        return new Info(new Parser($out));
    }

    /**
     * @param array<int, array<string, string|string[]>> $entries
     *
     * @return array<int, array<string, string|string[]>>
     */
    private function withContainerNames(array $entries): array
    {
        if ($this->nameMapping->isAsIs() || [] === $entries) {
            return $entries;
        }

        $sources = [];
        foreach ($entries as $entry) {
            $path = $entry['Path'] ?? null;
            $sources[] = \is_string($path) ? $path : '';
        }

        $names = $this->nameMapping->names($sources);
        if (\count($names) !== \count($entries)) {
            return $entries;
        }

        foreach ($entries as $index => $entry) {
            $entries[$index]['Path'] = $names[$index];
        }

        return $entries;
    }
}
