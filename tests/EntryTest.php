<?php

namespace Archive7z\Tests;

use Archive7z\Archive7z;
use Archive7z\Entry;
use PHPUnit\Framework\TestCase;

class EntryTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__.'/fixtures';
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

        $archive = new Archive7z($this->fixturesDir.'/7zip-18.05/test.7z');
        foreach ($archive->getEntries() as $entry) {
            $actualResults[] = $entry->getPackedSize();
        }

        self::assertEquals($expectedResults, $actualResults);
    }

    public function testExtractTo(): void
    {
        $archive = new Archive7z('fake.7z');
        $entry = new Entry($archive, [
            'Path' => 'some-path',
        ]);

        $originalOutputDirectory = $archive->getOutputDirectory();
        try {
            $entry->extractTo(\sys_get_temp_dir());
        } catch (\Exception $e) {
            self::assertSame(\realpath($originalOutputDirectory), $archive->getOutputDirectory());

            return;
        }

        self::fail('Not catch expected exception');
    }

    /**
     * @see https://github.com/Gemorroj/Archive7z/pull/24
     */
    public function testDirectory(): void
    {
        $archive = new Archive7z($this->fixturesDir.'/7zip-18.05/test.tar');

        $folder = $archive->getEntry('test');
        $ref = new \ReflectionObject($folder);
        $property = $ref->getProperty('folder');
        $property->setAccessible(true);

        self::assertSame('+', $property->getValue($folder));
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

        $archive = new Archive7z($this->fixturesDir.'/7zip-18.05/test.7z');
        [$firstEntry, $secondEntry] = $archive->getEntries();

        $actualResultFirst = $firstEntry->getData();
        $actualResultSecond = $secondEntry->getData();
        $actualResultFirst[3] = 'Modified = fake';
        $actualResultSecond[3] = 'Modified = fake';

        self::assertSame($expectedResultsFirst, $actualResultFirst);
        self::assertSame($expectedResultsSecond, $actualResultSecond);
    }
}
