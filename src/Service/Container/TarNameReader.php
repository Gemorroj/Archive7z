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

    public function matches(string $filename): bool
    {
        // "ustar" magic lives at offset 257 in the first 512-byte header.
        $header = @\file_get_contents($filename, false, null, 0, 262);

        return \is_string($header) && 'ustar' === \substr($header, 257, 5);
    }

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
     * @return list<RawName>
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

            $names[] = $this->name($header, $longName, $paxPath);
            $longName = null;
            $paxPath = null;
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

            // PAX records are "<length> <key>=<value>": drop the numeric length
            // prefix (including the separating space) before comparing the key.
            $key = \substr($line, 0, $separator);
            $space = \strpos($key, ' ');
            if (false !== $space) {
                $key = \substr($key, $space + 1);
            }

            if ('path' === $key) {
                return \substr($line, $separator + 1);
            }
        }

        return null;
    }

    private function size(string $header): int
    {
        $raw = \substr($header, self::SIZE_OFFSET, self::SIZE_LENGTH);

        // GNU/star store values that do not fit 11 octal digits (sizes >= 8 GiB)
        // in base-256: the top bit of the first byte is set and the remaining
        // bytes are a big-endian two's complement number (tar-spec, "GNU
        // extensions"). Octal digits are always < 0x80, so the top bit is a
        // reliable marker.
        if ('' !== $raw && 0 !== (\ord($raw[0]) & 0x80)) {
            $value = \ord($raw[0]) & 0x7F;
            for ($i = 1, $length = \strlen($raw); $i < $length; ++$i) {
                $value = ($value << 8) | \ord($raw[$i]);
            }

            return $value;
        }

        $raw = \trim($raw, " \0");
        if ('' === $raw) {
            return 0;
        }

        return (int) \octdec($raw);
    }
}
