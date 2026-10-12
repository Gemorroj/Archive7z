<?php

declare(strict_types=1);

namespace Archive7z\Service\Container;

use Archive7z\Exception\RuntimeException;

/**
 * Reads the optional original file name (FNAME) from a gzip header.
 *
 * The gzip specification does not define the encoding of FNAME, so it is
 * returned as raw bytes for the caller to decode.
 */
final class GzipNameReader implements NameReaderInterface
{
    private const FEXTRA = 0x04;
    private const FNAME = 0x08;

    public function read(string $filename): array
    {
        $handle = @\fopen($filename, 'r');
        if (false === $handle) {
            throw new RuntimeException('Can not open archive "'.$filename.'"');
        }

        try {
            $header = (string) \fread($handle, 10);
            if (\strlen($header) < 10 || "\x1f\x8b" !== \substr($header, 0, 2)) {
                throw new RuntimeException('Not a gzip archive');
            }

            $flags = \ord($header[3]);
            if (self::FEXTRA === ($flags & self::FEXTRA)) {
                $extraLength = $this->u16((string) \fread($handle, 2));
                if ($extraLength > 0) {
                    \fread($handle, $extraLength);
                }
            }

            if (self::FNAME !== ($flags & self::FNAME)) {
                return [];
            }

            $name = '';
            while (!\feof($handle)) {
                $char = (string) \fread($handle, 1);
                if ('' === $char || "\0" === $char) {
                    break;
                }
                $name .= $char;
            }

            return '' === $name ? [] : [new RawName($name, false)];
        } finally {
            \fclose($handle);
        }
    }

    private function u16(string $data): int
    {
        $value = \unpack('v', \substr($data, 0, 2));

        return false === $value ? 0 : (int) $value[1];
    }
}
