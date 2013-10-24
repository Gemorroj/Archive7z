<?php
/**
 *
 * This software is distributed under the GNU GPL v3.0 license.
 *
 * @author    Gemorroj
 * @copyright 2012 http://wapinet.ru
 * @license   http://www.gnu.org/licenses/gpl-3.0.txt
 * @link      https://github.com/Gemorroj/Archive_7z
 * @version   0.1 alpha
 *
 */

require_once 'Archive/7z/Exception.php';
require_once 'Archive/7z/Entry.php';

class Archive_7z
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
    protected $cliLinux = '/usr/bin/7z';
    /**
     * @var string
     */
    protected $cliBsd = '/usr/local/bin/7z';
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
    private $headToken = '----------';
    private $listToken = '';

    /**
     * @param string $filename 7z archive filename
     * @param string $cli      CLI path
     *
     * @throws Archive_7z_Exception
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
    protected function getAutoCli()
    {
        $os = strtoupper(php_uname('s'));

        if (strpos($os, 'BSD') !== false) {
            return $this->cliBsd;
        } elseif (strpos($os, 'WIN') !== false) {
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
     * @throws Archive_7z_Exception
     * @return Archive_7z
     */
    public function setCli($path)
    {
        $this->cli = realpath($path);

        if (is_executable($this->cli) === false) {
            throw new Archive_7z_Exception('Cli is not available');
        }

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
     * @throws Archive_7z_Exception
     * @return Archive_7z
     */
    public function setFilename($filename)
    {
        //$this->filename = realpath($filename);
        $this->filename = $filename;

        //if (is_readable($this->filename) === false) {
        //    throw new Archive_7z_Exception('Filename is not available');
        //}

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
     * @throws Archive_7z_Exception
     * @return Archive_7z
     */
    public function setOutputDirectory($directory = './')
    {
        $this->outputDirectory = realpath($directory);

        if (is_writable($this->outputDirectory) === false) {
            throw new Archive_7z_Exception('Output directory is not available');
        }

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
     * @throws Archive_7z_Exception
     * @return Archive_7z
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
     * @throws Archive_7z_Exception
     * @return Archive_7z
     */
    public function setOverwriteMode($mode = Archive_7z::OVERWRITE_MODE_A)
    {
        $this->overwriteMode = $mode;

        if (in_array(
                $this->overwriteMode, array(
                    self::OVERWRITE_MODE_A,
                    self::OVERWRITE_MODE_S,
                    self::OVERWRITE_MODE_T,
                    self::OVERWRITE_MODE_U
                )
            ) === false
        ) {
            throw new Archive_7z_Exception('Overwrite mode is not available');
        }

        return $this;
    }

    /**
     * @throws Archive_7z_Exception
     */
    public function extract()
    {
        $cmd = $this->getCmdPrefix() . ' x ' . escapeshellarg($this->filename) . ' ' . escapeshellcmd(
                $this->overwriteMode
            ) . ' -o' . escapeshellarg($this->outputDirectory) . ' ' . $this->getCmdPostfixExtract();

        exec($cmd, $out, $rv);

        if ($rv !== 0) {
            throw new Archive_7z_Exception(end($out), $rv);
        }
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
        //$cmd .= ' -scc"UTF-8"'; // not work for linux
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
        //$cmd .= ' -scc"UTF-8"'; // not work for linux
        if ($this->password !== null) {
            $cmd .= ' -p' . escapeshellarg($this->password);
        }

        return $cmd;
    }

    /**
     * @param string $file
     *
     * @throws Archive_7z_Exception
     */
    public function extractEntry($file)
    {
        $cmd = $this->getCmdPrefix() . ' x ' . escapeshellarg($this->filename) . ' ' . escapeshellcmd(
                $this->overwriteMode
            ) . ' -o' . escapeshellarg($this->outputDirectory) . ' ' . $this->getCmdPostfixExtract() . ' ' . escapeshellarg(
                $file
            );

        exec($cmd, $out, $rv);

        if ($rv !== 0) {
            throw new Archive_7z_Exception(end($out), $rv);
        }
    }

    /**
     * @param string $file
     *
     * @throws Archive_7z_Exception
     * @return string
     */
    public function getContent($file)
    {
        $cmd = $this->getCmdPrefix() . ' x ' . escapeshellarg($this->filename) . ' -so ' . escapeshellarg($file) . ' '
            . $this->getCmdPostfixExtract();

        // в exec теряются переводы строк
        $result = shell_exec($cmd);

        if ($result === null) {
            throw new Archive_7z_Exception('Error');
        }

        return $result;
    }

    /**
     * @param string $file
     * @throws Archive_7z_Exception
     * @return Archive_7z_Entry|null
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
     * @throws Archive_7z_Exception
     * @return Archive_7z_Entry[]
     */
    public function getEntries()
    {
        $cmd = $this->getCmdPrefix() . ' l ' . escapeshellarg($this->filename) . ' -slt ' . $this->getCmdPostfixExtract();

        exec($cmd, $out, $rv);

        if ($rv !== 0) {
            throw new Archive_7z_Exception(end($out), $rv);
        }

        $list = array();
        foreach ($this->parseEntries($out) as $v) {
            $list[] = new Archive_7z_Entry($this, $v);
        }

        return $list;
    }

    /**
     * @param array $output
     *
     * @return array
     */
    private function parseEntries(array $output)
    {
        $head = true;
        $list = array();
        $i = 0;

        foreach ($output as $value) {
            if ($value === $this->headToken) {
                $head = false;
                continue;
            }

            if ($head === true) {
                continue;
            }

            if ($value === $this->listToken) {
                $i++;
                continue;
            }

            $list[$i][] = $value;
        }

        return $list;
    }

    /**
     * @todo custom format (-t7z, -tzip, -tgzip, -tbzip2 or -ttar)
     *
     * @param string $file
     * @param bool $includeSubFiles
     * @param bool $storePath
     *
     * @throws Archive_7z_Exception
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

        $cmd = $this->getCmdPrefix() . ' a ' . escapeshellarg($this->filename) . ' -mx=' . intval($this->compressionLevel) . ' -t7z ' . $this->getCmdPostfixCompress() . ' '
            . $path . ' ' . $exclude;

        exec($cmd, $out, $rv);

        if ($rv !== 0) {
            throw new Archive_7z_Exception(end($out), $rv);
        }
    }

    /**
     * @param string $file
     *
     * @throws Archive_7z_Exception
     */
    public function delEntry($file)
    {
        $cmd = $this->getCmdPrefix() . ' d ' . escapeshellarg($this->filename) . ' ' . $this->getCmdPostfixExtract() . ' '
            . escapeshellarg($file);

        exec($cmd, $out, $rv);

        if ($rv !== 0) {
            throw new Archive_7z_Exception(end($out), $rv);
        }
    }

    /**
     * @param string $fileSrc
     * @param string $fileDest
     *
     * @throws Archive_7z_Exception
     */
    public function renameEntry($fileSrc, $fileDest)
    {
        $cmd = $this->getCmdPrefix() . ' rn ' . escapeshellarg($this->filename) . ' ' . $this->getCmdPostfixExtract() . ' '
            . escapeshellarg($fileSrc) . ' ' . escapeshellarg($fileDest);

        exec($cmd, $out, $rv);

        if ($rv !== 0) {
            throw new Archive_7z_Exception(end($out), $rv);
        }
    }
}
