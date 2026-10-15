<?php

declare(strict_types=1);

namespace Archive7z\Tests\Encoding;

use Archive7z\Config\FallbackEncoding;
use Archive7z\Encoding\EncodingDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EncodingDetectorTest extends TestCase
{
    private const RUSSIAN = 'Съешь же ещё этих мягких французских булок, да выпей чаю.';

    public function testDetectsCyrillicFamily(): void
    {
        $detector = new EncodingDetector();

        foreach (['Windows-1251', 'KOI8-U', 'CP866', 'ISO-8859-5'] as $encoding) {
            $raw = \mb_convert_encoding(self::RUSSIAN, $encoding, 'UTF-8');

            self::assertSame($encoding, $detector->detect($raw, FallbackEncoding::DETECT_CANDIDATES), $encoding);
        }
    }

    public function testDetectsUkrainianKoi8U(): void
    {
        // KOI8-U differs from KOI8-R only in the Ukrainian letters, so only text
        // that actually uses them can be told apart from KOI8-R.
        $text = 'Чуєш їх, доцю, га? Кумедна ж ти, прощайся без ґольфів!';
        $raw = \mb_convert_encoding($text, 'KOI8-U', 'UTF-8');

        self::assertSame('KOI8-U', (new EncodingDetector())->detect($raw, FallbackEncoding::DETECT_CANDIDATES));
    }

    public function testDetectsWesternEncoding(): void
    {
        $text = 'Café résumé, naïve façade, plain latin text with é, ü, ö, ß.';
        $raw = \mb_convert_encoding($text, 'Windows-1252', 'UTF-8');

        $encoding = (new EncodingDetector())->detect($raw, FallbackEncoding::DETECT_CANDIDATES);

        self::assertSame('Windows-1252', $encoding);
        self::assertNotSame('GB18030', $encoding);
        self::assertSame($text, \mb_convert_encoding($raw, 'UTF-8', (string) $encoding));
    }

    public function testDetectsWesternTextWithTypographicSymbols(): void
    {
        // The C1 range decodes to quotes/dashes/euro/trademark under Windows-1252
        // but to DOS/KOI8 pseudo-graphics (box drawing) under a wrong 8-bit guess;
        // the latter must be rejected, not win on the shared Latin letters.
        $text = "Don\u{2018}t \u{201C}quote\u{201D} me \u{2014} \u{20AC}\u{2122}\u{00A9}.";
        $raw = \mb_convert_encoding($text, 'Windows-1252', 'UTF-8');

        self::assertSame('Windows-1252', (new EncodingDetector())->detect($raw, FallbackEncoding::DETECT_CANDIDATES));
    }

    public function testDetectsEncodingInMixedListing(): void
    {
        // A listing is mostly ASCII with a lone non-ASCII name; scoring it line
        // by line keeps the rare Cyrillic name from being outvoted (regression).
        $cyrillic = \mb_convert_encoding('чавес.jpg', 'Windows-1251', 'UTF-8');
        $sample = "1.jpg\ntest\nreadme.txt\n".$cyrillic;

        self::assertSame(
            'Windows-1251',
            (new EncodingDetector())->detect($sample, FallbackEncoding::DETECT_CANDIDATES),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function shortNameProvider(): iterable
    {
        yield 'Windows-1251' => ['Windows-1251'];
        yield 'KOI8-U' => ['KOI8-U'];
        yield 'CP866' => ['CP866'];
        yield 'ISO-8859-5' => ['ISO-8859-5'];
    }

    #[DataProvider('shortNameProvider')]
    public function testDetectsShortFilename(string $encoding): void
    {
        // Real archives hold short names, where the frequency profile is noisy.
        $raw = \mb_convert_encoding('чавес.jpg', $encoding, 'UTF-8');

        self::assertSame($encoding, (new EncodingDetector())->detect($raw, FallbackEncoding::DETECT_CANDIDATES));
    }

    public function testSkipsUnknownCandidate(): void
    {
        $raw = \mb_convert_encoding('чавес', 'Windows-1251', 'UTF-8');

        self::assertSame('Windows-1251', (new EncodingDetector())->detect($raw, ['NO-SUCH-ENCODING', 'Windows-1251']));
    }

    public function testNoLettersIsRejected(): void
    {
        self::assertSame(-\INF, (new EncodingDetector())->score('Windows-1252', '...---:::'));
    }

    public function testDetectsChinese(): void
    {
        $text = '这是一段中文测试文本，用来检验编码检测是否正确工作。';
        $raw = \mb_convert_encoding($text, 'GB18030', 'UTF-8');

        self::assertSame('GB18030', (new EncodingDetector())->detect($raw, FallbackEncoding::DETECT_CANDIDATES));
    }

    public function testCorrectEncodingScoresHigherThanWrongOne(): void
    {
        $detector = new EncodingDetector();
        $raw = \mb_convert_encoding(self::RUSSIAN, 'Windows-1251', 'UTF-8');

        // The whole point of the rewrite: a Cyrillic decode must clearly beat a
        // Latin single-byte guess, which the old letter-ratio scorer could not do.
        self::assertGreaterThan(
            $detector->score('Windows-1252', $raw),
            $detector->score('Windows-1251', $raw),
        );
    }

    public function testRejectsBinary(): void
    {
        $raw = (string) \file_get_contents(__DIR__.'/../fixtures/7z/linux/7zip-26.04/sample.7z');

        self::assertNull((new EncodingDetector())->detect($raw, FallbackEncoding::DETECT_CANDIDATES));
    }

    public function testRejectsControlBytes(): void
    {
        self::assertSame(-\INF, (new EncodingDetector())->score('ISO-8859-5', "\x80\x81\x82"));
    }

    public function testUnknownEncodingScoresNegativeInfinity(): void
    {
        self::assertSame(-\INF, (new EncodingDetector())->score('no-such-encoding', 'abc'));
    }

    public function testEmptySampleIsNotDetected(): void
    {
        self::assertNull((new EncodingDetector())->detect('', FallbackEncoding::DETECT_CANDIDATES));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function cyrillicProvider(): iterable
    {
        yield 'Windows-1251' => ['Windows-1251'];
        yield 'KOI8-U' => ['KOI8-U'];
        yield 'CP866' => ['CP866'];
    }

    #[DataProvider('cyrillicProvider')]
    public function testCustomCandidateOrderDoesNotMatter(string $encoding): void
    {
        $raw = \mb_convert_encoding(self::RUSSIAN, $encoding, 'UTF-8');
        $detector = new EncodingDetector();

        self::assertSame($encoding, $detector->detect($raw, ['Windows-1252', $encoding]));
    }
}
