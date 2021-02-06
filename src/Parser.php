<?php

namespace Archive7z;

class Parser
{
    protected $headToken = '----------';
    protected $listToken = '';

    protected $data;

    /**
     * @param string[] $data cli output
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function parseEntries(): array
    {
        $head = true;
        $list = [];
        $i = 0;

        foreach ($this->data as $value) {
            if ($value === $this->headToken) {
                $head = false;
                continue;
            }

            if (true === $head) {
                continue;
            }

            if ($value === $this->listToken) {
                ++$i;
                continue;
            }

            $entry = $this->parseEntry($value);
            $list[$i][\key($entry)] = \current($entry);
        }

        return $list;
    }

    /**
     * @return string[]
     */
    protected function parseEntry(string $line): array
    {
        if (0 === \strpos($line, 'Warnings:') || 0 === \strpos($line, 'Errors:')) {
            [$k, $v] = \explode(': ', $line, 2);

            return [$k => (int) $v];
        }

        [$k, $v] = \explode(' =', $line, 2);

        return [$k => \ltrim($v)];
    }
}
