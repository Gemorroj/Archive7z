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
            '0', // directory
            '165102', // solid архив
            '',
            '',
            '',
        ];
        $actualResults = [];

        $archive = new Archive7z($this->fixturesDir . '/7zip-18.05/test.7z');
        foreach ($archive->getEntries() as $entry) {
            $actualResults[] = $entry->getPackedSize();
        }

        self::assertEquals($expectedResults, $actualResults);
    }
}
