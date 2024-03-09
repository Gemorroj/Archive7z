<?php

declare(strict_types=1);

namespace Archive7z;

use Symfony\Component\Process\Process;

class Archive7z
{
    use Archive7zTrait;

    /**
     * Overwrite all existing files.
     *
     * @see https://documentation.help/7-Zip/overwrite.htm
     *
     * @const string
     */
    public const OVERWRITE_MODE_A = '-aoa';
    /**
     * Skip extracting of existing files.
     *
     * @see https://documentation.help/7-Zip/overwrite.htm
     *
     * @const string
     */
    public const OVERWRITE_MODE_S = '-aos';
    /**
     * Auto rename extracting file (for example, name.txt will be renamed to name_1.txt).
     *
     * @see https://documentation.help/7-Zip/overwrite.htm
     *
     * @const string
     */
    public const OVERWRITE_MODE_U = '-aou';
    /**
     * Auto rename existing file (for example, name.txt will be renamed to name_1.txt).
     *
     * @see https://documentation.help/7-Zip/overwrite.htm
     *
     * @const string
     */
    public const OVERWRITE_MODE_T = '-aot';

    private string $binary7z;

    private string $filename;

    private ?string $password = null;

    /**
     * @see https://documentation.help/7-Zip/method.htm#Solid
     */
    private ?SolidMode $solidMode = null;

    /**
     * Encrypt archive header.
     *
     * Supported by 7z archives only.
     *
     * @see https://documentation.help/7-Zip/method.htm#HeaderEncrypt
     */
    protected bool $encryptFilenames = false;

    /**
     * From 0 to 9.
     *
     * @see https://documentation.help/7-Zip/method.htm#ZipX
     * @see https://documentation.help/7-Zip/method.htm#BZip2X
     * @see https://documentation.help/7-Zip/method.htm#SevenZipX
     */
    protected int $compressionLevel = 9;

    protected string $outputDirectory = './';

    /**
     * @see https://documentation.help/7-Zip/overwrite.htm
     */
    protected string $overwriteMode = self::OVERWRITE_MODE_A;

    /**
     * Cli process timeout in seconds.
     */
    protected ?float $timeout = 60.0;

    /**
     * @param string      $filename 7z archive filename
     * @param string|null $binary7z 7-zip binary path
     * @param float|null  $timeout  Timeout of system process
     *
     * @throws Exception
     */
    public function __construct(string $filename, ?string $binary7z = null, ?float $timeout = 60.0)
    {
        $this->filename = $filename;
        $this->timeout = $timeout;
        $this->binary7z = static::makeBinary7z($binary7z);
    }

    /**
     * @param string|null $binary7z 7-zip binary path
     *
     * @throws Exception
     */
    public static function get7zipInformation(?string $binary7z = null): string
    {
        $binary7z ??= static::makeBinary7z($binary7z);

        $cmd = [
            \str_replace('\\', '/', $binary7z),
            'i',
        ];

        $process = new Process($cmd);
        $process->mustRun();

        return $process->getOutput();
    }

    public function getOutputDirectory(): string
    {
        return $this->outputDirectory;
    }

    /**
     * @throws Exception
     */
    public function setOutputDirectory(string $directory): self
    {
        $outputDirectory = \realpath($directory);

        if (false === $outputDirectory) {
            throw new Exception('Output directory is not available');
        }

        if (!\is_writable($outputDirectory)) {
            throw new Exception('Output directory is not writable');
        }

        $this->outputDirectory = $outputDirectory;

        return $this;
    }

    public function setSolidMode(?SolidMode $solidMode): self
    {
        $this->solidMode = $solidMode;

        return $this;
    }

    public function getSolidMode(): ?SolidMode
    {
        return $this->solidMode;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getEncryptFilenames(): bool
    {
        return $this->encryptFilenames;
    }

    public function setEncryptFilenames(bool $encrypt): self
    {
        $this->encryptFilenames = $encrypt;

        return $this;
    }

    public function getOverwriteMode(): string
    {
        return $this->overwriteMode;
    }

    /**
     * @throws Exception
     */
    public function setOverwriteMode(string $mode): self
    {
        if (!\in_array($mode, [
            self::OVERWRITE_MODE_A,
            self::OVERWRITE_MODE_S,
            self::OVERWRITE_MODE_T,
            self::OVERWRITE_MODE_U,
        ], true)) {
            throw new Exception('Overwrite mode is not available');
        }

        $this->overwriteMode = $mode;

        return $this;
    }

    /**
     * @param string[] $arguments
     */
    private function makeProcess(string $command, array $arguments): Process
    {
        $cmd = [
            \str_replace('\\', '/', $this->binary7z),
            $command,
            $this->filename,
        ];
        \array_push($cmd, ...$arguments);

        return new Process($cmd, null, null, null, $this->timeout);
    }

    /**
     * @return string[]
     */
    private function decorateCmdExtract(): array
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
        if (static::isOsWin()) { // doesn't work on *nix
            $out[] = '-sccUTF-8';
            $out[] = '-scsUTF-8';
        }

        if (null !== $this->password) {
            $out[] = '-p'.$this->password;
        } else {
            $out[] = '-p '; // todo
        }

        return $out;
    }

