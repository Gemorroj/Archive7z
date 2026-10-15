<?php

declare(strict_types=1);

namespace Archive7z\Service\Namer;

use Archive7z\Config\FallbackEncoding;
use Archive7z\Encoding\EncodingDetector;

/**
 * Resolved name encoder.
 *
 * Always a pair of callables: one that turns a raw archive value into UTF-8
 * and one that turns a UTF-8 name back into the archive encoding. The library
 * presets ({@see FallbackEncoding}) build them from an explicit code page, a
 * detected encoding, or user supplied callbacks; as-is uses identity.
 */
final readonly class NameEncoder
{
    /**
     * @param \Closure(string): string $decode raw value -> UTF-8 (7-Zip escapes already reversed)
     * @param \Closure(string): string $encode UTF-8 -> raw value
     */
    private function __construct(
        private \Closure $decode,
        private \Closure $encode,
        private bool $asIs,
    ) {
    }

    /**
     * @param string|null $sample raw listing used to detect the encoding
     */
    public static function resolve(FallbackEncoding $fallbackEncoding, ?string $sample = null): self
    {
        if ($fallbackEncoding->isAsIs()) {
            return self::identity();
        }

        if ($fallbackEncoding->isCallback()) {
            return new self(
                $fallbackEncoding->getDecodeCallback() ?? self::identityCallback(),
                $fallbackEncoding->getEncodeCallback() ?? self::identityCallback(),
                false,
            );
        }

        if ($fallbackEncoding->isDetect()) {
            return self::fromDetection($sample, $fallbackEncoding->getDetectCandidates());
        }

        $encoding = $fallbackEncoding->getEncoding();

        return null === $encoding ? self::identity() : self::fromEncoding($encoding);
    }

    public function isAsIs(): bool
    {
        return $this->asIs;
    }

    /**
     * Converts a value from the archive name encoding to UTF-8.
     *
     * 7-Zip byte escapes (see {@see self::unescape()}) are always reversed
     * first, also for as-is, since they are a lossless transport artifact,
     * not decoding.
     */
    public function decode(string $value): string
    {
        return ($this->decode)(self::unescape($value));
    }

    /**
     * Converts a UTF-8 value to the archive name encoding.
     */
    public function encode(string $value): string
    {
        return ($this->encode)($value);
    }

    private static function identity(): self
    {
        return new self(self::identityCallback(), self::identityCallback(), true);
    }

    /**
     * @param list<string> $candidates
     */
    private static function fromDetection(?string $sample, array $candidates): self
    {
        if (null === $sample) {
            return self::identity();
        }

        $sample = self::unescape($sample);
        if (\mb_check_encoding($sample, 'UTF-8')) {
            return self::identity();
        }

        $encoding = (new EncodingDetector())->detect($sample, $candidates);

        return null === $encoding ? self::identity() : self::fromEncoding($encoding);
    }

    private static function fromEncoding(string $encoding): self
    {
        $decode = static function (string $value) use ($encoding): string {
            if (\mb_check_encoding($value, 'UTF-8')) {
                return $value;
            }

            $converted = \mb_convert_encoding($value, 'UTF-8', $encoding);

            return false === $converted ? $value : $converted;
        };

        $encode = static function (string $value) use ($encoding): string {
            $encoded = \mb_convert_encoding($value, $encoding, 'UTF-8');

            return $encoded ?: $value;
        };

        return new self($decode, $encode, false);
    }

    /**
     * @return \Closure(string): string
     */
    private static function identityCallback(): \Closure
    {
        return static function (string $value): string {
            return $value;
        };
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
}
