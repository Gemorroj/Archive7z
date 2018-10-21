<?php
namespace Archive7z\Tests;

use Archive7z\Archive7z;
use Archive7z\Entry;

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


    public function testExtractTo()
    {
        $archive = new Archive7z('fake.7z');
        $entry = new Entry($archive, [
            'Path' => 'some-path',
        ]);

        $originalOutputDirectory =  $archive->getOutputDirectory();
        try {
            $entry->extractTo(\sys_get_temp_dir());
        } catch (\Exception $e) {
            self::assertSame(\realpath($originalOutputDirectory), $archive->getOutputDirectory());
            return;
        }

        self::fail('Not catch expected exception');
    }
}
