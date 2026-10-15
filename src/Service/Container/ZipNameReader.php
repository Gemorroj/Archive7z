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

    public function matches(string $filename): bool
    {
        $header = @\file_get_contents($filename, false, null, 0, 4);
        if (false === $header) {
            return false;
        }

        if (
            \str_starts_with($header, "PK\x03\x04")
            || \str_starts_with($header, "PK\x05\x06")
            || \str_starts_with($header, "PK\x06\x06")
        ) {
            return true;
        }

        // Self-extracting ZIP: an executable stub (MZ) with a ZIP appended.
        return \str_starts_with($header, 'MZ') && $this->endsWithEocd($filename);
    }

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
     * Whether the file ends with a ZIP End Of Central Directory record, which
     * is what a self-extracting stub leaves at the end of the file.
     */
    private function endsWithEocd(string $filename): bool
    {
        $size = @\filesize($filename);
        if (false === $size || $size < 22) {
            return false;
        }

        $handle = @\fopen($filename, 'r');
        if (false === $handle) {
            return false;
        }

        try {
            $tailLength = \min($size, self::MAX_COMMENT + 22);
            \fseek($handle, $size - $tailLength);
            $tail = (string) \fread($handle, $tailLength);
        } finally {
            \fclose($handle);
        }

        return \str_contains($tail, self::EOCD_SIGNATURE);
    }

    /**
     * @param resource $handle
     *
     * @return list<RawName>
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

        // The "PK\x05\x06" signature is not unique: it may also occur in the
        // archive comment, in trailing data appended after the archive, or in
        // stored file contents. Candidates whose declared comment reaches the
        // end of the file are the canonical ones and are tried first; the rest
        // (archives with data after the end) are validated against their
        // central directory, which also rejects a bogus signature whose fields
        // would otherwise drive a huge read.
        $length = \strlen($tail);
        $exact = [];
        $fallback = [];
        foreach ($this->eocdCandidates($tail) as $eocdPos) {
            if ($eocdPos + 22 + $this->u16($tail, $eocdPos + 20) === $length) {
                $exact[] = $eocdPos;
            } else {
                $fallback[] = $eocdPos;
            }
        }

        foreach (\array_merge($exact, $fallback) as $eocdPos) {
            $names = $this->namesFromEocd($handle, $tail, $eocdPos, $size);
            if (null !== $names) {
                return $names;
            }
        }

        throw new RuntimeException('End of central directory record not found');
    }

    /**
     * @param resource $handle
     *
     * @return list<RawName>|null null when the candidate EOCD is not usable
     */
    private function namesFromEocd($handle, string $tail, int $eocdPos, int $size): ?array
    {
        $total = $this->u16($tail, $eocdPos + 10);
        $directorySize = $this->u32($tail, $eocdPos + 12);
        $directoryOffset = $this->u32($tail, $eocdPos + 16);

        [$total, $directorySize, $directoryOffset] = $this->zip64($tail, $eocdPos, $total, $directorySize, $directoryOffset, $handle);

        // A ZIP stores absolute offsets, but a self-extracting stub can leave
        // the central directory offset relative to the ZIP start; in that case
        // the directory ends right before the EOCD record. Both candidates are
        // bounds-checked, so a bogus EOCD cannot drive a huge read.
        $eocdFilePos = $size - \strlen($tail) + $eocdPos;
        $offsets = [$directoryOffset];
        $stubOffset = $eocdFilePos - $directorySize;
        if ($stubOffset !== $directoryOffset) {
            $offsets[] = $stubOffset;
        }

        foreach ($offsets as $offset) {
            if ($offset < 0 || $offset + $directorySize > $size) {
                continue;
            }

            \fseek($handle, $offset);
            $directory = $directorySize > 0 ? (string) \fread($handle, $directorySize) : '';

            if ($total > 0 && !\str_starts_with($directory, self::CENTRAL_DIRECTORY_SIGNATURE)) {
                continue;
            }

            return $this->parseDirectory($directory, $total);
        }

        return null;
    }

    /**
     * @return list<RawName>
     */
    private function parseDirectory(string $directory, int $total): array
    {
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

    /**
     * Candidate End Of Central Directory positions, closest to the end first.
     *
     * @return list<int>
     */
    private function eocdCandidates(string $tail): array
    {
        $length = \strlen($tail);
        $positions = [];

        for ($from = 0; false !== ($pos = \strpos($tail, self::EOCD_SIGNATURE, $from)); $from = $pos + 1) {
            if ($pos + 22 <= $length) {
                $positions[] = $pos;
            }
        }

        return \array_reverse($positions);
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

        if (!\str_starts_with($zip64, self::ZIP64_EOCD_SIGNATURE)) {
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
        if ($offset < 0 || $offset + 2 > \strlen($data)) {
            return 0;
        }

        $value = \unpack('v', \substr($data, $offset, 2));

        return false === $value ? 0 : (int) $value[1];
    }

    private function u32(string $data, int $offset): int
    {
        if ($offset < 0 || $offset + 4 > \strlen($data)) {
            return 0;
        }

        $value = \unpack('V', \substr($data, $offset, 4));

        return false === $value ? 0 : (int) $value[1];
    }

    private function u64(string $data, int $offset): int
    {
        if ($offset < 0 || $offset + 8 > \strlen($data)) {
            return 0;
        }

        $value = \unpack('P', \substr($data, $offset, 8));

        return false === $value ? 0 : (int) $value[1];
    }
}
