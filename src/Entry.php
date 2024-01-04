<?php

declare(strict_types=1);

namespace Archive7z;

/*
p7zip: 7z
Path = test\test.txt
Size = 14
Packed Size =
Modified = 2013-10-23 16:28:51
Attributes = A -rw-r--r--
CRC = A346C3A7
Encrypted = -
Method = LZMA2:192k
Block = 0

p7zip: tar
Path = test\test.txt
Folder = -
Size = 14
Packed Size = 512
Modified = 2013-10-23 16:28:51
Mode = -rwxrwxrwx
User =
Group =
Symbolic Link =
Hard Link =
Characteristics = ASCII

p7zip: zip
Path = test\test.txt
Folder = -
Size = 14
Packed Size = 14
Modified = 2013-10-23 16:28:51
Created = 2018-10-14 15:57:19
Accessed = 2018-10-14 15:58:59
Attributes =  -rw-r--r--
Encrypted = -
Comment =
CRC = A346C3A7
Method = Store
Characteristics = NTFS
Host OS = Unix
Version = 10
Volume Index = 0
Offset = 146577

p7zip: iso
Path = folder/subdoc
Folder = -
Size = 6
Packed Size = 6
Modified = 2018-06-17 19:22:27

p7zip: xz
Size = 3
Packed Size = 60
Method = LZMA2:23 CRC64
 */
class Entry
{
    /**
     * Raw data.
     *
     * @var string[]
     */
    private array $data;
    private string $path;

    private string $size;

    private string $packedSize;

    private ?string $modified = null;

    private ?string $created = null;

    private ?string $attributes = null;

    private ?string $crc = null;

    private ?string $encrypted = null;

    private ?string $method = null;

    private ?string $block = null;

    private ?string $comment = null;
    /**
     * Unix|Win32|FAT|NTFS|Windows|Mac OS.
     */
    private ?string $hostOs = null;

    private ?string $characteristics = null;

    private ?string $folder = null;

    private Archive7z $archive;

    /**
     * @param array<string, string|string[]> $data
     */
    public function __construct(Archive7z $archive, array $data)
    {
        $this->archive = $archive;
        $this->parseData($data);
    }

    /**
     * @param array<string, string|string[]> $data
     */
    private function parseData(array $data): void
    {
        foreach ($data as $key => $value) {
            switch ($key) {
                case '':
                    $this->data = $value;
                    break;

                case 'Path':
                    $this->path = $value;
                    break;

                case 'Folder':
                    $this->folder = $value;
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

                case 'Created':
                    $this->created = $value;
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

                case 'Characteristics':
                    $this->characteristics = $value;
                    break;
            }
        }
    }

    /**
     * @return string[] raw data
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function isDirectory(): bool
    {
        return '+' === $this->folder || \str_contains((string) $this->attributes, 'D');
    }

    public function isEncrypted(): bool
    {
        return '+' === $this->encrypted;
    }

    /**
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
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

    public function getAttributes(): ?string
    {
        return $this->attributes;
    }

    public function getBlock(): ?string
    {
        return $this->block;
    }

    public function getCrc(): ?string
    {
        return $this->crc;
    }

    /**
     * @deprecated use isEncrypted instead
     */
    public function getEncrypted(): ?string
    {
        return $this->encrypted;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function getModified(): ?string
    {
        return $this->modified;
    }

    public function getCreated(): ?string
    {
        return $this->created;
    }

    /**
     * return size only for first file of solid block.
     *
     * @see https://github.com/Gemorroj/Archive7z/issues/5
     */
    public function getPackedSize(): string
    {
        return $this->packedSize;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getUnixPath(): string
    {
        return \str_replace('\\', '/', $this->getPath());
    }

    public function getSize(): string
    {
        return $this->size;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getHostOs(): ?string
    {
        return $this->hostOs;
    }

    public function getCharacteristics(): ?string
    {
        return $this->characteristics;
    }
}
