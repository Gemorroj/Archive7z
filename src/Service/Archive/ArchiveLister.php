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
    /**
     * A positional include mask that matches no entry, so 7-Zip prints only the
     * archive header (the info block) and no entry list.
     */
    private const INFO_MASK = 'Archive7z fake path';

    public function __construct(
        private readonly ProcessFactory $processFactory,
        private readonly ArchiveOptions $options,
        private readonly NameMapping $nameMapping,
    ) {
    }

    /**
     * @param bool $exact treat the mask as a single exact name instead of a wildcard pattern
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     *
     * @return array<int, array<string, string|string[]>>
     */
    public function entries(?string $pathMask = null, ?int $limit = null, bool $exact = false): array
    {
        $mask = null === $pathMask ? null : $this->nameMapping->encode($pathMask);

        $base = ['-slt'];
        if ($exact && null !== $mask) {
            // Single entry by exact name: do not expand wildcards.
            $base[] = '-spd';
        }

        [$arguments, $maskFile] = $this->processFactory->withMask(
            \array_merge($base, $this->processFactory->decorateExtract($this->options)),
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
            [self::INFO_MASK],
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
