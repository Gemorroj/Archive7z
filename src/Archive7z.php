<?php

namespace Archive7z;

use Symfony\Component\Process\Process;

class Archive7z
{
    /**
     * Error codes
     * 0 - Normal (no errors or warnings detected)
     * 1 - Warning (Non fatal error(s)). For example, some files cannot be read during compressing. So they were not compressed
     * 2 - Fatal error
     * 7 - Bad command line parameters
     * 8 - Not enough memory for operation
     * 255 - User stopped the process with control-C (or similar)
     */

    /**
     * 7z uses plugins (7z.so and Codecs/Rar.so) to handle archives.
     * 7za is a stand-alone executable (7za handles less archive formats than 7z).
     * 7zr is a light stand-alone executable that supports only 7z/LZMA/BCJ/BCJ2.
     */

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
     * @var int (0-9)
     */
    protected $compressionLevel = 9;
    /**
     * @var array
     */
    protected $cliLinux = ['/usr/bin/7z', '/usr/bin/7za'];
    /**
     * @var array
     */
    protected $cliBsd = ['/usr/local/bin/7z', '/usr/local/bin/7za'];
    /**
     * @var array
     */
    protected $cliWindows = ['C:\Program Files\7-Zip\7z.exe']; // %ProgramFiles%\7-Zip\7z.exe
    /**
     * @var string
     */
    private $cli;
    /**
     * @var string
     */
    private $filename;
    /**
     * @var string
     */
    private $password;
    /**
     * @var string
     */
    private $outputDirectory = './';
    /**
     * @var string
     */
    private $overwriteMode = self::OVERWRITE_MODE_A;
    /**
     * @var bool
     */
    protected $changeSystemLocale = false;
    /**
     * @var string
     */
    protected $systemLocaleNix = 'en_US.utf8';
    /**
     * @var string
     */
    protected $systemLocaleWin = '65001';


    /**
     * @param string $filename 7z archive filename
     * @param string $cli CLI path
     *
     * @throws Exception
     */
    public function __construct($filename, $cli = null)
    {
        if (null === $cli) {
            $cli = $this->getAutoCli();
        }

        $this->setCli($cli);
        $this->setFilename($filename);
    }


    /**
     * @return string
     */
    protected function isOsWin()
    {
        return false !== \stripos(\PHP_OS, 'Win');
    }


    /**
     * @return string
     */
    protected function isOsBsd()
    {
        return false !== \stripos(\PHP_OS, 'BSD') || false !== \stripos(\PHP_OS, 'Darwin');
    }


    /**
     * @return string|null
     */
    protected function getAutoCli()
    {
        $cliPath = null;
        if ($this->isOsBsd()) {
            $cliPath = $this->cliBsd;
        } else
            if ($this->isOsWin()) {
            	$cliPath = $this->cliWindows;
            }
        if (null === $cliPath) {
            $cliPath = $this->cliLinux;
        }

        foreach ($cliPath as $cli) {
            if (\is_file($cli)) {
                $cliPath = $cli;
                break;
            }
        }

        return $cliPath;
    }

    /**
     * @return string
     */
    public function getCli()
    {
        return $this->cli;
    }

