<?php

declare(strict_types=1);

namespace Archive7z\Service\Container;

use Archive7z\Exception\RuntimeException;

/**
 * Reads entry names from a ZIP central directory.
 *
 * A name is considered UTF-8 when the general purpose bit 11 is set or a valid
 * Info-ZIP Unicode Path extra field (0x7075) is present; otherwise the raw
 * bytes are returned for the caller to decode.
 */
final class ZipNameReader implements NameReaderInterface
{
    private const EOCD_SIGNATURE = "PK\x05\x06";
    private const ZIP64_EOCD_LOCATOR_SIGNATURE = "PK\x06\x07";
    private const ZIP64_EOCD_SIGNATURE = "PK\x06\x06";
    private const CENTRAL_DIRECTORY_SIGNATURE = "PK\x01\x02";
    private const MAX_COMMENT = 65535;
    private const UTF8_FLAG = 0x0800;
    private const UNICODE_PATH_EXTRA_FIELD = 0x7075;

    public function read(string $filename): array
    {
        $handle = @\fopen($filename, 'r');
        if (false === $handle) {
            throw new RuntimeException('Can not open archive "'.$filename.'"');
        }

        try {
            return $this->readCentralDirectory($handle);
        } finally {
            \fclose($handle);
        }
    }

    /**
     * @param resource $handle
     *
     * @return RawName[]
     */
    private function readCentralDirectory($handle): array
    {
        $size = $this->fileSize($handle);
        if ($size < 22) {
            throw new RuntimeException('End of central directory record not found');
        }

        $tailLength = \min($size, self::MAX_COMMENT + 22);
        \fseek($handle, $size - $tailLength);
        $tail = (string) \fread($handle, $tailLength);

        $eocdPos = \strrpos($tail, self::EOCD_SIGNATURE);
        if (false === $eocdPos) {
            throw new RuntimeException('End of central directory record not found');
        }

        $eocd = \substr($tail, $eocdPos);
        $total = $this->u16($eocd, 10);
        $directorySize = $this->u32($eocd, 12);
        $directoryOffset = $this->u32($eocd, 16);

        [$total, $directorySize, $directoryOffset] = $this->zip64($tail, $eocdPos, $total, $directorySize, $directoryOffset, $handle);

        \fseek($handle, $directoryOffset);
        $directory = $directorySize > 0 ? (string) \fread($handle, $directorySize) : '';

        $names = [];
        $offset = 0;
        for ($i = 0; $i < $total; ++$i) {
            if (self::CENTRAL_DIRECTORY_SIGNATURE !== \substr($directory, $offset, 4)) {
                break;
            }

            $flags = $this->u16($directory, $offset + 8);
            $nameLength = $this->u16($directory, $offset + 28);
            $extraLength = $this->u16($directory, $offset + 30);
            $commentLength = $this->u16($directory, $offset + 32);

            $raw = \substr($directory, $offset + 46, $nameLength);
            $extra = \substr($directory, $offset + 46 + $nameLength, $extraLength);

            $names[] = $this->name($raw, $extra, 0 !== ($flags & self::UTF8_FLAG));

            $offset += 46 + $nameLength + $extraLength + $commentLength;
        }

        return $names;
    }

    private function name(string $raw, string $extra, bool $utf8): RawName
    {
        if (!$utf8) {
            $unicode = $this->unicodePath($raw, $extra);
            if (null !== $unicode) {
                return new RawName(\rtrim($unicode, '/'), true);
            }
        }

        return new RawName(\rtrim($raw, '/'), $utf8);
    }

    private function unicodePath(string $raw, string $extra): ?string
    {
        $offset = 0;
        $length = \strlen($extra);
        while ($offset + 4 <= $length) {
            $id = $this->u16($extra, $offset);
            $size = $this->u16($extra, $offset + 2);
            $data = \substr($extra, $offset + 4, $size);

            if (self::UNICODE_PATH_EXTRA_FIELD === $id && \strlen($data) >= 5) {
                $version = \ord($data[0]);
                $crc = $this->u32($data, 1);
                if (1 === $version && \crc32($raw) === $crc) {
                    return \substr($data, 5);
                }
            }

            $offset += 4 + $size;
        }

        return null;
    }

    /**
     * @param resource $handle
     *
     * @return array{int, int, int}
     */
    private function zip64(string $tail, int $eocdPos, int $total, int $directorySize, int $directoryOffset, $handle): array
    {
        if (0xFFFF !== $total && 0xFFFFFFFF !== $directorySize && 0xFFFFFFFF !== $directoryOffset) {
            return [$total, $directorySize, $directoryOffset];
        }

        $locatorPos = \strrpos(\substr($tail, 0, $eocdPos), self::ZIP64_EOCD_LOCATOR_SIGNATURE);
        if (false === $locatorPos) {
            return [$total, $directorySize, $directoryOffset];
        }

        $zip64Offset = $this->u64(\substr($tail, $locatorPos), 8);
        \fseek($handle, $zip64Offset);
        $zip64 = (string) \fread($handle, 56);

        if (self::ZIP64_EOCD_SIGNATURE !== \substr($zip64, 0, 4)) {
            return [$total, $directorySize, $directoryOffset];
        }

        return [
            $this->u64($zip64, 32),
            $this->u64($zip64, 40),
            $this->u64($zip64, 48),
        ];
    }

    /**
     * @param resource $handle
     */
    private function fileSize($handle): int
    {
        $stat = \fstat($handle);
        if (false === $stat) {
            throw new RuntimeException('Can not read archive size');
        }

        return $stat['size'];
    }

    private function u16(string $data, int $offset): int
    {
        $value = \unpack('v', \substr($data, $offset, 2));

        return false === $value ? 0 : (int) $value[1];
    }

    private function u32(string $data, int $offset): int
    {
        $value = \unpack('V', \substr($data, $offset, 4));

        return false === $value ? 0 : (int) $value[1];
    }

    private function u64(string $data, int $offset): int
    {
        $value = \unpack('P', \substr($data, $offset, 8));

        return false === $value ? 0 : (int) $value[1];
    }
}
