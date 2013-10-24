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
    private $path;
    /**
     * @var string
     */
    private $size;
    /**
     * @var string
     */
    private $packedSize;
    /**
     * @var string
     */
    private $modified;
    /**
     * @var string
     */
    private $attributes;
    /**
     * @var string
     */
    private $crc;
    /**
     * @var string
     */
    private $encrypted;
    /**
     * @var string
     */
    private $method;
    /**
     * @var string
     */
    private $block;

    /**
     * @var Archive_7z
     */
    private $archive;


    /**
     * @param Archive_7z $archive
     * @param array      $data
     */
    public function __construct(Archive_7z $archive, array $data)
    {
        $this->archive = $archive;
        $this->parseEntry($data);
    }


    /**
     * @param array $data
     */
    private function parseEntry(array $data)
    {
        foreach ($data as $line) {
            list($k, $v) = explode(' =', $line, 2);
            $v = ltrim($v);

            $this->setData($k, $v);
        }
    }


    /**
     * @param string $key
     * @param string $value
     */
    private function setData($key, $value)
    {
        switch ($key) {
        case 'Path':
            //$this->path = str_replace('\\', '/', $value);
            $this->path = $value;
            break;

        case 'Size':
            $this->size = $value;
            break;

        case 'Packed Size':
            $this->packedSize = $value;
            break;

        case 'Modified':
            $this->modified = $value;
            break;

        case 'Attributes':
            $this->attributes = $value;
            break;

        case 'CRC':
            $this->crc = $value;
            break;

        case 'Encrypted':
            $this->encrypted = $value;
            break;

        case 'Method':
            $this->method = $value;
            break;

        case 'Block':
            $this->block = $value;
            break;
        }
    }


    /**
     * @return bool
     */
    public function isDirectory()
    {
        return ($this->attributes[0] === 'D');
    }


    /**
     * @throws Archive_7z_Exception
     * @return string
     */
    public function getContent()
    {
        return $this->archive->getContent($this->path);
    }


    /**
     * @throws Archive_7z_Exception
     */
    public function extract()
    {
        $this->archive->extractEntry($this->path);
    }


    /**
     * @param string $directory
     *
     * @throws Archive_7z_Exception
     */
    public function extractTo($directory = './')
    {
        $oldDirectory = $this->archive->getOutputDirectory();
        $this->archive->setOutputDirectory($directory);
        $this->archive->extractEntry($this->path);
        $this->archive->setOutputDirectory($oldDirectory);
    }


    /**
     * @return string
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @return string
     */
    public function getBlock()
    {
        return $this->block;
    }

    /**
     * @return string
     */
    public function getCrc()
    {
        return $this->crc;
    }

    /**
     * @return string
     */
    public function getEncrypted()
    {
        return $this->encrypted;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getModified()
    {
        return $this->modified;
    }

    /**
     * @return string
     */
    public function getPackedSize()
    {
        return $this->packedSize;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getSize()
    {
        return $this->size;
    }
}
