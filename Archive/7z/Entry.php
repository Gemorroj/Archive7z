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

class Archive_7z_Entry
{
    private $_path;
    private $_size;
    private $_packedSize;
    private $_modified;
    private $_attributes;
    private $_crc;
    private $_encrypted;
    private $_method;
    private $_block;


    /**
     * @param array $data
     */
    public function __construct(array $data)
    {
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


    public function getAttributes()
    {
        return $this->_attributes;
    }

    public function getBlock()
    {
        return $this->_block;
    }

    public function getCrc()
    {
        return $this->_crc;
    }

    public function getEncrypted()
    {
        return $this->_encrypted;
    }

    public function getMethod()
    {
        return $this->_method;
    }

    public function getModified()
    {
        return $this->_modified;
    }

    public function getPackedSize()
    {
        return $this->_packedSize;
    }

    public function getPath()
    {
        return $this->_path;
    }

    public function getSize()
    {
        return $this->_size;
    }
}
