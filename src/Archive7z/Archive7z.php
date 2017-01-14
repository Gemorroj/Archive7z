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
        if ($cli === null) {
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
        return stripos(PHP_OS, 'WIN') !== false;
    }


    /**
     * @return string
     */
    protected function isOsBsd()
    {
        return stripos(PHP_OS, 'BSD') !== false;
    }


    /**
     * @return string
     */
    protected function getAutoCli()
    {
        if ($this->isOsBsd()) {
            return $this->cliBsd;
        } elseif ($this->isOsWin()) {
            return $this->cliWindows;
        } else {
            return $this->cliLinux;
        }
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
        $cli = realpath($path);

        if ($cli === false) {
            throw new Exception('Cli is not available');
        }

        if (is_executable($cli) === false) {
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
        $filename = realpath($filename);

        if ($filename === false) {
            throw new Exception('Filename is not available');
        }

        if (is_readable($filename) === false) {
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
        $outputDirectory = realpath($directory);

        if ($outputDirectory === false) {
            throw new Exception('Output directory is not available');
        }

        if (is_writable($outputDirectory) === false) {
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
     * @throws Exception
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

        if (in_array(
                $this->overwriteMode,
                array(
                    self::OVERWRITE_MODE_A,
                    self::OVERWRITE_MODE_S,
                    self::OVERWRITE_MODE_T,
                    self::OVERWRITE_MODE_U
                )
            ) === false
        ) {
            throw new Exception('Overwrite mode is not available');
        }

        return $this;
    }

    /**
     * @throws Exception
     */
    public function extract()
    {
        $cmd = $this->getCmdPrefix() . ' x ' . escapeshellarg($this->filename) . ' ' . escapeshellcmd(
                $this->overwriteMode
            ) . ' -o' . escapeshellarg($this->outputDirectory) . ' ' . $this->getCmdPostfixExtract();

        $this->execute($cmd);
    }

    /**
     * @return string
     */
    private function getCmdPrefix()
    {
        return '"' . escapeshellcmd(str_replace('\\', '/', $this->cli)) . '"'; // fix for windows
    }

    /**
     * @return string
     */
    private function getCmdPostfixExtract()
    {
        $cmd = ' -y';
        if ($this->isOsWin()) { // not work for *nix
            $cmd .= ' -scc"UTF-8"';
            $cmd .= ' -scs"UTF-8"';
        }
        if ($this->password !== null) {
            $cmd .= ' -p' . escapeshellarg($this->password);
        } else {
            $cmd .= ' -p" "';
        }

        return $cmd;
    }

    /**
     * @return string
     */
    private function getCmdPostfixCompress()
    {
        $cmd = ' -y';
        if ($this->isOsWin()) {  // not work for *nix
            $cmd .= ' -scc"UTF-8"';
            $cmd .= ' -scs"UTF-8"';
        }
        if ($this->password !== null) {
            $cmd .= ' -p' . escapeshellarg($this->password);
        }

        return $cmd;
    }

    /**
     * @param string $file
     *
     * @throws Exception
     */
    public function extractEntry($file)
    {
        $cmd = $this->getCmdPrefix() . ' x ' . escapeshellarg($this->filename) . ' ' . escapeshellcmd(
                $this->overwriteMode
            ) . ' -o' . escapeshellarg($this->outputDirectory) . ' ' . $this->getCmdPostfixExtract(
            ) . ' ' . escapeshellarg(
                $file
            );

        $this->execute($cmd);
    }

    /**
     * @param string $file
     *
     * @throws Exception
     * @return string
     */
    public function getContent($file)
    {
        $cmd = $this->getCmdPrefix() . ' x ' . escapeshellarg($this->filename) . ' -so ' . escapeshellarg($file) . ' '
            . $this->getCmdPostfixExtract();

        $process = new Process($cmd);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new Exception($process->getErrorOutput());
        }

        return $process->getOutput();
    }

    /**
     * @param string $file
     * @throws Exception
     * @return Entry|null
     */
    public function getEntry($file)
    {
        //$file = str_replace('\\', '/', $file);

        foreach ($this->getEntries() as $v) {
            if ($v->getPath() == $file) {
                return $v;
            }
        }

        return null;
    }

    /**
     * @throws Exception
     * @return Entry[]
     */
    public function getEntries()
    {
        $cmd = $this->getCmdPrefix() . ' l ' . escapeshellarg($this->filename) . ' -slt ' . $this->getCmdPostfixExtract(
            );

        $out = $this->execute($cmd);

        $list = array();
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
     * @throws Exception
     */
    public function addEntry($file, $includeSubFiles = false, $storePath = false)
    {
        if ($storePath) {
            $path = '-spf -i!' . escapeshellarg($file);
        } else {
            $path = escapeshellarg(realpath($file));
        }

        $exclude = '';
        if (!$includeSubFiles && is_dir($file) === true) {
            $exclude = '-x!' . escapeshellarg(rtrim($file, '/') . '/*');
        }

        $cmd = $this->getCmdPrefix() . ' a ' . escapeshellarg($this->filename) . ' -mx=' . intval(
                $this->compressionLevel
            ) . ' -t7z ' . $this->getCmdPostfixCompress() . ' '
            . $path . ' ' . $exclude;

        $this->execute($cmd);
    }

    /**
     * @param string $file
     *
     * @throws Exception
     */
    public function delEntry($file)
    {
        $cmd = $this->getCmdPrefix() . ' d ' . escapeshellarg($this->filename) . ' ' . $this->getCmdPostfixExtract(
            ) . ' '
            . escapeshellarg($file);

        $this->execute($cmd);
    }

    /**
     * 7-zip >= 7.30 ( http://sourceforge.net/p/p7zip/discussion/383043/thread/f54fe89a/ )
     *
     * @param string $fileSrc
     * @param string $fileDest
     *
     * @throws Exception
     */
    public function renameEntry($fileSrc, $fileDest)
    {
        $cmd = $this->getCmdPrefix() . ' rn ' . escapeshellarg($this->filename) . ' ' . $this->getCmdPostfixExtract(
            ) . ' '
            . escapeshellarg($fileSrc) . ' ' . escapeshellarg($fileDest);

        $this->execute($cmd);
    }


    /**
     * Is valid archive?
     *
     * @throws Exception
     * @return bool
     */
    public function isValid()
    {
        $cmd = $this->getCmdPrefix() . ' t ' . escapeshellarg($this->filename) . ' ' . $this->getCmdPostfixExtract();

        $out = $this->execute($cmd);

        return in_array('Everything is Ok', $out, true);
    }


    /**
     * @param string $cmd
     *
     * @return array
     * @throws Exception
     */
    protected function execute($cmd)
    {
        if (!$this->getChangeSystemLocale()) {
            $out = $this->exec($cmd);
        } else {
            $out = $this->execLocale($cmd);
        }

        return $out;
    }


    /**
     * @param string $cmd
     * @return array
     * @throws Exception
     */
    protected function exec($cmd)
    {
        $process = new Process($cmd);
        $process->run();

        if (!$process->isSuccessful()) {
            $arrayErrorOutput = explode(PHP_EOL, $process->getErrorOutput());
            $error = $this->getCliError($arrayErrorOutput);
            throw new Exception($error);
        }

        return explode(PHP_EOL, $process->getOutput());
    }


    /**
     * @param string $cmd
     * @return array
     * @throws Exception
     */
    protected function execLocale($cmd)
    {
        if ($this->isOsWin()) {
            return $this->exec('chcp ' . escapeshellarg($this->systemLocaleWin) . ' & ' . $cmd);
        } else {
            return $this->exec('LANG=' . escapeshellarg($this->systemLocaleNix) . ' ' . $cmd);
        }
    }


    /**
     * Get cli error
     *
     * @param array $out
     *
     * @return string|null
     */
    protected function getCliError(array $out)
    {
        for ($i = count($out) - 1; $i >= 0; --$i) {
            if ($out[$i] !== '') {
                return $out[$i];
            }
        }

        return null;
    }
}
