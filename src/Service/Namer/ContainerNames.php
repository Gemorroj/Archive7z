<?php

declare(strict_types=1);

namespace Archive7z\Service\Namer;

use Archive7z\Config\FallbackEncoding;
use Archive7z\Service\Container\RawName;

/**
 * Snapshot of the archive container names, read once for a single operation.
 *
 * Groups the queries that decide how the entry names must be decoded (whether
 * a repair is needed, how to decode a listing, which renames to apply) so they
 * all share one container read instead of re-reading the archive each time.
 * Because it is short-lived, it never goes stale when the archive is modified
 * by a later operation.
 */
final readonly class ContainerNames
{
    /**
     * @param RawName[]|null $names null when the format has no container reader
     */
    public function __construct(
        private FallbackEncoding $fallbackEncoding,
        private ?array $names,
    ) {
    }

    /**
     * Whether the extracted names can differ from the decoded ones.
     */
    public function requiresRepair(): bool
    {
        if ($this->fallbackEncoding->isAsIs()) {
            return false;
        }

        if (null === $this->names) {
            return true;
        }

        if (NameEncoder::resolve($this->fallbackEncoding, $this->sample())->isAsIs()) {
            return false;
        }

        foreach ($this->names as $rawName) {
            if (!$rawName->utf8 && !\mb_check_encoding($rawName->value, 'UTF-8')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Decodes the entry names, taking them from the container when possible.
     *
     * @param string[] $sourceNames names as 7-Zip produced them
     *
     * @return string[]
     */
    public function names(array $sourceNames): array
    {
        if (null !== $this->names && \count($this->names) === \count($sourceNames)) {
            $encoder = NameEncoder::resolve($this->fallbackEncoding, $this->sample());

            $names = [];
            foreach ($this->names as $rawName) {
                $names[] = $rawName->utf8 ? $rawName->value : $encoder->decode($rawName->value);
            }

            return $names;
        }

        $encoder = NameEncoder::resolve($this->fallbackEncoding, \implode("\n", $sourceNames));

        $names = [];
        foreach ($sourceNames as $sourceName) {
            $names[] = $encoder->decode($sourceName);
        }

        return $names;
    }

    /**
     * @param string[] $sourceNames
     *
     * @return array<int, array{from: string, to: string}>
     */
    public function targets(array $sourceNames): array
    {
        $names = $this->names($sourceNames);

        $renames = [];
        foreach ($sourceNames as $index => $from) {
            $to = $names[$index];
            if ($to !== $from) {
                $renames[] = ['from' => $from, 'to' => $to];
            }
        }

        return $renames;
    }

    /**
     * Raw listing used to detect the encoding; null when there is no container.
     */
    public function sample(): ?string
    {
        if (null === $this->names) {
            return null;
        }

        $sample = '';
        foreach ($this->names as $rawName) {
            $sample .= $rawName->value."\n";
        }

        return $sample;
    }
}