    /**
     * @param string $path
     *
     * @throws Exception
     * @return $this
     */
    public function setCli($path)
    {
        $cli = \realpath($path);

        if (false === $cli) {
            throw new Exception('Cli is not available');
        }

        if (!\is_executable($cli)) {
            throw new Exception('Cli is not executable');
        }

        $this->cli = $cli;

        return $this;
    }

    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @param string $filename
     *
     * @throws Exception
     * @return $this
     */
    public function setFilename($filename)
    {
        /*
        $filename = \realpath($filename);

        if (false === $filename) {
            throw new Exception('Filename is not available');
        }

        if (!\is_readable($filename)) {
            throw new Exception('Filename is not readable');
        }
        */

        $this->filename = $filename;

        return $this;
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
    public function setOutputDirectory($directory = './')
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
     * @param bool $changeSystemLocale
     * @return $this
     */
    public function setChangeSystemLocale($changeSystemLocale)
    {
        $this->changeSystemLocale = $changeSystemLocale;

        return $this;
    }


    /**
     * @return bool
     */
    public function getChangeSystemLocale()
    {
        return $this->changeSystemLocale;
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
     * @param array $arguments
     * @return Process
     */
    private function makeProcess(array $arguments)
    {
        return new Process(\array_merge([\str_replace('\\', '/', $this->cli)], $arguments));
    }


    /**
     * @return array
     */
    private function decorateCmdExtract()
    {
        $out = [];
        $out[] = '-y';

        if ($this->isOsWin()) { // not work for *nix
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
     * @return array
     */
    private function decorateCmdCompress()
    {
        $out = [];
        $out[] = '-y';

        if ($this->isOsWin()) {  // not work for *nix
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
        $process = $this->makeProcess(\array_merge([
            'x',
            $this->filename,
            $this->overwriteMode,
            '-o' . $this->outputDirectory,
        ], $this->decorateCmdExtract()));

        $this->execute($process);
    }

    /**
     * @param string $file
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function extractEntry($file)
    {
        $process = $this->makeProcess(\array_merge([
            'x',
            $this->filename,
            $this->overwriteMode,
            '-o' . $this->outputDirectory,
        ], $this->decorateCmdExtract(), [$file]));

        $this->execute($process);
    }

    /**
     * @param string $file
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     * @return string
     */
    public function getContent($file)
    {
        $process = $this->makeProcess(\array_merge([
            'x',
            $this->filename,
            '-so',
            $file,
        ], $this->decorateCmdExtract()));

        return $process->mustRun()->getOutput();
    }

    /**
     * @param string $file
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     * @return Entry|null
     */
    public function getEntry($file)
    {
        $file = \str_replace('\\', '/', $file);

        foreach ($this->getEntries() as $v) {
            if ($v->getUnixPath() === $file) {
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
        $process = $this->makeProcess(\array_merge([
            'l',
            $this->filename,
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
     * 7-zip >= 7.25 ( http://sourceforge.net/p/p7zip/discussion/383043/thread/f54fe89a/ )
     * @todo custom format (-t7z, -tzip, -tgzip, -tbzip2 or -ttar)
     *
     * @param string $file
     * @param bool $includeSubFiles
     * @param bool $storePath
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException|Exception
     */
    public function addEntry($file, $includeSubFiles = false, $storePath = false)
    {
        $args = [];
        $args[] = 'a';
        $args[] = $this->filename;
        $args[] = '-mx=' . (int)$this->compressionLevel;
        $args[] = '-t7z';

        if ($storePath) {
            $args[] = '-spf';
            $args[] = '-i!' . $file;
        } else {
            $realPath = \realpath($file);
            if (false === $realPath) {
                throw new Exception('Can not resolve absolute path for "' . $file . '"');
            }
            $args[] = $realPath;
        }

        if (!$includeSubFiles && \is_dir($file)) {
            $args[] = '-x!' . \rtrim($file, '/') . '/*';
        }

        $process = $this->makeProcess(\array_merge($args, $this->decorateCmdCompress()));

        $this->execute($process);
    }

    /**
     * @param string $file
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function delEntry($file)
    {
        $process = $this->makeProcess(\array_merge([
            'd',
            $this->filename,
            $file,
        ], $this->decorateCmdExtract()));

        $this->execute($process);
    }

    /**
     * 7-zip >= 7.30 ( http://sourceforge.net/p/p7zip/discussion/383043/thread/f54fe89a/ )
     *
     * @param string $fileSrc
     * @param string $fileDest
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function renameEntry($fileSrc, $fileDest)
    {
        $process = $this->makeProcess(\array_merge([
            'rn',
            $this->filename,
            $fileSrc,
            $fileDest,
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
        $process = $this->makeProcess(\array_merge([
            't',
            $this->filename,
        ], $this->decorateCmdExtract()));

        $this->execute($process);

        return false !== \strpos($process->getOutput(), 'Everything is Ok');
    }


    /**
     * @param Process $process
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    protected function execute(Process $process)
    {
        if ($this->getChangeSystemLocale()) {
            if ($this->isOsWin()) {
                $localeProcess = new Process([
                    'chcp',
                    $this->systemLocaleWin,
                ]);
            } else {
                $localeProcess = new Process([
                    'LANG=' . $this->systemLocaleNix,
                ]);
            }

            $process->setCommandLine($localeProcess->getCommandLine() . ' & ' . $process->getCommandLine());
        }

        $process->mustRun();
    }
}
