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

class Archive_7z_Entry
{
    /**
     * @var string
     */
    private $_path;
    /**
     * @var string
     */
    private $_size;
    /**
     * @var string
     */
    private $_packedSize;
    /**
     * @var string
     */
    private $_modified;
    /**
     * @var string
     */
    private $_attributes;
    /**
     * @var string
     */
    private $_crc;
    /**
     * @var string
     */
    private $_encrypted;
    /**
     * @var string
     */
    private $_method;
    /**
     * @var string
     */
    private $_block;

    /**
     * @var Archive_7z
     */
    private $_archive;


    /**
     * @param Archive_7z $archive
     * @param array      $data
     */
    public function __construct(Archive_7z $archive, array $data)
    {
        $this->_archive = $archive;
        $this->_parseEntry($data);
    }


    /**
     * @param array $data
     */
    private function _parseEntry(array $data)
    {
        foreach ($data as $line) {
            list($k, $v) = explode(' =', $line, 2);
            $v = ltrim($v);

            $this->_setData($k, $v);
        }
    }


    /**
     * @param string $key
     * @param string $value
     */
    private function _setData($key, $value)
    {
        switch ($key) {
        case 'Path':
            $this->_path = $value;
            break;

        case 'Size':
            $this->_size = $value;
            break;

        case 'Packed Size':
            $this->_packedSize = $value;
            break;

        case 'Modified':
            $this->_modified = $value;
            break;

        case 'Attributes':
            $this->_attributes = $value;
            break;

        case 'CRC':
            $this->_crc = $value;
            break;

        case 'Encrypted':
            $this->_encrypted = $value;
            break;

        case 'Method':
            $this->_method = $value;
            break;

        case 'Block':
            $this->_block = $value;
            break;
        }
    }


    /**
     * @return bool
     */
    public function isDirectory()
    {
        return ($this->_attributes[0] === 'D');
    }


    /**
     * @throws Archive_7z_Exception
     * @return string
     */
    public function getContent()
    {
        return $this->_archive->getContent($this->_path);
    }


    /**
     * @throws Archive_7z_Exception
     */
    public function extract()
    {
        $this->_archive->extractEntry($this->_path);
    }


    /**
     * @param string $directory
     *
     * @throws Archive_7z_Exception
     */
    public function extractTo($directory = './')
    {
        $oldDirectory = $this->_archive->getOutputDirectory();
        $this->_archive->setOutputDirectory($directory);
        $this->_archive->extractEntry($this->_path);
        $this->_archive->setOutputDirectory($oldDirectory);
    }


    /**
     * @return string
     */
    public function getAttributes()
    {
        return $this->_attributes;
    }

    /**
     * @return string
     */
    public function getBlock()
    {
        return $this->_block;
    }

    /**
     * @return string
     */
    public function getCrc()
    {
        return $this->_crc;
    }

    /**
     * @return string
     */
    public function getEncrypted()
    {
        return $this->_encrypted;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->_method;
    }

    /**
     * @return string
     */
    public function getModified()
    {
        return $this->_modified;
    }

    /**
     * @return string
     */
    public function getPackedSize()
    {
        return $this->_packedSize;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->_path;
    }

    /**
     * @return string
     */
    public function getSize()
    {
        return $this->_size;
    }
}
