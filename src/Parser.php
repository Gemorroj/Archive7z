<?php

declare(strict_types=1);

namespace Archive7z;

class Parser
{
    protected string $headTokenStart = '--';

    protected string $headTokenEnd = '';

    protected string $listTokenStart = '----------';

    protected string $newFileListToken = '';
    /**
     * raw cli output.
     *
     * @var string[]
     */
    protected array $data;

    /**
     * @param string[] $data raw cli output
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @return string[] raw cli output
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function parseInfo(): string
    {
        $data = '';

        foreach ($this->data as $value) {
            if ($value === $this->headTokenStart) {
                break;
            }

            $data .= $value."\n";
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function parseHeader(): array
    {
        $isMess = true;
        $list = [];

        foreach ($this->data as $value) {
            if ($value === $this->headTokenStart) {
                $isMess = false;
                continue;
            }

            if (true === $isMess) {
                continue;
            }

            if ($value === $this->headTokenEnd) {
                break;
            }

            $entry = $this->parseHeaderLine($value);
            if (!$entry) {
                continue;
            }

            $list[\key($entry)] = \current($entry);
        }

        return $list;
    }

    /**
     * @return array<int, array<string, string|string[]>>
     */
    public function parseEntries(?int $limit = null): array
    {
        $isHead = true;
        $list = [];
        $i = 0;

        foreach ($this->data as $value) {
            if ($value === $this->listTokenStart) {
                $isHead = false;
                continue;
            }

            if (true === $isHead) {
                continue;
            }

            if (null !== $limit && $i >= $limit) {
                break;
            }
            if ($value === $this->newFileListToken) {
                ++$i;
                continue;
            }

            $entry = $this->parseEntry($value);
            if (!$entry) {
                break; // ends of list
            }

            $list[$i][\key($entry)] = \current($entry);
            $list[$i][''][] = $value;
        }

        return $list;
    }

    /**
     * @return array<string, string>|null
     */
    protected function parseEntry(string $line): ?array
    {
        if (\str_starts_with($line, 'Warnings:') || \str_starts_with($line, 'Errors:')) {
            return null;
        }

        [$k, $v] = \explode(' =', $line, 2);

        return [$k => \ltrim($v)];
    }

    /**
     * @return array<string, string>|null
     */
    protected function parseHeaderLine(string $line): ?array
    {
        if (\str_starts_with($line, 'ERROR:') || \str_starts_with($line, 'Open WARNING:')) {
            return null;
        }

        /*
WARNINGS:
There are data after the end of archive
         */
        if (\str_starts_with($line, 'WARNINGS:') || !\str_contains($line, ' = ')) {
            return null;
        }

        [$k, $v] = \explode(' =', $line, 2);

        return [$k => \ltrim($v)];
    }
}
