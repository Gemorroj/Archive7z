<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Archive7z\Archive7z;
use Archive7z\Config\ArchiveOptions;
use Archive7z\Config\FallbackEncoding;
use Archive7z\Service\Container\TarNameReader;
use Archive7z\Service\Namer\NameEncoder;
use Archive7z\Service\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

$fixture = $argv[1] ?? 'tests/fixtures/tar/windows/totalcommander-9.21a/sample.tar';
$options = new ArchiveOptions(fallbackEncoding: FallbackEncoding::codepage('Windows-1251'));

echo "=== environment ===\n";
echo 'PHP_OS_FAMILY='.PHP_OS_FAMILY."\n";
echo 'binary='.ExecutableFinder::find()."\n";
echo 'fixture='.$fixture."\n";

echo "=== container reader (TarNameReader) raw names ===\n";
foreach ((new TarNameReader())->read($fixture) as $rawName) {
    echo '  utf8='.($rawName->utf8 ? '1' : '0').' hex='.bin2hex($rawName->value).' name='.$rawName->value."\n";
}

$library = new Archive7z($fixture, $options);

echo "=== library getEntries() (unmasked) ===\n";
foreach ($library->getEntries() as $entry) {
    echo '  hex='.bin2hex($entry->getPath()).' name='.$entry->getPath()."\n";
}

echo "=== library getEntries('чавес.jpg') (masked) ===\n";
$entries = $library->getEntries('чавес.jpg');
echo '  count='.\count($entries)."\n";
foreach ($entries as $entry) {
    echo '  hex='.bin2hex($entry->getPath()).' name='.$entry->getPath()."\n";
}

$mask = \mb_convert_encoding('чавес.jpg', 'Windows-1251', 'UTF-8');
$listFile = \tempnam(\sys_get_temp_dir(), 'diag_');
\file_put_contents($listFile, $mask."\n");

$binary = ExecutableFinder::find();
$command = [
    $binary,
    'l',
    $fixture,
    '-slt',
    '-y',
    '-mcp=1251',
    '-p ',
    '-scs1251',
    '@'.$listFile,
];
echo '=== raw command ==='."\n".\implode(' ', $command)."\n";

$process = new Process($command);
$process->run();
$output = $process->getOutput();

echo "=== raw 7zz output (printable) ===\n".$output."\n";
echo "=== raw 7zz output (hex) ===\n".\bin2hex($output)."\n";

$encoder = NameEncoder::resolve(FallbackEncoding::codepage('Windows-1251'), $output);
echo "=== NameEncoder::decode(output) hex ===\n".\bin2hex($encoder->decode($output))."\n";

\unlink($listFile);
