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

    /**
     * Candidate encodings tried by {@see self::detect()}, in order.
     *
     * @var string[]
     */
    public const DETECT_CANDIDATES = [
        'Windows-1251',
        'CP866',
        'KOI8-R',
        'ISO-8859-5',
        'Windows-1252',
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

    private function __construct(
        private readonly string $mode,
        private readonly ?string $encoding = null,
    ) {
    }

    /**
     * Leave the value untouched (default).
     */
    public static function asIs(): self
    {
        return new self(self::MODE_AS_IS);
    }

    /**
     * Try to guess the encoding with mb_detect_encoding().
     */
    public static function detect(): self
    {
        return new self(self::MODE_DETECT);
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

        return new self(self::MODE_ENCODING, $encoding);
    }

    public function isAsIs(): bool
    {
        return self::MODE_AS_IS === $this->mode;
    }

    public function isDetect(): bool
    {
        return self::MODE_DETECT === $this->mode;
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
