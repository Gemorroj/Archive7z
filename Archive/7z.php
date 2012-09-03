<?php
/**
 *
 * This software is distributed under the GNU GPL v3.0 license.
 * @author Gemorroj
 * @copyright 2012 http://wapinet.ru
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @link http://wapinet.ru/gmanager/
 * @version 0.1 alpha
 *
 */

require_once 'Archive/7z/Exception.php';
require_once 'Archive/7z/Entry.php';

class Archive_7z
{
    /**
     * @const string
     */
    const CMD_PATH_NIX = '/usr/local/bin/7z';
    const CMD_PATH_WIN = 'C:\Program Files\7-Zip\7z.exe';
    //const CMD_PATH_WIN = '%ProgramFiles%\7-Zip\7z.exe';

    /**
     * @const string
     */
    const OVERWRITE_MODE_A = '-aoa'; // Overwrite All existing files
    const OVERWRITE_MODE_S = '-aos'; // Skip extracting of existing files
    const OVERWRITE_MODE_U = '-aou'; // aUto rename extracting file (for example, name.txt will be renamed to name_1.txt)
    const OVERWRITE_MODE_T = '-aot'; // auto rename existing file (for example, name.txt will be renamed to name_1.txt)

    /**
     * @var string
     */
    private $_cmdPath;

    /**
     * @var string
     */
    private $_filename;

    /**
     * @var string
     */
    private $_password;

    /**
     * @var string
     */
    private $_outputDir = './';

    /**
     * @var string
     */
    private $_overwriteMode = self::OVERWRITE_MODE_A;


    private $_headToken = '----------';
    private $_listToken = '';


    /**
     * @param string $filename 7z archive filename
     * @throws Archive_7z_Exception
     */
    public function __construct($filename)
    {
        $this->setFilename($filename)->setCmdPath(substr(PHP_OS, 0, 3) === 'WIN' ? self::CMD_PATH_WIN : self::CMD_PATH_NIX);
    }


    /**
     * @param string $path
     * @throws Archive_7z_Exception
     * @return Archive_7z
     */
    public function setCmdPath($path)
    {
        $this->_cmdPath = str_replace('\\', '/', realpath($path));

        if (is_executable($this->_cmdPath) === false) {
            throw new Archive_7z_Exception('Cmd path is not available');
        }

        return $this;
    }


    /**
     * @param string $filename
     * @throws Archive_7z_Exception
     * @return Archive_7z
     */
    public function setFilename($filename)
    {
        $this->_filename = realpath($filename);

        if (is_readable($this->_filename) === false) {
            throw new Archive_7z_Exception('Filename is not available');
        }

        return $this;
    }


    /**
     * @param string $dir
     * @throws Archive_7z_Exception
     * @return Archive_7z
     */
    public function setOutputDir($dir = './')
    {
        $this->_outputDir = realpath($dir);

        if (is_writable($this->_outputDir) === false) {
            throw new Archive_7z_Exception('Output directory is not available');
        }

        return $this;
    }


    /**
     * @param string $password
     * @throws Archive_7z_Exception
     * @return Archive_7z
     */
    public function setPassword($password)
    {
        $this->_password = $password;

        return $this;
    }


    /**
     * @param string $mode
     * @throws Archive_7z_Exception
     * @return Archive_7z
     */
    public function setOverwriteMode($mode = Archive_7z::OVERWRITE_MODE_A)
    {
        $this->_overwriteMode = $mode;

        if (in_array($this->_overwriteMode, array(
            self::OVERWRITE_MODE_A,
            self::OVERWRITE_MODE_S,
            self::OVERWRITE_MODE_T,
            self::OVERWRITE_MODE_U
        )) === false
        ) {
            throw new Archive_7z_Exception('Overwrite mode is not available');
        }

        return $this;
    }


    /**
     * @return string
     */
    private function _getCmdPrefix()
    {
        $cmd = '"' . escapeshellcmd($this->_cmdPath) . '"'; // fix for windows
        if ($this->_password !== null) {
            $cmd .= ' -p' . escapeshellarg($this->_password);
        }
        return $cmd;
    }


    /**
     * @throws Archive_7z_Exception
     */
    public function extract()
    {
        $cmd = $this->_getCmdPrefix() . ' ' . escapeshellcmd($this->_overwriteMode) . ' -o' . escapeshellarg($this->_outputDir) . ' x ' . escapeshellarg($this->_filename);

        system($cmd, $rv);

        if ($rv !== 0) {
            throw new Archive_7z_Exception('Error! Exit code: ' . $rv);
        }
    }


    /**
     * @param string $file
     * @throws Archive_7z_Exception
     * @return string
     */
    public function getContent($file)
    {
        $cmd = $this->_getCmdPrefix() . ' -so x ' . escapeshellarg($this->_filename) . ' ' . escapeshellarg($file);

        $out = shell_exec($cmd);

        if ($out === null) {
            throw new Archive_7z_Exception('Error!');
        }

        return $out;
    }


    /**
     * @throws Archive_7z_Exception
     * @return Archive_7z_Entry[]
     */
    public function getEntries()
    {
        $cmd = $this->_getCmdPrefix() . ' -slt l ' . escapeshellarg($this->_filename);

        exec($cmd, $out, $rv);

        if ($rv !== 0) {
            throw new Archive_7z_Exception('Error! Exit code: ' . $rv);
        }

        $list = array();
        foreach ($this->_parseEntries($out) as $v) {
            $list[] = new Archive_7z_Entry($this, $v);
        }

        return $list;
    }


    /**
     * @param array $output
     * @return array
     */
    private function _parseEntries(array $output)
    {
        $head = true;
        $list = array();
        $i = 0;

        foreach ($output as $value) {
            if ($value === $this->_headToken) {
                $head = false;
                continue;
            }

            if ($head === true) {
                continue;
            }

            if ($value === $this->_listToken) {
                $i++;
                continue;
            }

            $list[$i][] = $value;
        }

        return $list;
    }
}
