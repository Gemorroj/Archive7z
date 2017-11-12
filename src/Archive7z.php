<?php

namespace Archive7z;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

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
     * @var string
     */
    protected $cliLinux = '/usr/bin/7za';
    /**
     * @var string
     */
    protected $cliBsd = '/usr/local/bin/7za';
    /**
     * @var string
     */
    protected $cliWindows = 'C:\Program Files\7-Zip\7z.exe'; // %ProgramFiles%\7-Zip\7z.exe
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
     * @return string
     */
    protected function getAutoCli()
    {
        if ($this->isOsBsd()) {
            return $this->cliBsd;
        }
        if ($this->isOsWin()) {
            return $this->cliWindows;
        }

        return $this->cliLinux;
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

        if (false === \is_executable($cli)) {
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

        if (false === \is_readable($filename)) {
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

        if (false === \is_writable($outputDirectory)) {
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
        ])) {
            throw new Exception('Overwrite mode is not available');
        }

        return $this;
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function extract()
    {
        $processBuilder = $this->getProcessBuilder()->setArguments([
            'x',
            $this->filename,
            $this->overwriteMode,
            '-o' . $this->outputDirectory,

        ]);

        $processBuilder = $this->decorateCmdExtract($processBuilder);

        $this->execute($processBuilder);
    }

    /**
     * @return ProcessBuilder
     */
    private function getProcessBuilder()
    {
        $processBuilder = new ProcessBuilder();
        $processBuilder->setPrefix(\str_replace('\\', '/', $this->cli));

        return $processBuilder;
    }

    /**
     * @param ProcessBuilder $processBuilder
     * @return ProcessBuilder
     */
    private function decorateCmdExtract(ProcessBuilder $processBuilder)
    {
        $processBuilder->add('-y');

        if ($this->isOsWin()) { // not work for *nix
            $processBuilder->add('-sccUTF-8');
            $processBuilder->add('-scsUTF-8');
        }

        if (null !== $this->password) {
            $processBuilder->add('-p' . $this->password);
        } else {
            $processBuilder->add('-p '); //todo
        }

        return $processBuilder;
    }

    /**
     * @param ProcessBuilder $processBuilder
     * @return ProcessBuilder
     */
    private function decorateCmdCompress(ProcessBuilder $processBuilder)
    {
        $processBuilder->add('-y');

        if ($this->isOsWin()) {  // not work for *nix
            $processBuilder->add('-sccUTF-8');
            $processBuilder->add('-scsUTF-8');
        }

        if (null !== $this->password) {
            $processBuilder->add('-p' . $this->password);
        }

        return $processBuilder;
    }

    /**
     * @param string $file
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function extractEntry($file)
    {
        $processBuilder = $this->getProcessBuilder()->setArguments([
            'x',
            $this->filename,
            $this->overwriteMode,
            '-o' . $this->outputDirectory,
        ]);
        $processBuilder = $this->decorateCmdExtract($processBuilder);
        $processBuilder->add($file);

        $this->execute($processBuilder);
    }

    /**
     * @param string $file
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     * @return string
     */
    public function getContent($file)
    {
        $processBuilder = $this->getProcessBuilder()->setArguments([
            'x',
            $this->filename,
            '-so',
            $file,
        ]);
        $processBuilder = $this->decorateCmdExtract($processBuilder);

        $process = $processBuilder->getProcess()->mustRun();

        return $process->getOutput();
    }

    /**
     * @param string $file
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     * @return Entry|null
     */
    public function getEntry($file)
    {
        //$file = \str_replace('\\', '/', $file);

        foreach ($this->getEntries() as $v) {
            if ($v->getPath() === $file) {
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
        $processBuilder = $this->getProcessBuilder()->setArguments([
            'l',
            $this->filename,
            '-slt',
        ]);
        $processBuilder = $this->decorateCmdExtract($processBuilder);

        $process = $this->execute($processBuilder);
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
        $processBuilder = $this->getProcessBuilder()->setArguments([
            'a',
            $this->filename,
            '-mx=' . (int)$this->compressionLevel,
            '-t7z',
        ]);
        $processBuilder = $this->decorateCmdCompress($processBuilder);

        if ($storePath) {
            $processBuilder->add('-spf');
            $processBuilder->add('-i!' . $file);
        } else {
            $realPath = \realpath($file);
            if (false === $realPath) {
                throw new Exception('Can not resolve absolute path for "' . $file . '"');
            }
            $processBuilder->add($realPath);
        }

        if (!$includeSubFiles && true === \is_dir($file)) {
            $processBuilder->add('-x!' . \rtrim($file, '/') . '/*');
        }

        $this->execute($processBuilder);
    }

    /**
     * @param string $file
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function delEntry($file)
    {
        $processBuilder = $this->getProcessBuilder()->setArguments([
            'd',
            $this->filename,
        ]);
        $processBuilder = $this->decorateCmdExtract($processBuilder);
        $processBuilder->add($file);

        $this->execute($processBuilder);
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
        $processBuilder = $this->getProcessBuilder()->setArguments([
            'rn',
            $this->filename,
        ]);
        $processBuilder = $this->decorateCmdExtract($processBuilder);
        $processBuilder->add($fileSrc);
        $processBuilder->add($fileDest);

        $this->execute($processBuilder);
    }


    /**
     * Is valid archive?
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     * @return bool
     */
    public function isValid()
    {
        $processBuilder = $this->getProcessBuilder()->setArguments([
            't',
            $this->filename,
        ]);
        $processBuilder = $this->decorateCmdExtract($processBuilder);

        $process = $this->execute($processBuilder);

        return false !== \strpos($process->getOutput(), 'Everything is Ok');
    }


    /**
     * @param ProcessBuilder $processBuilder
     *
     * @return Process
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    protected function execute(ProcessBuilder $processBuilder)
    {
        $process = $processBuilder->getProcess();

        if ($this->getChangeSystemLocale()) {
            if ($this->isOsWin()) {
                $localeProcessBuilder = new ProcessBuilder([
                    $this->systemLocaleWin,
                ]);
                $localeProcessBuilder->setPrefix('chcp');
            } else {
                $localeProcessBuilder = new ProcessBuilder([
                    'LANG=' . $this->systemLocaleNix,
                ]);
            }
            $process->setCommandLine($localeProcessBuilder->getProcess()->getCommandLine() . ' & ' . $process->getCommandLine());
        }

        return $process->mustRun();
    }
}
