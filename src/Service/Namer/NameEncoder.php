<?php

declare(strict_types=1);

namespace Archive7z\Service\Namer;

use Archive7z\Config\FallbackEncoding;

/**
 * Resolved encoding of the archive entry names.
 *
 * Values that are not valid UTF-8 are decoded from the resolved source
 * encoding; {@see self::encode()} does the opposite for user supplied names.
 */
final readonly class NameEncoder
{
    private function __construct(
        private ?string $encoding,
    ) {
    }

    public static function asIs(): self
    {
        return new self(null);
    }

    /**
     * @param string|null $sample raw listing used to detect the encoding
     */
    public static function resolve(FallbackEncoding $fallbackEncoding, ?string $sample = null): self
    {
        if ($fallbackEncoding->isAsIs()) {
            return self::asIs();
        }

        if ($fallbackEncoding->isDetect()) {
            if (null === $sample) {
                return self::asIs();
            }

            $sample = self::unescape($sample);

            if (\mb_check_encoding($sample, 'UTF-8')) {
                return self::asIs();
            }

            $encoding = \mb_detect_encoding($sample, FallbackEncoding::DETECT_CANDIDATES, true);

            return \is_string($encoding) ? new self($encoding) : self::asIs();
        }

        return new self($fallbackEncoding->getEncoding());
    }

    public function isAsIs(): bool
    {
        return null === $this->encoding;
    }

    /**
     * Converts a value from the archive name encoding to UTF-8.
     */
    public function decode(string $value): string
    {
        if (null === $this->encoding) {
            return $value;
        }

        $value = self::unescape($value);

        if (\mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = \mb_convert_encoding($value, 'UTF-8', $this->encoding);

        return false === $converted ? $value : $converted;
    }

    /**
     * Reverses 7-Zip's escape of bytes it cannot represent (see UTFConvert.cpp:
     * UTF_ESCAPE_PLANE + UTF_ESCAPE_BASE + byte, i.e. U+EF80..U+EFFF for bytes
     * 0x80..0xFF; 7-Zip restores them the same way with (char)val). Raw 8-bit
     * names (e.g. on Linux) contain no such code points.
     */
    private static function unescape(string $value): string
    {
        if (!\mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $result = \preg_replace_callback('/[\x{EF80}-\x{EFFF}]/u', static function (array $match): string {
            $bytes = $match[0];

            return \chr(((\ord($bytes[1]) & 0x03) << 6) | (\ord($bytes[2]) & 0x3F));
        }, $value);

        return $result ?? $value;
    }

    /**
     * Converts a UTF-8 value to the archive name encoding.
     */
    public function encode(string $value): string
    {
        if (null === $this->encoding) {
            return $value;
        }

        $encoded = \mb_convert_encoding($value, $this->encoding, 'UTF-8');

        return $encoded ?: $value;
    }
}
