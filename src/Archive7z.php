<?php

namespace Archive7z;

use Symfony\Component\Process\Process;

class Archive7z
{
    use Archive7zTrait;

    /**
     * Overwrite all existing files
     *
     * @const string
     */
    const OVERWRITE_MODE_A = '-aoa';
    /**
     * Skip extracting of existing files
     *
     * @const string
     */
    const OVERWRITE_MODE_S = '-aos';
    /**
     * Auto rename extracting file (for example, name.txt will be renamed to name_1.txt)
     *
     * @const string
     */
    const OVERWRITE_MODE_U = '-aou';
    /**
     * Auto rename existing file (for example, name.txt will be renamed to name_1.txt)
     *
     * @const string
     */
    const OVERWRITE_MODE_T = '-aot';
    /**
     * @var string
     */
    private $binary7z;
    /**
     * @var string
     */
    private $filename;
    /**
     * @var string
     */
    private $password;
    /**
     * @var int (0-9)
     */
    protected $compressionLevel = 9;
    /**
     * @var string
     */
    protected $outputDirectory = './';
    /**
     * @var string
     */
    protected $overwriteMode = self::OVERWRITE_MODE_A;
    /**
     * @var float|int
     */
    protected $timeout = 60;


    /**
     * @param string $filename 7z archive filename
     * @param string $binary7z 7-zip binary path
     * @param float|int|null $timeout Timeout of system process
     *
     * @throws Exception
     */
    public function __construct($filename, $binary7z = null, $timeout = 60)
    {
        if (!\is_string($filename)) {
            throw new Exception('Filename must be string');
        }
        $this->filename = $filename;

        $this->binary7z = static::makeBinary7z($binary7z);

        if (!\is_numeric($timeout) && $timeout !== null) {
            throw new Exception('Timeout must be a numeric or null');
        }
        $this->timeout = $timeout;
    }

    /**
     * @return string
     */
    public function getOutputDirectory()
    {
        return $this->outputDirectory;
    }

    /**
     * @param string $directory
     *
     * @throws Exception
     * @return $this
     */
    public function setOutputDirectory($directory)
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

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param string $password
     *
     * @return $this
     */
    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return string
     */
    public function getOverwriteMode()
    {
        return $this->overwriteMode;
    }

    /**
     * @param string $mode
     *
     * @throws Exception
     * @return $this
     */
    public function setOverwriteMode($mode = Archive7z::OVERWRITE_MODE_A)
    {
        $this->overwriteMode = $mode;

        if (false === \in_array($this->overwriteMode, [
            self::OVERWRITE_MODE_A,
            self::OVERWRITE_MODE_S,
            self::OVERWRITE_MODE_T,
            self::OVERWRITE_MODE_U,
        ], true)) {
            throw new Exception('Overwrite mode is not available');
        }

        return $this;
    }

    /**
     * @param string $command
     * @param array $arguments
     * @return Process
     */
    private function makeProcess($command, array $arguments)
    {
        return new Process(\array_merge(
            [\str_replace('\\', '/', $this->binary7z)],
            [$command, $this->filename],
            $arguments
        ), null, null, null, $this->timeout);
    }


    /**
     * @return array
     */
    private function decorateCmdExtract()
    {
        $out = [];
        $out[] = '-y';

        /*
  { "utf-8", CP_UTF8 },
  { "win", CP_ACP },
  { "dos", CP_OEMCP },
  { "utf-16le", MY__CP_UTF16 },
  { "utf-16be", MY__CP_UTF16BE }
         */
        if (static::isOsWin()) { // not work for *nix
            $out[] = '-sccUTF-8';
            $out[] = '-scsUTF-8';
        }

        if (null !== $this->password) {
            $out[] = '-p' . $this->password;
        } else {
            $out[] = '-p '; //todo
        }

        return $out;
    }

    /**
     * @return string[]
     */
    private function decorateCmdCompress()
    {
        $out = [];
        $out[] = '-y';

        /*
  { "utf-8", CP_UTF8 },
  { "win", CP_ACP },
  { "dos", CP_OEMCP },
  { "utf-16le", MY__CP_UTF16 },
  { "utf-16be", MY__CP_UTF16BE }
         */
        if (static::isOsWin()) {  // not work for *nix
            $out[] = '-sccUTF-8';
            $out[] = '-scsUTF-8';
        }

        if (null !== $this->password) {
            $out[] = '-p' . $this->password;
        }

        return $out;
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function extract()
    {
        $process = $this->makeProcess('x', \array_merge([
            $this->overwriteMode,
            '-o' . $this->outputDirectory,
        ], $this->decorateCmdExtract()));

        $this->execute($process);
    }

    /**
     * @param string $path
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function extractEntry($path)
    {
        $process = $this->makeProcess('x', \array_merge([
            $this->overwriteMode,
            '-o' . $this->outputDirectory,
        ], $this->decorateCmdExtract(), [$path]));

        $this->execute($process);
    }

    /**
     * @param string $path
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     * @return string
     */
    public function getContent($path)
    {
        $process = $this->makeProcess('x', \array_merge([
            '-so',
            $path,
        ], $this->decorateCmdExtract()));

        return $this->execute($process)->getOutput();
    }

    /**
     * @param string $path
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     * @return Entry|null
     */
    public function getEntry($path)
    {
        $path = \str_replace('\\', '/', $path);

        foreach ($this->getEntries() as $v) {
            if ($v->getUnixPath() === $path) {
                return $v;
            }
        }

        return null;
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     * @return Entry[]
     */
    public function getEntries()
    {
        $process = $this->makeProcess('l', \array_merge([
            '-slt',
        ], $this->decorateCmdExtract()));

        $this->execute($process);

        $out = \explode(\PHP_EOL, $process->getOutput());

        $list = [];
        $parser = new Parser($out);
        foreach ($parser->parseEntries() as $v) {
            $list[] = new Entry($this, $v);
        }

        return $list;
    }

    /**
     * 7-zip >= 7.25
     *
     * @param string $path
     * @param bool $storePath store real filesystem path in archive
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException|Exception
     */
    public function addEntry($path, $storePath = false)
    {
        $args = [];
        $args[] = '-mx=' . (int)$this->compressionLevel;

        if ($storePath) {
            $args[] = '-spf';
            $args[] = $path;
        } else {
            $realPath = \realpath($path);
            if (false === $realPath) {
                throw new Exception('Can not resolve absolute path for "' . $path . '"');
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
     * @param string $path
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function delEntry($path)
    {
        $process = $this->makeProcess('d', \array_merge([
            $path,
        ], $this->decorateCmdExtract()));

        $this->execute($process);
    }

    /**
     * 7-zip >= 9.30 alpha ( http://sourceforge.net/p/p7zip/discussion/383043/thread/f54fe89a/ )
     *
     * @param string $pathSrc
     * @param string $pathDest
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function renameEntry($pathSrc, $pathDest)
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
     * @return bool
     */
    public function isValid()
    {
        $process = $this->makeProcess('t', $this->decorateCmdExtract());

        $this->execute($process);

        return false !== \strpos($process->getOutput(), 'Everything is Ok');
    }


    /**
     * Exit codes
     * 0 - Normal (no errors or warnings detected)
     * 1 - Warning (Non fatal error(s)). For example, some files cannot be read during compressing. So they were not compressed
     * 2 - Fatal error
     * 7 - Bad command line parameters
     * 8 - Not enough memory for operation
     * 255 - User stopped the process with control-C (or similar)
     *
     * @param Process $process
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     * @return Process
     */
    protected function execute(Process $process)
    {
        return $process->mustRun();
    }
}
