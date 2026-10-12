<?php

declare(strict_types=1);

namespace Archive7z\Service\Container;

final class NameReaderResolver
{
    public function readerFor(string $type): ?NameReaderInterface
    {
        return match ($type) {
            'zip' => new ZipNameReader(),
            'tar' => new TarNameReader(),
            'gzip' => new GzipNameReader(),
            default => null,
        };
    }

    /**
     * Detects the container format by its magic bytes.
     */
    public function resolve(string $filename): ?NameReaderInterface
    {
        $header = @\file_get_contents($filename, false, null, 0, 512);
        if (false === $header) {
            return null;
        }

        if (\str_starts_with($header, "PK\x03\x04") || \str_starts_with($header, "PK\x05\x06")) {
            return new ZipNameReader();
        }

        if (\str_starts_with($header, "\x1f\x8b")) {
            return new GzipNameReader();
        }

        if ('ustar' === \substr($header, 257, 5)) {
            return new TarNameReader();
        }

        return null;
    }
}
