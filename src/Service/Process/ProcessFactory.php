<?php

declare(strict_types=1);

namespace Archive7z\Service\Process;

use Archive7z\Config\ArchiveOptions;
use Symfony\Component\Process\Process;

/**
 * Builds and runs 7-Zip CLI processes.
 */
final class ProcessFactory
{
    /**
     * @param array<string, string> $env additional environment variables for the process
     */
    public function __construct(
        private readonly string $binary7z,
        private readonly string $filename,
        private readonly ?float $timeout,
        private readonly bool $isWindows,
        private readonly array $env = [],
    ) {
    }

    /**
     * Builds the arguments selecting a single encoded entry name/mask.
     *
     * When the fallback encoding defines a code page, the mask is passed through
     * a temporary list file with an explicit -scs code page, so 8-bit names are
     * matched the same way on every platform. Otherwise it is a regular argument.
     *
     * @param string[] $arguments
     *
     * @return array{0: string[], 1: MaskListFile|null}
     */
    public function withMask(array $arguments, ArchiveOptions $options, ?string $encodedMask): array
    {
        if (null === $encodedMask) {
            return [$arguments, null];
        }

        $codepage = $options->getFallbackEncoding()->getCodepage();
        if (null === $codepage) {
            $arguments[] = $encodedMask;

            return [$arguments, null];
        }

        $filtered = [];
        foreach ($arguments as $argument) {
            if (!\str_starts_with($argument, '-scs')) {
                $filtered[] = $argument;
            }
        }

        $maskFile = new MaskListFile($encodedMask);
        $filtered[] = '-scs'.$codepage;
        $filtered[] = '@'.$maskFile->getPath();

        return [$filtered, $maskFile];
    }

    /**
     * @param string[] $arguments
     */
    public function create(string $command, array $arguments): Process
    {
        $cmd = [
            \str_replace('\\', '/', $this->binary7z),
            $command,
            $this->filename,
        ];
        \array_push($cmd, ...$arguments);

        return new Process($cmd, null, [] === $this->env ? null : $this->env, null, $this->timeout);
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function run(Process $process): Process
    {
        return $process->mustRun();
    }

    /**
     * @return string[]
     */
    public function decorateExtract(ArchiveOptions $options): array
    {
        $out = [];
        $out[] = '-y';

        $codepage = $options->getFallbackEncoding()->getCodepage();
        if (null !== $codepage) {
            $out[] = '-mcp='.$codepage;
        }

        // https://github.com/p7zip-project/p7zip/blob/v17.05/CPP/7zip/UI/Common/ArchiveCommandLine.cpp#L959-L966
        /*
  { "utf-8", CP_UTF8 },
  { "win", CP_ACP },
  { "dos", CP_OEMCP },
  { "utf-16le", MY__CP_UTF16 },
  { "utf-16be", MY__CP_UTF16BE }
         */
        if ($this->isWindows) { // doesn't work on *nix
            $out[] = '-sccUTF-8';
            $out[] = '-scsUTF-8';
        }

        $password = $options->getPassword();
        if (null !== $password) {
            $out[] = '-p'.$password;
        } else {
            // Pass an empty password so 7-Zip never prompts for it interactively
            // (e.g. archives with an encrypted header). Without -p the process
            // blocks on "Enter password (will not be echoed)" and times out.
            $out[] = '-p ';
        }

        return $out;
    }

    /**
     * @return string[]
     */
    public function decorateCompress(ArchiveOptions $options): array
    {
        $out = [];
        $out[] = '-y';

        // https://github.com/p7zip-project/p7zip/blob/v17.05/CPP/7zip/UI/Common/ArchiveCommandLine.cpp#L959-L966
        /*
  { "utf-8", CP_UTF8 },
  { "win", CP_ACP },
  { "dos", CP_OEMCP },
  { "utf-16le", MY__CP_UTF16 },
  { "utf-16be", MY__CP_UTF16BE }
         */
        if ($this->isWindows) {  // doesn't work on *nix
            $out[] = '-sccUTF-8';
            $out[] = '-scsUTF-8';
        }

        $password = $options->getPassword();
        if (null !== $password) {
            $out[] = '-p'.$password;

            // Encrypt archive header if 7z archive
            if ($options->isEncryptFilenames() && '7z' === \pathinfo($this->filename, \PATHINFO_EXTENSION)) {
                $out[] = '-mhe=on';
            }
        }

        $solidMode = $options->getSolidMode();
        if (null !== $solidMode) {
            $out[] = '-ms='.((string) $solidMode);
        }

        return $out;
    }
}
