# Wrapper 7-zip (p7zip)

[![License](https://poser.pugx.org/gemorroj/archive7z/license)](https://packagist.org/packages/gemorroj/archive7z)
[![Latest Stable Version](https://poser.pugx.org/gemorroj/archive7z/v/stable)](https://packagist.org/packages/gemorroj/archive7z)
[![Continuous Integration](https://github.com/Gemorroj/Archive7z/workflows/Continuous%20Integration/badge.svg?branch=master)](https://github.com/Gemorroj/Archive7z/actions?query=workflow%3A%22Continuous+Integration%22)


### Features:

- Support all 7-zip formats: `7z`, `XZ`, `BZIP2`, `GZIP`, `TAR`, `ZIP`, `WIM`, `AR`, `ARJ`, `CAB`, `CHM`, `CPIO`, `CramFS`, `DMG`, `EXT`, `FAT`, `GPT`, `HFS`, `IHEX`, `ISO`, `LZH`, `LZMA`, `MBR`, `MSI`, `NSIS`, `NTFS`, `QCOW2`, `RAR`, `RPM`, `SquashFS`, `UDF`, `UEFI`, `VDI`, `VHD`, `VMDK`, `WIM`, `XAR` and `Z`
- Unpacking archives
- Extract any directory or file
- List files and directories
- Get contents of any file from archive
- Delete files or directories (not `RAR`)
- Add files or directories (not `RAR`)


### Requirements:

- PHP >= 7.3
- shell
- **7-zip >= 7.30 (p7zip >= 9.38)**


### Installation:
```bash
composer require gemorroj/archive7z
```


### Notes:
- https://sourceforge.net/p/p7zip/discussion/383043/thread/fa143cf2/
- Correctly works only with filenames in UTF-8 encoding [#15](https://github.com/Gemorroj/Archive7z/issues/15).


### Recommendations:
Archive7z focuses on wrapping up the original 7-zip features.
But it is not always convenient to use in your application.
Therefore, we recommend that you always create your own wrapper class over Archive7z with the addition of higher-level logic.


### Example:

```php
<?php
use Archive7z\Archive7z;

class MyArchive7z extends Archive7z
{
    protected $timeout = 120;
    protected $compressionLevel = 6;
    protected $overwriteMode = self::OVERWRITE_MODE_S;
    protected $outputDirectory = '/path/to/custom/output/directory';
}

$obj = new MyArchive7z('path_to_7z_file.7z');

if (!$obj->isValid()) {
    throw new \RuntimeException('Incorrect archive');
}


foreach ($obj->getEntries() as $entry) {
        print_r($entry);
        /*
Archive7z\Entry Object
    (
        [path:Archive7z\Entry:private] => 1.jpg
        [size:Archive7z\Entry:private] => 91216
        [packedSize:Archive7z\Entry:private] => 165344
        [modified:Archive7z\Entry:private] => 2013-06-10 09:56:07
        [attributes:Archive7z\Entry:private] => A
        [crc:Archive7z\Entry:private] => 871345C2
        [encrypted:Archive7z\Entry:private] => +
        [method:Archive7z\Entry:private] => LZMA:192k 7zAES:19
        [block:Archive7z\Entry:private] => 0
        [comment:Archive7z\Entry:private] => 
        [hostOs:Archive7z\Entry:private] => 
        [folder:Archive7z\Entry:private] => 
        [archive:Archive7z\Entry:private] => Archive7z\Archive7z Object
            (
                [compressionLevel:protected] => 9
                [binary7z:Archive7z\Archive7z:private] => C:\Program Files\7-Zip\7z.exe
                [filename:Archive7z\Archive7z:private] => S:\VCS\Git\Archive7z\tests/fixtures/testPasswd.7z
                [password:Archive7z\Archive7z:private] => 123
                [outputDirectory:Archive7z\Archive7z:private] => ./
                [overwriteMode:Archive7z\Archive7z:private] => -aoa
                [timeout:Archive7z\Archive7z:private] => 60
            )

    )
         */

    if ($entry->getPath() === 'test/test.txt') {
        $entry->extractTo('path_to_extract_folder/'); // extract file
    }
}

echo $obj->getContent('test/test.txt'); // show content of the file
$obj->setOutputDirectory('path_to_extract_folder/')->extract(); // extract archive
$obj->setOutputDirectory('path_to_extract_pass_folder/')->setPassword('pass')->extractEntry('test/test.txt'); // extract password-protected entry

$obj->addEntry(__FILE__); // add file to archive
$obj->addEntry(__DIR__);  // add directory to archive (include subfolders)

$obj->renameEntry(__FILE__, __FILE__.'new'); // rename file in archive
$obj->delEntry(__FILE__.'new'); // remove file from archive
```
