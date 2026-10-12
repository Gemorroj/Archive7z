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
     * -scs sets the list-file charset and accepts a numeric code page id
     * (ArchiveCommandLine.cpp: FindCharset, kListfileCharSet); it does not apply
     * to positional names, which is why a list file is used here.
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

        // '-mcp' is not a switch: it is the method property 'cp' (switch "m",
        // ArchiveCommandLine.cpp), stored by handlers as _forceCodePage/
        // _specifiedCodePage (TarHandler.cpp, ZipHandlerOut.cpp). ZIP reading
        // honours it for names without a UTF-8 flag / Info-ZIP Unicode path
        // (ZipItem.cpp: CItem::GetUnicodeString); other formats ignore it.
        $codepage = $options->getFallbackEncoding()->getCodepage();
        if (null !== $codepage) {
            $out[] = '-mcp='.$codepage;
        }

        // Console output charset; only "utf-8", "win" or "dos" are accepted
        // (ArchiveCommandLine.cpp: FindCharset, byteOnlyCodePages). It drives the
        // UString -> AString name conversion (StdOutStream.cpp:
        // Convert_UString_to_AString); the default is CP_OEMCP and differs per
        // platform, so force UTF-8 on Windows. -scs (list files) is not passed:
        // it already defaults to UTF-8 and does not affect positional names.
        if ($this->isWindows) {
            $out[] = '-sccUTF-8';
        }

        $password = $options->getPassword();
        if (null !== $password) {
            $out[] = '-p'.$password;
        } else {
            // Pass an empty password so 7-Zip never prompts for it interactively
            // (UserInputUtils.cpp: "Enter password (will not be echoed)"); without
            // it archives with an encrypted header block on stdin and time out.
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

        // See decorateExtract: -scc is the console output charset, forced to
        // UTF-8 on Windows; the list-file charset (-scs) already defaults to
        // UTF-8 and is not passed.
        if ($this->isWindows) {
            $out[] = '-sccUTF-8';
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
