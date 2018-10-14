<?php

namespace Archive7z;

class Entry
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
     * @var string|null
     */
    private $block;
    /**
     * @var string|null
     */
    private $comment;
    /**
     * @var string|null
     * Unix|Win32|FAT
     */
    private $hostOs;
    /**
     * @var string|null
     */
    private $folder;
    /**
     * @var Archive7z
     */
    private $archive;


    /**
     * @param Archive7z $archive Archive7z object
     * @param array $data parsed entry data
     */
    public function __construct(Archive7z $archive, array $data)
    {
        $this->archive = $archive;
        foreach ($data as $k => $v) {
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

            case 'Encrypted':
                $this->encrypted = $value;
                break;

            case 'Comment':
                $this->comment = $value;
                break;

            case 'CRC':
                $this->crc = $value;
                break;

            case 'Method':
                $this->method = $value;
                break;

            case 'Block':
                $this->block = $value;
                break;

            case 'Host OS':
                $this->hostOs = $value;
                break;
        }
    }


    /**
     * @return bool
     */
    public function isDirectory()
    {
        return '+' === $this->folder || false !== \strpos($this->attributes, 'D');
    }


    /**
     * @return bool
     */
    public function isEncrypted()
    {
        return '+' === $this->encrypted;
    }


    /**
     * @throws Exception
     * @return string
     */
    public function getContent()
    {
        return $this->archive->getContent($this->path);
    }


    /**
     * @throws Exception
     */
    public function extract()
    {
        $this->archive->extractEntry($this->path);
    }


    /**
     * @param string $directory
     *
     * @throws Exception
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
     * @return string|null
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
     * return size only for first file of solid block
     * @see https://github.com/Gemorroj/Archive7z/issues/5
     *
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
    public function getUnixPath()
    {
       return \str_replace('\\', '/', $this->getPath());
    }

    /**
     * @return string
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @return string|null
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * @return string|null
     */
    public function getHostOs()
    {
        return $this->hostOs;
    }
}
