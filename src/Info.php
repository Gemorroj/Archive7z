<?php

declare(strict_types=1);

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

Path = test.tgz
Type = gzip
Headers Size = 19
 */
class Info
{
    /**
     * Raw data.
     *
     * @var string[]
     */
    private array $data;
    private string $path;

    private string $type;

    private int $physicalSize;

    private ?int $headersSize = null;

    private ?string $method = null;

    private ?string $solid = null;

    private ?int $blocks = null;

    private ?string $codePage = null;

    public function __construct(Parser $parser)
    {
        $this->data = $parser->getData();
        $headerData = $parser->parseHeader();
        $this->parseHeader($headerData);

        if (!isset($this->physicalSize)) {
            $info = $parser->parseInfo();
            \preg_match('/\d+ file, (\d+) bytes/', $info, $match);
            $this->physicalSize = (int) $match[1];
        }
    }

    /**
     * @param string[] $headerData
     */
    private function parseHeader(array $headerData): void
    {
        foreach ($headerData as $key => $value) {
            switch ($key) {
                case 'Path':
                    $this->path = $value;
                    break;

                case 'Type':
                    $this->type = $value;
                    break;

                case 'Physical Size':
                    $this->physicalSize = (int) $value;
                    break;

                case 'Headers Size':
                    $this->headersSize = (int) $value;
                    break;

                case 'Method':
                    $this->method = $value;
                    break;

                case 'Solid':
                    $this->solid = $value;
                    break;

                case 'Blocks':
                    $this->blocks = (int) $value;
                    break;

                case 'Code Page':
                    $this->codePage = $value;
                    break;
            }
        }
    }

    /**
     * @return string[]
     */
    public function getData(): array
    {
        return $this->data;
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
