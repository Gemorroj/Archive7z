<?php

namespace Archive7z;

class Parser
{
    protected string $headTokenStart = '--';

    protected string $headTokenEnd = '';

    protected string $listTokenStart = '----------';

    protected string $newFileListToken = '';
    /**
     * @var string[]
     */
    protected array $data;

    /**
     * @param string[] $data cli output
     */
    public function __construct(array $data)
    {
        $this->data = $data;
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
     * @return array<int, array<string, string>>
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
        }

        return $list;
    }

    /**
     * @return array<string, string>|null
     */
    protected function parseEntry(string $line): ?array
    {
        if (0 === \strpos($line, 'Warnings:') || 0 === \strpos($line, 'Errors:')) {
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
        if (0 === \strpos($line, 'ERROR:') || 0 === \strpos($line, 'Open WARNING:')) {
            return null;
        }

        /*
WARNINGS:
There are data after the end of archive
         */
        if (0 === \strpos($line, 'WARNINGS:') || false === \strpos($line, ' = ')) {
            return null;
        }

        [$k, $v] = \explode(' =', $line, 2);

        return [$k => \ltrim($v)];
    }
}
