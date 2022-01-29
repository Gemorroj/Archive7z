<?php

namespace Archive7z;

/*
Path = test.7z
Type = 7z
Physical Size = 165348
Headers Size = 246
Method = LZMA2:192k
Solid = +
Blocks = 1

Path = test.tar
Type = tar
Physical Size = 174592
Headers Size = 3584
Code Page = UTF-8
Characteristics = UTF8 MaxUnicode=1095

Path = test.zip
Type = zip
Physical Size = 165038
 */
class Info
{
    /**
     * @var string
     */
    private $path;
    /**
     * @var string
     */
    private $type;
    /**
     * @var int
     */
    private $physicalSize;
    /**
     * @var int|null
     */
    private $headersSize;
    /**
     * @var string|null
     */
    private $method;
    /**
     * @var string|null
     */
    private $solid;
    /**
     * @var int|null
     */
    private $blocks;
    /**
     * @var string|null
     */
    private $codePage;

    /**
     * @param array<string, string> $data parsed data
     */
    public function __construct(array $data)
    {
        foreach ($data as $k => $v) {
            $this->setData($k, $v);
        }
    }

    private function setData(string $key, string $value): void
    {
        switch ($key) {
            case 'Path':
                $this->path = $value;
                break;

            case 'Type':
                $this->type = $value;
                break;

            case 'Physical Size':
                $this->physicalSize = $value;
                break;

            case 'Headers Size':
                $this->headersSize = $value;
                break;

            case 'Method':
                $this->method = $value;
                break;

            case 'Solid':
                $this->solid = $value;
                break;

            case 'Blocks':
                $this->blocks = $value;
                break;

            case 'Code Page':
                $this->codePage = $value;
                break;
        }
    }

    public function isSolid(): bool
    {
        return '+' === $this->solid;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPhysicalSize(): int
    {
        return $this->physicalSize;
    }

    public function getHeadersSize(): ?int
    {
        return $this->headersSize;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function getBlocks(): ?int
    {
        return $this->blocks;
    }

    public function getCodePage(): ?string
    {
        return $this->codePage;
    }
}
