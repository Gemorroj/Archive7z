<?php

declare(strict_types=1);

namespace Archive7z\Config;

use Archive7z\Exception\InvalidArgumentException;

/**
 * Fallback used for entry names when 7-Zip returns a value that is not valid UTF-8.
 *
 * 7-Zip already produces proper UTF-8 whenever the archive carries an exact
 * encoding (ZIP UTF-8 flag, Info-ZIP Unicode path, UTF-16 names in 7z, ...).
 * Only non-UTF-8 names without such metadata (for example CP866/CP1251 in a ZIP
 * or TAR) reach this fallback.
 */
final readonly class FallbackEncoding
{
    public const MODE_AS_IS = 'asis';
    public const MODE_DETECT = 'detect';
    public const MODE_ENCODING = 'encoding';
    public const MODE_CALLBACK = 'callback';

    /**
     * Candidate encodings tried by {@see self::detect()}, in order.
     *
     * The plausibility score of {@see \Archive7z\Encoding\EncodingDetector}
     * discriminates them, so the order only breaks same-script ties. The list
     * covers the code pages the scorer can actually tell apart, in both the
     * Cyrillic family (ranked by letter frequencies) and the Latin family,
     * plus the GB18030 CJK super-set. Encodings a given mbstring build does not
     * know are skipped during detection.
     *
     * Other single-byte scripts (Greek, Hebrew, Arabic, Baltic, ...) are
     * deliberately absent: their bytes decode to a plausible, frequency-matched
     * Cyrillic/Latin text under a wrong candidate, so the heuristic mis-ranks
     * them. Pass them explicitly through {@see self::codepage()} when an archive
     * stores such names, or use a dedicated charset-detection library.
     *
     * @var list<string>
     */
    public const DETECT_CANDIDATES = [
        // Cyrillic: KOI8-U first, it covers Ukrainian while mapping the shared
        // Cyrillic letters exactly like KOI8-R, so it loses nothing for Russian.
        'Windows-1251',
        'KOI8-U',
        'KOI8-R',
        'CP866',
        'ISO-8859-5',
        // Western European
        'Windows-1252',
        'ISO-8859-1',
        'ISO-8859-15',
        // CJK multi-byte super-set
        'GB18030',
    ];

    /**
     * Map of encoding names to 7-Zip codepage numbers (the -mcp switch).
     *
     * @var array<string, int>
     */
    private const CODEPAGES = [
        'WINDOWS-1250' => 1250,
        'WINDOWS-1251' => 1251,
        'WINDOWS-1252' => 1252,
        'WINDOWS-1253' => 1253,
        'WINDOWS-1254' => 1254,
        'WINDOWS-1255' => 1255,
        'WINDOWS-1256' => 1256,
        'WINDOWS-1257' => 1257,
        'WINDOWS-1258' => 1258,
        'CP1250' => 1250,
        'CP1251' => 1251,
        'CP1252' => 1252,
        'CP437' => 437,
        'CP850' => 850,
        'CP852' => 852,
        'CP866' => 866,
        'IBM866' => 866,
        'KOI8-R' => 20866,
        'KOI8-U' => 21866,
        'ISO-8859-1' => 28591,
        'ISO-8859-2' => 28592,
        'ISO-8859-5' => 28595,
        'UTF-8' => 65001,
    ];

    /**
     * @param list<string>|null $detectCandidates candidate encodings for detect()
     */
    private function __construct(
        private readonly string $mode,
        private readonly ?string $encoding = null,
        private readonly ?\Closure $decodeCallback = null,
        private readonly ?\Closure $encodeCallback = null,
        private readonly ?array $detectCandidates = null,
    ) {
    }

    /**
     * Leave the value untouched.
     */
    public static function asIs(): self
    {
        return new self(self::MODE_AS_IS);
    }

    /**
     * Guess the encoding from the listing, ranking the candidates by how
     * plausible the decoded text is (see {@see NameEncoder}). The default.
     *
     * @param list<string>|null $candidates encodings to try, in preference order
     *
     * @throws InvalidArgumentException when a candidate is not a known encoding
     */
    public static function detect(?array $candidates = null): self
    {
        return new self(
            self::MODE_DETECT,
            detectCandidates: null === $candidates || [] === $candidates ? null : self::validateCandidates($candidates),
        );
    }

    /**
     * @param array<int|string, mixed> $candidates
     *
     * @throws InvalidArgumentException
     *
     * @return list<string>
     */
    private static function validateCandidates(array $candidates): array
    {
        $validated = [];
        foreach ($candidates as $candidate) {
            if (!\is_string($candidate) || '' === $candidate || !self::isKnownEncoding($candidate)) {
                throw new InvalidArgumentException('Unknown encoding "'.(\is_string($candidate) ? $candidate : \gettype($candidate)).'"');
            }

            $validated[] = $candidate;
        }

        return $validated;
    }

    private static function isKnownEncoding(string $encoding): bool
    {
        try {
            \mb_convert_encoding('', 'UTF-8', $encoding);
        } catch (\ValueError) {
            return false;
        }

        return true;
    }

    /**
     * Decode from the given encoding.
     *
     * @throws InvalidArgumentException
     */
    public static function codepage(string $encoding): self
    {
        if ('' === $encoding) {
            throw new InvalidArgumentException('Encoding must not be empty');
        }

        if (!self::isKnownEncoding($encoding)) {
            throw new InvalidArgumentException('Unknown encoding "'.$encoding.'"');
        }

        return new self(self::MODE_ENCODING, $encoding);
    }

    /**
     * Decode (and optionally encode) names with user supplied callbacks.
     *
     * Use when neither the container metadata nor {@see self::detect()} can
     * decide the encoding (for example old ZIP/TAR archives holding raw 8-bit
     * names): the decoder receives a raw name and returns UTF-8. The optional
     * encoder does the reverse for masks/paths; without it non-ASCII names
     * passed to a mask are used unchanged.
     *
     * Note: only {@see self::codepage()} passes a non-ASCII mask through a list
     * file. A callback mask goes on the 7-Zip command line, so it matches only
     * where 7-Zip compares the raw bytes (official builds on Linux); Windows
     * and builds that force a UTF-8 command line (some distro packages) may
     * match nothing. Use a code page when you need non-ASCII masks.
     *
     * @param callable(string): string      $decode raw name bytes -> UTF-8
     * @param callable(string): string|null $encode UTF-8 -> raw name bytes
     */
    public static function viaCallback(callable $decode, ?callable $encode = null): self
    {
        return new self(
            self::MODE_CALLBACK,
            null,
            \Closure::fromCallable($decode),
            null === $encode ? null : \Closure::fromCallable($encode),
        );
    }

    public function isAsIs(): bool
    {
        return self::MODE_AS_IS === $this->mode;
    }

    public function isDetect(): bool
    {
        return self::MODE_DETECT === $this->mode;
    }

    /**
     * Candidate encodings used by detect(), in preference order.
     *
     * @return list<string>
     */
    public function getDetectCandidates(): array
    {
        return $this->detectCandidates ?? self::DETECT_CANDIDATES;
    }

    public function isCallback(): bool
    {
        return self::MODE_CALLBACK === $this->mode;
    }

    /**
     * @return \Closure(string): string|null
     */
    public function getDecodeCallback(): ?\Closure
    {
        return $this->decodeCallback;
    }

    /**
     * @return \Closure(string): string|null
     */
    public function getEncodeCallback(): ?\Closure
    {
        return $this->encodeCallback;
    }

    public function getEncoding(): ?string
    {
        return $this->encoding;
    }

    /**
     * 7-Zip codepage number (-mcp) for the configured encoding, if known.
     */
    public function getCodepage(): ?int
    {
        if (self::MODE_ENCODING !== $this->mode || null === $this->encoding) {
            return null;
        }

        return self::CODEPAGES[\strtoupper($this->encoding)] ?? null;
    }
}
