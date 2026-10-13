<?php

declare(strict_types=1);

namespace Archive7z\Service\Process;

use Archive7z\Config\ArchiveOptions;
use Archive7z\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Builds and runs 7-Zip CLI processes.
 */
final class ProcessFactory
{
    private ?bool $supportsCodePage = null;

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

    public function isWindows(): bool
    {
        return $this->isWindows;
    }

    /**
     * Whether the archive handler accepts the "-mcp" method property.
     *
     * Only ZIP and TAR do; for 7z/gzip/bzip2/wim it makes 7-Zip fail to open
     * the archive. Detected from the container magic bytes.
     */
    private function supportsCodePage(): bool
    {
        if (null === $this->supportsCodePage) {
            $header = @\file_get_contents($this->filename, false, null, 0, 262);

            $this->supportsCodePage = \is_string($header) && (
                \str_starts_with($header, "PK\x03\x04")
                || \str_starts_with($header, "PK\x05\x06")
                || \str_starts_with($header, "PK\x06\x06")
                || 'ustar' === \substr($header, 257, 5)
            );
        }

        return $this->supportsCodePage;
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
     * Runs the process, tolerating 7-Zip's non-fatal warning exit code.
     *
     * 7-Zip exit codes (ExitCode.h): 0 success, 1 non-fatal warnings, >=2 fatal.
     *
     * @throws ProcessFailedException
     */
    public function run(Process $process): Process
    {
        $process->run();

        $exitCode = $process->getExitCode();
        if (0 !== $exitCode && 1 !== $exitCode) {
            throw new ProcessFailedException($process);
        }

        return $process;
    }

    /**
     * The process stdout with CRLF line endings normalized to "\n".
     *
     * 7-Zip separates lines by "\r\n" on Windows and "\n" elsewhere, which does
     * not necessarily match {@see PHP_EOL}, so parsing must not rely on it.
     * Only CRLF is rewritten: a lone "\r" is content (a filename may contain
     * it) and must be preserved. For text output only — never binary content.
     */
    public function textOutput(Process $process): string
    {
        return \str_replace("\r\n", "\n", $process->getOutput());
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
        // _specifiedCodePage and honoured by ZIP/TAR reading for names without
        // a UTF-8 flag / Info-ZIP Unicode path (ZipItem.cpp:
        // CItem::GetUnicodeString). Other handlers reject the property and fail
        // to open the archive, so it is passed only for ZIP/TAR.
        $codepage = $options->getFallbackEncoding()->getCodepage();
        if (null !== $codepage && $this->supportsCodePage()) {
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
