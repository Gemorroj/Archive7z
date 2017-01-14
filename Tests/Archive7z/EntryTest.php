<?php
namespace Tests\Archive7z;

use Archive7z\Archive7z;

//TODO
class EntryTest extends \PHPUnit_Framework_TestCase
{
    protected $cliPath;
    protected $fixturesDir;
    protected $baseDir;

    protected function setUp()
    {
        $this->baseDir = dirname(__DIR__);
        $this->fixturesDir = $this->baseDir . DIRECTORY_SEPARATOR . 'fixtures';
    }


    /**
     * @see https://github.com/Gemorroj/Archive7z/issues/5
     */
    public function testPackedSize()
    {
        $expectedResults = array(
            '60572',
            '104822',
            '', // второй файл в solid блоке
            '19',
            '0' // directory
        );
        $actualResults = array();

        $archive = new Archive7z($this->fixturesDir . '/test.7z', $this->cliPath);
        foreach ($archive->getEntries() as $entry) {
            $actualResults[] = $entry->getPackedSize();
        }

        self::assertEquals($expectedResults, $actualResults);
    }
}
