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
     * The name 7-Zip assigns to the only entry of a bare compression stream
     * (xz/bzip2/lzma/zstd): the archive file name without its final extension.
     *
     * Such containers store no name at all, and 7-Zip omits "Path" from the
     * listing, but it still matches that single entry by this synthesized name
     * ("7z x onefile.xz onefile" works, "7z x onefile.xz other" does not), so
     * the library must expose the same name to stay consistent with it.
     */
    public function syntheticName(): string
    {
        $basename = \basename($this->filename);
        $name = \pathinfo($basename, \PATHINFO_FILENAME);

        return '' === $name ? $basename : $name;
    }

    /**
     * Encodes a user supplied UTF-8 name/path into the archive name encoding.
     */
    public function encode(string $name): string
    {
        // The sample is only needed to detect the encoding; as-is and an explicit
        // codepage ignore it, so skip reading the archive in those cases.
        $sample = $this->fallbackEncoding->isDetect() ? $this->container()->sample() : null;

        return NameEncoder::resolve($this->fallbackEncoding, $sample)->encode($name);
    }

    /**
     * Reads the container names once and returns a snapshot for the queries
     * that need them (repair decision, decoding, rename targets).
     */
    public function container(): ContainerNames
    {
        return new ContainerNames($this->fallbackEncoding, $this->containerNames());
    }

    /**
     * @return list<RawName>|null
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
}
