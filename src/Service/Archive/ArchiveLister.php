<?php

declare(strict_types=1);

namespace Archive7z\Service\Archive;

use Archive7z\Config\Options;
use Archive7z\Exception\ProcessFailedException;
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
        private readonly Options $options,
        private readonly NameMapping $nameMapping,
    ) {
    }

    /**
     * @param bool $exact treat the mask as a single exact name instead of a wildcard pattern
     *
     * @throws ProcessFailedException
     *
     * @return array<int, array<string, string|list<string>>>
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
            $maskFile?->delete();
        }

        $parser = new Parser(\explode("\n", $this->nameMapping->decodeOutput($this->processFactory->textOutput($process))));
        $entries = $parser->parseEntries($limit);

        if (null === $pathMask) {
            $entries = $this->withContainerNames($entries);
        }

        return $this->withSyntheticName($entries);
    }

    /**
     * @throws ProcessFailedException
     */
    public function info(): Info
    {
        $process = $this->processFactory->create('l', \array_merge(
            ['-slt'],
            $this->processFactory->decorateExtract($this->options),
            [self::INFO_MASK],
        ));
        $this->processFactory->run($process);

        $out = \explode("\n", $this->nameMapping->decodeOutput($this->processFactory->textOutput($process)));

        return new Info(new Parser($out));
    }

    /**
     * @param array<int, array<string, string|list<string>>> $entries
     *
     * @return array<int, array<string, string|list<string>>>
     */
    private function withContainerNames(array $entries): array
    {
        if ([] === $entries || $this->nameMapping->isAsIs()) {
            return $entries;
        }

        $sources = [];
        foreach ($entries as $entry) {
            $path = $entry['Path'] ?? null;
            $sources[] = \is_string($path) ? $path : '';
        }

        $names = $this->nameMapping->container()->names($sources);
        if (\count($names) !== \count($entries)) {
            return $entries;
        }

        foreach ($entries as $index => $entry) {
            $entries[$index]['Path'] = $names[$index];
        }

        return $entries;
    }

    /**
     * Names the single entry of a bare compression stream (xz/bzip2/lzma/zstd).
     *
     * For those containers 7-Zip prints no "Path" at all, so the entry name is
     * the archive file name without its final extension, exactly the name
     * 7-Zip itself uses for matching and extraction (see
     * {@see NameMapping::syntheticName()}).
     *
     * @param array<int, array<string, string|list<string>>> $entries
     *
     * @return array<int, array<string, string|list<string>>>
     */
    private function withSyntheticName(array $entries): array
    {
        if (1 !== \count($entries)) {
            return $entries;
        }

        $index = \array_key_first($entries);
        $path = $entries[$index]['Path'] ?? null;
        if (\is_string($path) && '' !== $path) {
            return $entries;
        }

        $entries[$index]['Path'] = $this->nameMapping->syntheticName();

        return $entries;
    }
}
