# Wrapper 7-zip (p7zip)

[![License](https://poser.pugx.org/gemorroj/archive7z/license)](https://packagist.org/packages/gemorroj/archive7z)
[![Latest Stable Version](https://poser.pugx.org/gemorroj/archive7z/v/stable)](https://packagist.org/packages/gemorroj/archive7z)
[![Build Status Travis](https://secure.travis-ci.org/Gemorroj/Archive7z.png?branch=master)](https://travis-ci.org/Gemorroj/Archive7z)
[![Build Status AppVeyor](https://ci.appveyor.com/api/projects/status/3i7po8fka1eqdb8a)](https://ci.appveyor.com/project/Gemorroj/archive7z)


### Features:

- Support all 7-zip formats: `7z`, `XZ`, `BZIP2`, `GZIP`, `TAR`, `ZIP`, `WIM`, `AR`, `ARJ`, `CAB`, `CHM`, `CPIO`, `CramFS`, `DMG`, `EXT`, `FAT`, `GPT`, `HFS`, `IHEX`, `ISO`, `LZH`, `LZMA`, `MBR`, `MSI`, `NSIS`, `NTFS`, `QCOW2`, `RAR`, `RPM`, `SquashFS`, `UDF`, `UEFI`, `VDI`, `VHD`, `VMDK`, `WIM`, `XAR` and `Z`
- Unpacking archives
- Extract any directory or file
- List files and directories
- Get contents of any file from archive
- Delete files or directories (not `RAR`)
- Add files or directories (not `RAR`)


### Requirements:

- PHP >= 5.6
- shell
- **7-zip >= 7.30 (p7zip >= 9.38)**


### Notes:
- https://sourceforge.net/p/p7zip/discussion/383043/thread/fa143cf2/


### Installation:
```bash
composer require gemorroj/archive7z
```


### Example:

```php
<?php
use Archive7z\Archive7z;

$obj = new Archive7z('path_to_7z_file.7z');

if (!$obj->isValid()) {
    throw new Exception('Incorrect archive');
}


foreach ($obj->getEntries() as $entry) {
        print_r($entry);
        /*
Archive7z\Entry Object
(
    [path:Archive7z\Entry:private] => 1.jpg
    [size:Archive7z\Entry:private] => 91216
    [packedSize:Archive7z\Entry:private] => 165102
    [modified:Archive7z\Entry:private] => 2013-06-10 09:56:07
    [attributes:Archive7z\Entry:private] => A
    [crc:Archive7z\Entry:private] => 871345C2
    [encrypted:Archive7z\Entry:private] => -
    [method:Archive7z\Entry:private] => LZMA2:192k
    [block:Archive7z\Entry:private] => 0
    [comment:Archive7z\Entry:private] => 
    [hostOs:Archive7z\Entry:private] => 
    [folder:Archive7z\Entry:private] => 
    [archive:Archive7z\Entry:private] => Archive7z\Archive7z Object
        (
            [compressionLevel:protected] => 9
            [binary7z:Archive7z\Archive7z:private] => C:\Program Files\7-Zip\7z.exe
            [filename:Archive7z\Archive7z:private] => s:\VCS\Git\Archive7z\tests/fixtures/7zip-18.05/test.7z
            [password:Archive7z\Archive7z:private] => 
            [outputDirectory:Archive7z\Archive7z:private] => ./
            [overwriteMode:Archive7z\Archive7z:private] => -aoa
        )

)
         */

    if ($entry->getPath() === 'test/test.txt') {
        $entry->extractTo('path_to_extract_folder/'); // extract file
    }
}

echo $obj->getContent('test/test.txt'); // show content of the file

$obj->setOutputDirectory('path_to_extract_folder/')->extract(); // extract archive

$obj->addEntry(__FILE__); // add file to archive
$obj->addEntry(__DIR__);  // add directory to archive (include subfolders)
```
