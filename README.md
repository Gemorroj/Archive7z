# Wrapper 7-zip

[![License](https://poser.pugx.org/gemorroj/archive7z/license)](https://packagist.org/packages/gemorroj/archive7z)
[![Latest Stable Version](https://poser.pugx.org/gemorroj/archive7z/v/stable)](https://packagist.org/packages/gemorroj/archive7z)
[![Continuous Integration](https://github.com/Gemorroj/Archive7z/workflows/Continuous%20Integration/badge.svg)](https://github.com/Gemorroj/Archive7z/actions?query=workflow%3A%22Continuous+Integration%22)


### Features:
- Supported all 7-zip formats:
    - Packing / unpacking: `7z`, `XZ`, `BZIP2`, `GZIP`, `TAR`, `ZIP` and `WIM`.
    - Unpacking only: `APFS`, `AR`, `ARJ`, `Base64`, `CAB`, `CHM`, `CPIO`, `CramFS`, `DMG`, `EXT`, `FAT`, `GPT`, `HFS`, `IHEX`, `ISO`, `LZH`, `LZMA`, `MBR`, `MSI`, `NSIS`, `NTFS`, `QCOW2`, `RAR`, `RPM`, `SquashFS`, `UDF`, `UEFI`, `VDI`, `VHD`, `VHDX`, `VMDK`, `XAR` and `Z`.
- List files and directories
- Get contents of any file from archive
- Delete files or directories
- Add files or directories


### Requirements:
- PHP >= 8.2 with [proc_open](https://www.php.net/proc_open) enabled
- 7-Zip >= 16.04 (Windows), >= 21.01 (Linux), >= 21.02 (macOS) — [official](https://www.7-zip.org/) or a distribution package (`7zz`/`7z`)
- p7zip is not supported: no `@listfile`/`-scs`, and the 17.x fork also lacks `-aot`


### Installation:
```bash
composer require gemorroj/archive7z
```


### Notes:
- Pass `env` to `ArchiveOptions` for 7-Zip environment variables, e.g. `env: ['LC_ALL' => 'ru_RU.UTF-8']`.


### Limitations:
- Filenames are assumed UTF-8. Non-UTF-8 names (CP866, CP1251, ...) need `ArchiveOptions::fallbackEncoding` (`FallbackEncoding::codepage(...)`, `detect()` or a custom `viaCallback(...)`), otherwise they are unreadable on Linux and may be mangled on Windows, see [#15](https://github.com/Gemorroj/Archive7z/issues/15).
- `zip`/`tar`/`gzip` names are read from the container (exact, cross-platform); other formats use the 7-Zip output instead, so Unicode-name formats like `7z`/`rar`/`wim` need no fallback.
- A non-ASCII name used in a mask/path is portable only with `FallbackEncoding::codepage(...)` (passed via a list file); with `detect()`, `asIs()` or `viaCallback(...)` the mask goes on the 7-Zip command line and matches only on builds comparing the raw bytes (official Linux `7zz`), not on Windows or on builds forcing a UTF-8 command line.


### Example:
```php
<?php
use Archive7z\Archive7z;
use Archive7z\Config\ArchiveOptions;
use Archive7z\Config\SolidMode;

$obj = new Archive7z(
    'path_to_7z_file.7z',
    new ArchiveOptions(
        compressionLevel: 6,
        overwriteMode: ArchiveOptions::OVERWRITE_MODE_S,
    ),
);

if (!$obj->isValid()) {
    throw new \RuntimeException('Incorrect archive');
}

print_r($obj->getInfo());
/*
Archive7z\Model\Info Object
(
    [data:Archive7z\Model\Info:private] => Array
        (
            ...
        )

    [path:Archive7z\Model\Info:private] => /full_path_to/test.7z
    [type:Archive7z\Model\Info:private] => 7z
    [physicalSize:Archive7z\Model\Info:private] => 165343
    [headersSize:Archive7z\Model\Info:private] => 241
    [method:Archive7z\Model\Info:private] => LZMA2:192k
    [solid:Archive7z\Model\Info:private] => +
    [blocks:Archive7z\Model\Info:private] => 1
    [codePage:Archive7z\Model\Info:private] => 
)
*/


// $obj->getEntries('test', 100)
foreach ($obj->getEntries() as $entry) {
    print_r($entry);
/*
Archive7z\Model\Entry Object
(
    [data:Archive7z\Model\Entry:private] => Array
        (
            ...
        )

    [path:Archive7z\Model\Entry:private] => 1.jpg
    [size:Archive7z\Model\Entry:private] => 91216
    [packedSize:Archive7z\Model\Entry:private] => 165102
    [modified:Archive7z\Model\Entry:private] => 2013-06-10 09:56:07.0000000
    [created:Archive7z\Model\Entry:private] => 
    [attributes:Archive7z\Model\Entry:private] => A
    [crc:Archive7z\Model\Entry:private] => 871345C2
    [encrypted:Archive7z\Model\Entry:private] => -
    [method:Archive7z\Model\Entry:private] => LZMA2:192k
    [block:Archive7z\Model\Entry:private] => 0
    [comment:Archive7z\Model\Entry:private] => 
    [hostOs:Archive7z\Model\Entry:private] => 
    [characteristics:Archive7z\Model\Entry:private] => 
    [folder:Archive7z\Model\Entry:private] => 
    [archive:Archive7z\Model\Entry:private] => Archive7z\Archive7z Object
        (
            ...
        )
)
*/

    if ($entry->getPath() === 'test/test.txt') {
        $entry->extract('path_to_extract_folder/'); // extract the file
    }
}

echo $obj->getContent('test/test.txt'); // show content of the file
$obj->extract('path_to_extract_folder/'); // extract the archive

// password-protected archive: pass the password to ArchiveOptions
$obj = new Archive7z('path_to_protected.7z', new ArchiveOptions(password: 'pass'));
$obj->extractEntry('test/test.txt', 'path_to_extract_pass_folder/'); // extract the password-protected entry

$archive = new Archive7z('path_to_archive.7z', new ArchiveOptions(solidMode: SolidMode::off()));
$archive->addEntry(__DIR__);  // add directory to the archive (include subfolders)
$archive->addEntry(__FILE__); // add file to the archive

$archive->renameEntry(__FILE__, __FILE__.'new'); // rename the file in the archive
$archive->delEntry(__FILE__.'new'); // remove the file from the archive


// Custom name encoding:
// When an archive stores non-UTF-8 names without encoding metadata (old `zip`/`tar`)
use Archive7z\Archive7z;
use Archive7z\Config\ArchiveOptions;
use Archive7z\Config\FallbackEncoding;

// e.g. an old DOS/Windows archive with CP866 names; a legacy tool with its own
// scheme, or mixed encodings, is handled the same way.
$obj = new Archive7z(
    'path_to_archive.zip',
    new ArchiveOptions(fallbackEncoding: FallbackEncoding::viaCallback(
        static function (string $raw): string {     // raw name bytes -> UTF-8
            return \mb_convert_encoding($raw, 'UTF-8', 'CP866');
        },
        static function (string $name): string {    // UTF-8 name -> raw bytes (masks/paths)
            return \mb_convert_encoding($name, 'CP866', 'UTF-8');
        },
    )),
);

foreach ($obj->getEntries() as $entry) {
    echo $entry->getPath(), "\n"; // decoded names
}
```
