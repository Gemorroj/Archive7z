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
    private function setData(string $key, string $value): void
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
    public function isDirectory(): bool
    {
        return '+' === $this->folder || false !== \strpos($this->attributes, 'D');
    }


    /**
     * @return bool
     */
    public function isEncrypted(): bool
    {
        return '+' === $this->encrypted;
    }


    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     * @return string
     */
    public function getContent(): string
    {
        return $this->archive->getContent($this->path);
    }


    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function extract(): void
    {
        $this->archive->extractEntry($this->path);
    }


    /**
     * @param string $directory
     *
     * @throws Exception
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function extractTo(string $directory): void
    {
        $oldDirectory = $this->archive->getOutputDirectory();
        $this->archive->setOutputDirectory($directory);
        try {
            $this->archive->extractEntry($this->path);
        } finally {
            $this->archive->setOutputDirectory($oldDirectory);
        }
    }


    /**
     * @return string
     */
    public function getAttributes(): string
    {
        return $this->attributes;
    }

    /**
     * @return string|null
     */
    public function getBlock(): ?string
    {
        return $this->block;
    }

    /**
     * @return string
     */
    public function getCrc(): string
    {
        return $this->crc;
    }

    /**
     * @return string
     */
    public function getEncrypted(): string
    {
        return $this->encrypted;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getModified(): string
    {
        return $this->modified;
    }

    /**
     * return size only for first file of solid block
     * @see https://github.com/Gemorroj/Archive7z/issues/5
     *
     * @return string
     */
    public function getPackedSize(): string
    {
        return $this->packedSize;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getUnixPath(): string
    {
        return \str_replace('\\', '/', $this->getPath());
    }

    /**
     * @return string
     */
    public function getSize(): string
    {
        return $this->size;
    }

    /**
     * @return string|null
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * @return string|null
     */
    public function getHostOs(): ?string
    {
        return $this->hostOs;
    }
}
