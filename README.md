# Wrapper 7-zip

[![License](https://poser.pugx.org/gemorroj/archive7z/license)](https://packagist.org/packages/gemorroj/archive7z)
[![Latest Stable Version](https://poser.pugx.org/gemorroj/archive7z/v/stable)](https://packagist.org/packages/gemorroj/archive7z)
[![Continuous Integration](https://github.com/Gemorroj/Archive7z/workflows/Continuous%20Integration/badge.svg)](https://github.com/Gemorroj/Archive7z/actions?query=workflow%3A%22Continuous+Integration%22)


### Features:
- Supports all 7-Zip formats:
    - Packing / unpacking: `7z`, `XZ`, `BZIP2`, `GZIP`, `TAR`, `ZIP` and `WIM`.
    - Unpacking only: `APFS`, `AR`, `ARJ`, `Base64`, `CAB`, `CHM`, `CPIO`, `CramFS`, `DMG`, `EXT`, `FAT`, `GPT`, `HFS`, `IHEX`, `ISO`, `LZH`, `LZMA`, `MBR`, `MSI`, `NSIS`, `NTFS`, `QCOW2`, `RAR`, `RPM`, `SquashFS`, `UDF`, `UEFI`, `VDI`, `VHD`, `VHDX`, `VMDK`, `XAR` and `Z`.
- List files and directories
- Get contents of any file from archive
- Extract files and directories
- Create, add, rename and delete entries
- Password-protected archives (including encrypted file names for the `7z` format)
- Solid archives, compression level and overwrite modes
- Decode legacy (non-UTF-8) entry names


### Requirements:
- PHP >= 8.2 with [proc_open](https://www.php.net/proc_open) enabled
- 7-Zip >= 16.04 (Windows), >= 21.01 (Linux), >= 21.02 (macOS) — [official](https://www.7-zip.org/) or a distribution package (`7zz`/`7z`)
- p7zip is not supported: no `@listfile`/`-scs`, and the 17.x fork also lacks `-aot`


### Installation:
```bash
composer require gemorroj/archive7z
```


### Examples:

#### Open, validate and list
```php
<?php
use Archive7z\Archive7z;

$archive = new Archive7z('path_to_archive.7z');

if (!$archive->isValid()) {
    throw new \RuntimeException('Incorrect archive');
}

// archive type, sizes, method, solid flag, ... (Model\Info)
print_r($archive->getInfo());

foreach ($archive->getEntries() as $entry) {
    printf("%-30s %10s\n", $entry->getPath(), $entry->getSize());
}

// filter by a wildcard mask and/or limit the count
$images = $archive->getEntries('*.jpg', 100);
```

#### Read a file and extract
```php
<?php
use Archive7z\Archive7z;

$archive = new Archive7z('path_to_archive.7z');

// bytes of a single file
$content = $archive->getContent('test/test.txt');

// one entry by its exact name (no wildcard expansion)
$entry = $archive->getEntry('test/test.txt');
echo $entry->getSize(), ' ', $entry->getCrc(), "\n";
$entry->extract('path_to_folder/'); // the same as $archive->extractEntry('test/test.txt', 'path_to_folder/')

// the whole archive
$archive->extract('path_to_folder/');
```

#### Create an archive
```php
<?php
use Archive7z\Archive7z;
use Archive7z\Config\Options;
use Archive7z\Config\SolidMode;

// the format comes from the extension; the file is created if it does not exist yet
$archive = new Archive7z('new.7z', new Options(
    compressionLevel: 9,
    solidMode: SolidMode::on(),
));

$archive->addEntry(__FILE__); // a single file (stored by basename)
$archive->addEntry(__DIR__);  // a directory, recursively

// zip / tar / tar.gz / xz / bz2 / wim are created the same way
$zip = new Archive7z('new.zip', new Options(compressionLevel: 6));
$zip->addEntry('path_to_file.txt');
```

#### Modify an archive
```php
<?php
use Archive7z\Archive7z;

$archive = new Archive7z('path_to_archive.7z');

$archive->addEntry('another.txt'); // append to an existing archive
$archive->renameEntry('another.txt', 'renamed.txt');
$archive->delEntry('renamed.txt');
```

#### Password-protected archives
```php
<?php
use Archive7z\Archive7z;
use Archive7z\Config\Options;

// create an encrypted archive, encrypting the file names too (7z format only)
$archive = new Archive7z('secret.7z', new Options(
    password: 'pass',
    encryptFilenames: true,
));
$archive->addEntry(__FILE__);

// read it back
$read = new Archive7z('secret.7z', new Options(password: 'pass'));
echo $read->getContent(basename(__FILE__));
```

#### Compression, solid mode and overwrite
```php
<?php
use Archive7z\Archive7z;
use Archive7z\Config\Options;
use Archive7z\Config\SolidMode;

$archive = new Archive7z('backup.7z', new Options(
    compressionLevel: 9,
    solidMode: SolidMode::on(),
    overwriteMode: Options::OVERWRITE_MODE_S, // skip existing files when extracting
));
$archive->addEntry(__DIR__);
$archive->extract('path_to_folder/');
```

#### Legacy (non-UTF-8) names
```php
<?php
use Archive7z\Archive7z;
use Archive7z\Config\Options;
use Archive7z\Config\FallbackEncoding;

// DOS (CP866) names in an old ZIP
$archive = new Archive7z('old.zip', new Options(
    fallbackEncoding: FallbackEncoding::codepage('CP866'),
));

foreach ($archive->getEntries() as $entry) {
    echo $entry->getPath(), "\n"; // e.g. чавес.jpg
}
```

#### Custom name decoding
```php
<?php
use Archive7z\Config\Options;
use Archive7z\Config\FallbackEncoding;

// let the library guess the code page from the listing (best effort)
new Options(fallbackEncoding: FallbackEncoding::detect());

// or decode the names yourself: $decode maps raw bytes -> UTF-8,
// the optional $encode maps a UTF-8 mask/path back to raw bytes
new Options(fallbackEncoding: FallbackEncoding::viaCallback(
    static function (string $raw): string {
        return \mb_convert_encoding($raw, 'UTF-8', 'CP866');
    },
    static function (string $name): string {
        return \mb_convert_encoding($name, 'CP866', 'UTF-8');
    },
));
```

#### Runtime options
```php
<?php
use Archive7z\Archive7z;
use Archive7z\Config\Options;

// a specific 7-Zip binary and a longer timeout (seconds)
$archive = new Archive7z('archive.7z', null, '/usr/bin/7zz', 120.0);

// environment variables for the 7-Zip process
new Options(env: ['LC_ALL' => 'ru_RU.UTF-8']);

// 7-Zip build info and archive warnings
echo Archive7z::get7zipInformation();
var_dump($archive->getWarnings());
```


### Filename encodings:
Names are returned as UTF-8. How the names are read depends on the format and on
the metadata it carries:

| Format            | Name metadata                                             | What happens                                                        |
|-------------------|-----------------------------------------------------------|---------------------------------------------------------------------|
| `7z`, `rar`, `wim`| Unicode-aware (7-Zip decodes them itself)                 | names come from the 7-Zip output; no fallback needed                |
| `zip`             | UTF-8 flag (bit 11) or Info-ZIP Unicode Path (`0x7075`)   | read from the container; used as UTF-8                              |
| `zip`             | none                                                      | raw bytes → decoded with `fallbackEncoding`                         |
| `tar`             | PAX `path` record (UTF-8 by specification)                | read from the container as UTF-8                                    |
| `tar`             | GNU/ustar header                                          | raw bytes → decoded with `fallbackEncoding`                         |
| `gzip`            | name in the header                                        | read from the container                                             |
| other             | —                                                         | names come from the 7-Zip output                                    |

For the formats without reliable metadata the code page is genuinely unknown, so
`fallbackEncoding` (default `asIs()` = names are assumed to be UTF-8) is the
caller's choice:

- `FallbackEncoding::codepage('CP866')` — DOS code page; exact.
- `FallbackEncoding::codepage('Windows-1251')` — Windows Cyrillic; exact.
- `FallbackEncoding::detect()` — guess from the listing; best effort.
- `FallbackEncoding::viaCallback($decode, $encode)` — your own mapping.

An explicit `codepage(...)` is the only mode that also passes a non-ASCII entry
name through a list file, so masks/paths with such names work everywhere; a
`detect()`/`asIs()`/`viaCallback(...)` mask goes on the 7-Zip command line and
matches only on builds comparing the raw bytes (official Linux `7zz`).


### Limitations:
- `zip`/`tar`/`gzip` names are read straight from the container (exact and
  cross-platform); other formats rely on 7-Zip's own output.
- Legacy archives that store names without any encoding metadata (old `zip`
  with 8-bit names, plain `tar`) can not be decoded automatically with certainty
  — provide `fallbackEncoding`, see [#15](https://github.com/Gemorroj/Archive7z/issues/15).
