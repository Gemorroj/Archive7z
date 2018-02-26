# Wrapper 7-zip (p7zip)

[![License](https://poser.pugx.org/gemorroj/archive7z/license)](https://packagist.org/packages/gemorroj/archive7z)
[![Latest Stable Version](https://poser.pugx.org/gemorroj/archive7z/v/stable)](https://packagist.org/packages/gemorroj/archive7z)
[![Build Status Travis](https://secure.travis-ci.org/Gemorroj/Archive7z.png?branch=master)](https://travis-ci.org/Gemorroj/Archive7z)
[![Build Status AppVeyor](https://ci.appveyor.com/api/projects/status/3i7po8fka1eqdb8a)](https://ci.appveyor.com/project/Gemorroj/archive7z)


### Features:

- Unpacking archives (all supports 7zip archiver formats - 7z, XZ, BZIP2, GZIP, TAR, ZIP, WIM, AR, ARJ, CAB, CHM, CPIO, CramFS, DMG, EXT, FAT, GPT, HFS, IHEX, ISO, LZH, LZMA, MBR, MSI, NSIS, NTFS, QCOW2, RAR, RPM, SquashFS, UDF, UEFI, VDI, VHD, VMDK, WIM, XAR and Z)
- Extract any directory or file
- List files and directories
- Get contents of any file from archive
- Delete files or directories (only 7z format now)
- Add files or directories (only 7z format now)


### Requirements:

- PHP >= 5.6
- shell
- **7-zip >= 7.30 (p7zip >= 9.38)**


### Notes:

 - List of files/directories is always in UTF-8
 - When unpacking, the names of the files/directories you need to specify the encoding of the current file system
 - When you unpack the archive, the file names/directories are written in the encoding of the current file system


### Installation:

- add to composer.json:

```json
{
    "require": {
        "gemorroj/archive7z": "dev-master"
    }
}
```
- install:

```bash
$ php composer.phar update gemorroj/archive7z
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
    [path:Archive7z\Entry:private] => test/test.txt
    [size:Archive7z\Entry:private] => 14
    [packedSize:Archive7z\Entry:private] => 19
    [modified:Archive7z\Entry:private] => 2013-10-23 16:28:51
    [attributes:Archive7z\Entry:private] => A
    [crc:Archive7z\Entry:private] => A346C3A7
    [encrypted:Archive7z\Entry:private] => -
    [method:Archive7z\Entry:private] => LZMA:16
    [block:Archive7z\Entry:private] => 2
    [archive:Archive7z\Entry:private] => Archive7z\Archive7z Object
        (
            [compressionLevel:protected] => 9
            [cliLinux:protected] => /usr/bin/7za
            [cliBsd:protected] => /usr/local/bin/7za
            [cliWindows:protected] => C:\Program Files\7-Zip\7z.exe
            [cli:Archive7z\Archive7z:private] => C:\Program Files\7-Zip\7z.exe
            [filename:Archive7z\Archive7z:private] => path_to_7z_file.7z
            [password:Archive7z\Archive7z:private] => 
            [outputDirectory:Archive7z\Archive7z:private] => ./
            [overwriteMode:Archive7z\Archive7z:private] => -aoa
            [changeSystemLocale:protected] => 
            [systemLocaleNix:protected] => en_US.utf8
            [systemLocaleWin:protected] => 65001
        )

)
         */

    if ($entry->getPath() === 'test/test.txt') {
        $entry->extractTo('path_to_extract_folder/'); // extract file
    }
}

echo $obj->getContent('test/test.txt'); // show content of the file

$obj->setOutputDirectory('path_to_extract_folder/');
$obj->extract(); // extract archive

$obj->addEntry(__FILE__); // add file to archive
$obj->addEntry(__DIR__, true);  // add directory to archive (include subfolders)
```
