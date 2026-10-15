<?php

declare(strict_types=1);

namespace Archive7z\Encoding;

/**
 * Ranks candidate encodings by how plausible the decoded text is.
 *
 * A wrong single-byte guess almost never fails to decode (every byte maps to
 * some character), so byte validity alone is not enough. The decode is scored
 * on the produced text instead: binary or unassigned output is rejected, a
 * single dominant script is rewarded, and — where several encodings share a
 * script, e.g. the Cyrillic single-byte family — the letter frequencies are
 * compared with the language profile. The sample is scored line by line (a
 * listing mixes names of every script and length), and nothing plausible yields
 * null, so the caller can leave the raw bytes untouched rather than decode them
 * wrongly.
 *
 * The component has no dependencies beyond mbstring and is deliberately
 * self-contained, so it can be reused or extracted without pulling the rest of
 * the library along.
 */
final class EncodingDetector
{
    /**
     * Minimum plausibility score for a detected encoding to be trusted; below it
     * the caller should leave the raw bytes untouched rather than decode.
     */
    public const MIN_SCORE = 0.45;

    /**
     * Only the beginning of the sample is scored; the winning encoding is then
     * applied by the caller to the whole input.
     */
    private const SAMPLE_SIZE = 65536;

    /**
     * Scripts grouped by writing system: a decode may mix the scripts of one
     * group (e.g. Japanese Han + Kana) and still count as a single writing
     * system, while mixing across groups is suspicious.
     *
     * @var array<string, list<string>>
     */
    private const SCRIPT_GROUPS = [
        'Latin' => ['Latin'],
        'Cyrillic' => ['Cyrillic'],
        'Greek' => ['Greek'],
        'Hebrew' => ['Hebrew'],
        'Arabic' => ['Arabic'],
        'CJK' => ['Han', 'Hiragana', 'Katakana'],
        'Hangul' => ['Hangul'],
        'Thai' => ['Thai'],
    ];

    /**
     * Multi-byte encodings: a decode that stays clean on high-byte-rich input is
     * a strong signal, since a single-byte guess would win on letter-likeness.
     *
     * @var list<string>
     */
    private const MULTIBYTE_ENCODINGS = ['GB18030'];

    /**
     * Letter frequency profiles (lowercased) used to tell apart encodings that
     * decode to the same script, e.g. the Cyrillic single-byte family.
     *
     * @var array<string, array<string, float>>
     */
    private const SCRIPT_FREQUENCIES = [
        'Cyrillic' => [
            'о' => 0.1097, 'е' => 0.0845, 'а' => 0.0801, 'и' => 0.0735, 'н' => 0.0670, 'т' => 0.0626,
            'с' => 0.0547, 'р' => 0.0473, 'в' => 0.0454, 'л' => 0.0440, 'к' => 0.0349, 'м' => 0.0321,
            'д' => 0.0298, 'п' => 0.0281, 'у' => 0.0262, 'я' => 0.0201, 'ы' => 0.0190, 'ь' => 0.0174,
            'г' => 0.0170, 'з' => 0.0165, 'б' => 0.0159, 'ч' => 0.0144, 'й' => 0.0121, 'х' => 0.0097,
            'ж' => 0.0094, 'ш' => 0.0073, 'ю' => 0.0064, 'ц' => 0.0048, 'щ' => 0.0036, 'э' => 0.0032,
            'ф' => 0.0026, 'ъ' => 0.0004, 'ё' => 0.0004,
        ],
        'Latin' => [
            'e' => 0.12702, 't' => 0.09056, 'a' => 0.08167, 'o' => 0.07507, 'i' => 0.06966, 'n' => 0.06749,
            's' => 0.06327, 'h' => 0.06094, 'r' => 0.05987, 'd' => 0.04253, 'l' => 0.04025, 'c' => 0.02782,
            'u' => 0.02758, 'm' => 0.02406, 'w' => 0.02360, 'f' => 0.02228, 'g' => 0.02015, 'y' => 0.01974,
            'p' => 0.01929, 'b' => 0.01492, 'v' => 0.00978, 'k' => 0.00772, 'j' => 0.00153, 'x' => 0.00150,
            'q' => 0.00095, 'z' => 0.00074,
        ],
    ];

    /**
     * @param list<string> $candidates encodings to try, in preference order
     *
     * @return string|null the most plausible encoding, or null when none is
     */
    public function detect(string $sample, array $candidates): ?string
    {
        $sample = \substr($sample, 0, self::SAMPLE_SIZE);

        $best = null;
        $bestScore = self::MIN_SCORE;
        foreach ($candidates as $encoding) {
            $score = $this->score($encoding, $sample);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $encoding;
            }
        }