    /**
     * @return string[]
     */
    private function decorateCmdCompress(): array
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
        if (static::isOsWin()) {  // doesn't work on *nix
            $out[] = '-sccUTF-8';
            $out[] = '-scsUTF-8';
        }

        if (null !== $this->password) {
            $out[] = '-p'.$this->password;

            // Encrypt archive header if 7z archive
            if ($this->encryptFilenames && '7z' === \pathinfo($this->filename, \PATHINFO_EXTENSION)) {
                $out[] = '-mhe=on';
            }
        }

        if ($this->solidMode) {
            $out[] = '-ms='.((string) $this->solidMode);
        }

        return $out;
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function extract(): void
    {
        $process = $this->makeProcess('x', \array_merge([
            $this->overwriteMode,
            '-o'.$this->outputDirectory,
        ], $this->decorateCmdExtract()));

        $this->execute($process);
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function extractEntry(string $path): void
    {
        $process = $this->makeProcess('x', \array_merge([
            $this->overwriteMode,
            '-o'.$this->outputDirectory,
        ], $this->decorateCmdExtract(), [$path]));

        $this->execute($process);
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function getContent(string $path): string
    {
        $process = $this->makeProcess('x', \array_merge([
            '-so',
            $path,
        ], $this->decorateCmdExtract()));

        return $this->execute($process)->getOutput();
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function getEntry(string $path): ?Entry
    {
        $entries = $this->getEntries($path, 1);

        return $entries ? $entries[0] : null;
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     *
     * @return Entry[]
     */
    public function getEntries(?string $pathMask = null, ?int $limit = null): array
    {
        $process = $this->makeProcess('l', \array_merge(
            ['-slt'],
            $this->decorateCmdExtract(),
            null !== $pathMask ? [$pathMask] : [],
        ));

        $this->execute($process);

        $out = \explode(\PHP_EOL, $process->getOutput());

        $list = [];
        $parser = new Parser($out);
        foreach ($parser->parseEntries($limit) as $v) {
            $list[] = new Entry($this, $v);
        }

        return $list;
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function getInfo(): Info
    {
        $process = $this->makeProcess('l', \array_merge([
            '-slt',
        ], $this->decorateCmdExtract(), ['Archive7z fake path']));

        $this->execute($process);

        $out = \explode(\PHP_EOL, $process->getOutput());

        $parser = new Parser($out);

        return new Info($parser);
    }

    /**
     * 7-zip >= 7.25.
     *
     * @param bool $storePath store real filesystem path in archive
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException|Exception
     */
    public function addEntry(string $path, bool $storePath = false): void
    {
        $args = [];
        $args[] = '-mx='.$this->compressionLevel;

        if ($storePath) {
            $args[] = '-spf';
            $args[] = $path;
        } else {
            $realPath = \realpath($path);
            if (false === $realPath) {
                throw new Exception('Can not resolve absolute path for "'.$path.'"');
            }

            if (\is_dir($realPath)) {
                $realPath .= '/*';
            }

            $args[] = $realPath;
        }

        $process = $this->makeProcess('a', \array_merge($args, $this->decorateCmdCompress()));

        $this->execute($process);
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function delEntry(string $path): void
    {
        $process = $this->makeProcess('d', \array_merge([
            $path,
        ], $this->decorateCmdExtract()));

        $this->execute($process);
    }

    /**
     * 7-zip >= 7.30 ( http://sourceforge.net/p/p7zip/discussion/383043/thread/f54fe89a/ ).
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function renameEntry(string $pathSrc, string $pathDest): void
    {
        $process = $this->makeProcess('rn', \array_merge([
            $pathSrc,
            $pathDest,
        ], $this->decorateCmdExtract()));

        $this->execute($process);
    }

    /**
     * Is valid archive?
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function isValid(): bool
    {
        $process = $this->makeProcess('t', $this->decorateCmdExtract());

        $this->execute($process);

        return \str_contains($process->getOutput(), 'Everything is Ok');
    }

    /**
     * List archive warnings.
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     *
     * @return string[]
     */
    public function getWarnings(): array
    {
        $process = $this->makeProcess('t', $this->decorateCmdExtract());

        $this->execute($process);

        $output = $process->getOutput();

        $pos = \strpos($output, \PHP_EOL.'--');
        if (false === $pos) {
            return [];
        }
        $header = \substr($output, 0, $pos);
        $warningsHeader = \PHP_EOL.'WARNINGS:';
        $warningsPos = \strrpos($header, $warningsHeader);
        if (false === $warningsPos) {
            return [];
        }

        $warningsStr = \substr($header, $warningsPos + \strlen($warningsHeader));
        $warningsStr = \trim($warningsStr);

        return \explode(\PHP_EOL, $warningsStr);
    }

    /**
     * Exit codes
     * 0 - Normal (no errors or warnings detected)
     * 1 - Warning (Non fatal error(s)). For example, some files cannot be read during compressing. So they were not compressed
     * 2 - Fatal error
     * 7 - Bad command line parameters
     * 8 - Not enough memory for operation
     * 255 - User stopped the process with control-C (or similar).
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    protected function execute(Process $process): Process
    {
        return $process->mustRun();
    }
}
