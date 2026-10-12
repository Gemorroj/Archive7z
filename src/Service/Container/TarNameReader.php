<?php

declare(strict_types=1);

namespace Archive7z\Service\Container;

use Archive7z\Exception\RuntimeException;

/**
 * Reads entry names from a TAR archive (ustar, GNU long names, PAX).
 *
 * PAX `path` records are UTF-8 by specification; other names are returned as
 * raw bytes for the caller to decode.
 */
final class TarNameReader implements NameReaderInterface
{
    private const BLOCK_SIZE = 512;
    private const NAME_OFFSET = 0;
    private const NAME_LENGTH = 100;
    private const SIZE_OFFSET = 124;
    private const SIZE_LENGTH = 12;
    private const TYPE_OFFSET = 156;
    private const PREFIX_OFFSET = 345;
    private const PREFIX_LENGTH = 155;

    public function read(string $filename): array
    {
        $handle = @\fopen($filename, 'r');
        if (false === $handle) {
            throw new RuntimeException('Can not open archive "'.$filename.'"');
        }

        try {
            return $this->readEntries($handle);
        } finally {
            \fclose($handle);
        }
    }

    /**
     * @param resource $handle
     *
     * @return RawName[]
     */
    private function readEntries($handle): array
    {
        $names = [];
        $longName = null;
        $paxPath = null;

        while (true) {
            $header = (string) \fread($handle, self::BLOCK_SIZE);
            if (\strlen($header) < self::BLOCK_SIZE) {
                break;
            }

            if ('' === \rtrim($header, "\0")) {
                break; // end-of-archive marker
            }

            $size = $this->size($header);
            $paddedSize = (int) (\ceil($size / self::BLOCK_SIZE) * self::BLOCK_SIZE);
            $type = $header[self::TYPE_OFFSET];

            if ('L' === $type) {
                $longName = \rtrim($this->readData($handle, $paddedSize, $size), "\0");
                continue;
            }

            if ('K' === $type) {
                \fseek($handle, $paddedSize, \SEEK_CUR);
                continue;
            }

            if ('x' === $type || 'g' === $type) {
                $data = $this->readData($handle, $paddedSize, $size);
                $path = $this->paxPath($data);
                if (null !== $path) {
                    $paxPath = $path;
                }
                continue;
            }

            $name = $this->name($header, $longName, $paxPath);
            $longName = null;
            if ('x' === $type) {
                $paxPath = null;
            }

            $names[] = $name;
            \fseek($handle, $paddedSize, \SEEK_CUR);
        }

        return $names;
    }

    private function name(string $header, ?string $longName, ?string $paxPath): RawName
    {
        if (null !== $paxPath) {
            return new RawName(\rtrim($paxPath, '/'), true);
        }

        if (null !== $longName) {
            return new RawName(\rtrim($longName, '/'), false);
        }

        $name = \rtrim(\substr($header, self::NAME_OFFSET, self::NAME_LENGTH), "\0");
        $prefix = \rtrim(\substr($header, self::PREFIX_OFFSET, self::PREFIX_LENGTH), "\0");
        if ('' !== $prefix) {
            $name = $prefix.'/'.$name;
        }

        return new RawName(\rtrim($name, '/'), false);
    }

    /**
     * @param resource $handle
     */
    private function readData($handle, int $paddedSize, int $size): string
    {
        if ($paddedSize <= 0) {
            return '';
        }

        return \substr((string) \fread($handle, $paddedSize), 0, $size);
    }

    private function paxPath(string $data): ?string
    {
        foreach (\explode("\n", $data) as $line) {
            $separator = \strpos($line, '=');
            if (false === $separator) {
                continue;
            }
            if ('path' === \substr($line, 0, $separator)) {
                return \substr($line, $separator + 1);
            }
        }

        return null;
    }

    private function size(string $header): int
    {
        $raw = \trim(\substr($header, self::SIZE_OFFSET, self::SIZE_LENGTH), " \0");
        if ('' === $raw) {
            return 0;
        }

        return (int) \octdec($raw);
    }
}