        return $best;
    }

    /**
     * Plausibility (0.0..1.0) of decoding the sample with the encoding, or -INF
     * when the result is binary, unreadable or otherwise not text.
     *
     * The sample is scored line by line and averaged: a listing mixes names of
     * every script and length, and a single line scored in isolation keeps a
     * rare non-ASCII name from being outvoted by the surrounding ASCII ones.
     * Lines that are identical across the candidates (pure ASCII) add the same
     * constant to every average and therefore do not affect the ranking.
     */
    public function score(string $encoding, string $sample): float
    {
        $sum = 0.0;
        $lines = 0;
        foreach (\explode("\n", $sample) as $line) {
            if ('' === $line) {
                continue;
            }

            $sum += $this->scoreLine($encoding, $line);
            ++$lines;
        }

        return $lines > 0 ? $sum / $lines : 0.0;
    }

    /**
     * Plausibility (0.0..1.0) of decoding one line with the encoding, or -INF
     * when the result is binary, unreadable or otherwise not text.
     */
    private function scoreLine(string $encoding, string $line): float
    {
        $decoded = self::tryConvert($line, $encoding);
        if (null === $decoded) {
            return -\INF;
        }

        $length = \mb_strlen($decoded, 'UTF-8');
        if (0 === $length) {
            return -\INF;
        }

        // Reject binary-ish decodes: control and unassigned/replacement characters.
        // The Arrows..Geometric Shapes range is included because the DOS/KOI8
        // pseudo-graphics live there, so an 8-bit guess that turns typographic
        // characters (quotes, dashes, euro, trademark) into box drawing is caught.
        $control = (int) \preg_match_all('/[\x{00}-\x{08}\x{0B}\x{0C}\x{0E}-\x{1F}\x{7F}-\x{9F}]/u', $decoded);
        $broken = (int) \preg_match_all('/[\x{FFFD}\p{Cn}\p{Co}\p{Cs}\x{2190}-\x{25FF}]/u', $decoded);
        if (($control + $broken) > 0.02 * $length) {
            return -\INF;
        }

        $letters = (int) \preg_match_all('/\p{L}/u', $decoded);
        if (0 === $letters) {
            return -\INF;
        }

        // Dominant writing system and how pure the decode is (mixing systems is
        // suspicious); the letter frequencies then break same-script ties.
        [$group, $groupCount] = self::dominantGroup($decoded);
        $purity = $groupCount / $letters;
        $score = 0.5 * $purity + 0.5 * self::frequencyMatch($group, $decoded);

        // A valid multi-byte decode of high-byte-rich input is a strong signal.
        if (\in_array($encoding, self::MULTIBYTE_ENCODINGS, true) && 0 === $broken) {
            $score += 0.5;
        }

        return $score;
    }

    /**
     * @return array{0: string, 1: int} the dominant writing system and its letter count
     */
    private static function dominantGroup(string $decoded): array
    {
        $dominant = '';
        $dominantCount = 0;
        foreach (self::SCRIPT_GROUPS as $group => $scripts) {
            $count = 0;
            foreach ($scripts as $script) {
                $count += (int) \preg_match_all('/\p{'.$script.'}/u', $decoded);
            }
            if ($count > $dominantCount) {
                $dominantCount = $count;
                $dominant = $group;
            }
        }

        return [$dominant, $dominantCount];
    }

    /**
     * Cosine similarity (0.0..1.0) between the decoded letters and the writing
     * system's reference frequency profile; 0.5 when it has no profile.
     */
    private static function frequencyMatch(string $group, string $decoded): float
    {
        $model = self::SCRIPT_FREQUENCIES[$group] ?? null;
        if (null === $model) {
            return 0.5;
        }

        $pattern = '/[\p{'.\implode('}\p{', self::SCRIPT_GROUPS[$group]).'}]/u';
        $counts = [];
        $total = 0;
        \preg_match_all($pattern, $decoded, $matches);
        foreach ($matches[0] as $letter) {
            $letter = \mb_strtolower($letter, 'UTF-8');
            if (isset($model[$letter])) {
                $counts[$letter] = ($counts[$letter] ?? 0) + 1;
                ++$total;
            }
        }
        if (0 === $total) {
            return 0.0;
        }

        $dot = 0.0;
        $observedNorm = 0.0;
        $modelNorm = 0.0;
        foreach ($model as $letter => $weight) {
            $observed = ($counts[$letter] ?? 0) / $total;
            $dot += $observed * $weight;
            $observedNorm += $observed * $observed;
            $modelNorm += $weight * $weight;
        }

        return $observedNorm > 0.0 ? $dot / (\sqrt($observedNorm) * \sqrt($modelNorm)) : 0.0;
    }

    /**
     * @return string|null null when the encoding is unknown or cannot represent the bytes
     */
    private static function tryConvert(string $bytes, string $encoding): ?string
    {
        $previous = \mb_substitute_character();
        try {
            // Make invalid bytes visible as U+FFFD so they can be counted below.
            \mb_substitute_character(0xFFFD);
            $converted = \mb_convert_encoding($bytes, 'UTF-8', $encoding);
        } catch (\ValueError) {
            return null;
        } finally {
            \mb_substitute_character($previous);
        }

        return false === $converted ? null : $converted;
    }
}
