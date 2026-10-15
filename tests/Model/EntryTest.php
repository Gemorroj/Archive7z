<?php

declare(strict_types=1);

namespace Archive7z\Tests\Model;

use Archive7z\Archive7z;
use Archive7z\Model\Entry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EntryTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = \dirname(__DIR__).'/fixtures';
    }

    /**
     * @see https://github.com/Gemorroj/Archive7z/issues/5
     */
    public function testPackedSize(): void
    {
        $expectedResults = [
            '0', // directory
            '165102', // solid archive
            '',
            '',
            '',
        ];
        $actualResults = [];

        $archive = new Archive7z($this->fixturesDir.'/7z/windows/7zip-18.05/sample.7z');
        foreach ($archive->getEntries() as $entry) {
            $actualResults[] = $entry->getPackedSize();
        }

        self::assertEquals($expectedResults, $actualResults);
    }

    public function testExtract(): void
    {
        $archive = new Archive7z('fake.7z');
        $entry = new Entry($archive, [
            'Path' => 'some-path',
        ]);

        $this->expectException(\Exception::class);
        $entry->extract(\sys_get_temp_dir());
    }

    /**
     * @see https://github.com/Gemorroj/Archive7z/pull/24
     */
    public function testDirectory(): void
    {
        $archive = new Archive7z($this->fixturesDir.'/tar/windows/7zip-18.05/sample.tar');

        $folder = $archive->getEntry('test');
        \assert($folder instanceof Entry);
        $property = new \ReflectionProperty(Entry::class, 'folder');

        self::assertSame('+', $property->getValue($folder));
    }

    /**
     * @return list<list<string>>
     */
    public static function featureProvider(): array
    {
        return [
            ['7z/windows/7zip-26.04/features.7z'],
            ['zip/windows/7zip-26.04/features.zip'],
            ['tar/linux/gnutar-1.34/features.tar'],
            ['tar/linux/bsdtar-3.6.2/features.tar'],
            ['zip/linux/bsdtar-3.6.2/features.zip'],
        ];
    }

    #[DataProvider('featureProvider')]
    public function testContentFeatures(string $archiveName): void
    {
        $archive = new Archive7z($this->fixturesDir.'/'.$archiveName);

        $entries = [];
        foreach ($archive->getEntries() as $entry) {
            $entries[(string) $entry->getUnixPath()] = $entry;
        }

        self::assertArrayHasKey('name with spaces.txt', $entries);
        self::assertArrayHasKey('noextension', $entries);
        self::assertArrayHasKey('UPPER.TXT', $entries);
        self::assertArrayHasKey('zero.txt', $entries);
        self::assertSame('0', $entries['zero.txt']->getSize());
        self::assertArrayHasKey('nested/one/two/deep.txt', $entries);

        self::assertTrue($entries['empty']->isDirectory());
        self::assertTrue($entries['nested/one']->isDirectory());

        $longNames = [];
        foreach (\array_keys($entries) as $path) {
            if (\str_starts_with($path, 'long-')) {
                $longNames[] = $path;
            }
        }
        self::assertCount(1, $longNames);
        self::assertGreaterThan(100, \strlen((string) \current($longNames)));
    }

    public function testData(): void
    {
        $expectedResultsFirst = [
            'Path = test',
            'Size = 0',
            'Packed Size = 0',
            // 'Modified = 2018-10-14 15:41:42.5198371',
            'Modified = fake',
            'Attributes = D',
            'CRC = ',
            'Encrypted = -',
            'Method = ',
            'Block = ',
        ];
        $expectedResultsSecond = [
            'Path = 1.jpg',
            'Size = 91216',
            'Packed Size = 165102',
            // 'Modified = 2013-06-10 09:56:07.0000000',
            'Modified = fake',
            'Attributes = A',
            'CRC = 871345C2',
            'Encrypted = -',
            'Method = LZMA2:192k',
            'Block = 0',
        ];

        $archive = new Archive7z($this->fixturesDir.'/7z/windows/7zip-18.05/sample.7z');
        [$firstEntry, $secondEntry] = $archive->getEntries();

        $actualResultFirst = $firstEntry->getData();
        $actualResultSecond = $secondEntry->getData();
        $actualResultFirst[3] = 'Modified = fake';
        $actualResultSecond[3] = 'Modified = fake';

        self::assertSame($expectedResultsFirst, $actualResultFirst);
        self::assertSame($expectedResultsSecond, $actualResultSecond);
    }
}
