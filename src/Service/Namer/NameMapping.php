<?php

declare(strict_types=1);

namespace Archive7z\Service\Namer;

use Archive7z\Config\FallbackEncoding;
use Archive7z\Exception\RuntimeException;
use Archive7z\Service\Container\NameReaderResolver;
use Archive7z\Service\Container\RawName;

/**
 * Single point of entry-name translation.
 *
 * Reads the authoritative names straight from the container for the formats
 * whose names 7-Zip decodes in a platform dependent way (zip/tar/gzip) and
 * falls back to decoding the 7-Zip output otherwise. Used for listing,
 * mask/path encoding and extraction repair, so the configured fallback
 * encoding behaves the same way everywhere.
 */
final readonly class NameMapping
{
    public function __construct(
        private FallbackEncoding $fallbackEncoding,
        private NameReaderResolver $nameReaderResolver,
        private string $filename,
    ) {
    }

    public function isAsIs(): bool
    {
        return $this->fallbackEncoding->isAsIs();
    }

    /**
     * Decodes the 7-Zip text output (fallback for the formats without reader).
     */
    public function decodeOutput(string $output): string
    {
        // Always go through the encoder: even asIs must reverse 7-Zip escapes.
        return NameEncoder::resolve($this->fallbackEncoding, $output)->decode($output);
    }

    /**
     * Encodes a user supplied UTF-8 name/path into the archive name encoding.
     */
    public function encode(string $name): string
    {
        // The sample is only needed to detect the encoding; as-is and an explicit
        // codepage ignore it, so skip reading the archive in those cases.
        $sample = $this->fallbackEncoding->isDetect() ? $this->containerSample() : null;

        return NameEncoder::resolve($this->fallbackEncoding, $sample)->encode($name);
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
        $container = $this->containerNames();
        if (null !== $container && \count($container) === \count($sourceNames)) {
            $encoder = NameEncoder::resolve($this->fallbackEncoding, $this->sample($container));

            $names = [];
            foreach ($container as $rawName) {
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
     * Whether the extracted names can differ from the decoded ones.
     *
     * For container formats (zip/tar/gzip) this is decided from the container
     * itself, so an archive with already-UTF-8 names never triggers the extra
     * listing/repair pass. For other formats it is unknown until the archive is
     * listed, so it returns true.
     */
    public function requiresRepair(): bool
    {
        if ($this->fallbackEncoding->isAsIs()) {
            return false;
        }

        $container = $this->containerNames();
        if (null === $container) {
            return true;
        }

        if (NameEncoder::resolve($this->fallbackEncoding, $this->sample($container))->isAsIs()) {
            return false;
        }

        foreach ($container as $rawName) {
            if (!$rawName->utf8 && !\mb_check_encoding($rawName->value, 'UTF-8')) {
                return true;
            }
        }

        return false;
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
     * @return RawName[]|null
     */
    private function containerNames(): ?array
    {
        $reader = $this->nameReaderResolver->resolve($this->filename);
        if (null === $reader) {
            return null;
        }

        try {
            $names = $reader->read($this->filename);
        } catch (RuntimeException) {
            return null;
        }

        return [] === $names ? null : $names;
    }

    private function containerSample(): ?string
    {
        $container = $this->containerNames();

        return null === $container ? null : $this->sample($container);
    }

    /**
     * @param RawName[] $rawNames
     */
    private function sample(array $rawNames): string
    {
        $sample = '';
        foreach ($rawNames as $rawName) {
            $sample .= $rawName->value."\n";
        }

        return $sample;
    }
}
