<?php
namespace Archive7z\Tests;

use Archive7z\Archive7z;

class EntryTest extends \PHPUnit_Framework_TestCase
{
    protected $fixturesDir;

    protected function setUp()
    {
        $this->fixturesDir = __DIR__ . '/fixtures';
    }


    /**
     * @see https://github.com/Gemorroj/Archive7z/issues/5
     */
    public function testPackedSize()
    {
        $expectedResults = [
            '60572',
            '104822',
            '', // второй файл в solid блоке
            '19',
            '0' // directory
        ];
        $actualResults = [];

        $archive = new Archive7z($this->fixturesDir . '/test.7z');
        foreach ($archive->getEntries() as $entry) {
            $actualResults[] = $entry->getPackedSize();
        }

        self::assertEquals($expectedResults, $actualResults);
    }
}
