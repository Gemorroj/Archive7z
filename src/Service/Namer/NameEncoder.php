<?php

declare(strict_types=1);

namespace Archive7z\Service\Namer;

use Archive7z\Config\FallbackEncoding;

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
     * Minimum plausibility score for a detected encoding to be trusted; below it
     * the listing is left as-is rather than confidently mis-decoded.
     */
    private const DETECT_MIN_SCORE = 0.3;

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
     * @param string[] $candidates
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

        $best = null;
        $bestScore = self::DETECT_MIN_SCORE;
        foreach ($candidates as $encoding) {
            $score = self::score($encoding, $sample);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $encoding;
            }
        }

        return null === $best ? self::identity() : self::fromEncoding($best);
    }

    /**
     * Plausibility of decoding the sample (all names) with an encoding: the
     * average score over its lines. A wrong encoding usually yields control or
     * unassigned characters and scores low; related single-byte encodings may
     * score equally, in which case the candidate order decides.
     *
     * @return float 0..1, or -INF when the encoding cannot represent the bytes
     */
    private static function score(string $encoding, string $sample): float
    {
        $sum = 0.0;
        $lines = 0;
        foreach (\explode("\n", $sample) as $line) {
            if ('' === $line) {
                continue;
            }

            $decoded = \mb_convert_encoding($line, 'UTF-8', $encoding);
            if (false === $decoded) {
                return -\INF;
            }

            $sum += self::scoreText($decoded);
            ++$lines;
        }

        return $lines > 0 ? $sum / $lines : 0.0;
    }

    private static function scoreText(string $text): float
    {
        $length = \mb_strlen($text, 'UTF-8');
        if (0 === $length) {
            return 0.0;
        }

        $good = (int) \preg_match_all('/[\p{L}\p{N}]/u', $text);
        $neutral = (int) \preg_match_all('/[\p{Zs}\p{P}\p{S}]/u', $text);
        $bad = (int) \preg_match_all('/[\x{00}-\x{08}\x{0B}\x{0C}\x{0E}-\x{1F}\x{7F}-\x{9F}\x{FFFD}\p{Cn}\p{Co}\p{Cs}]/u', $text);

        return ($good + 0.5 * $neutral - 2.0 * $bad) / $length;
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
