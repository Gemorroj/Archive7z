<?php

namespace Archive7z;

class Parser
{
    /**
     * @var string
     */
    protected $headToken = '----------';
    /**
     * @var string
     */
    protected $listToken = '';
    /**
     * @var string[]
     */
    protected $data;

    /**
     * @param string[] $data cli output
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function parseEntries(): array
    {
        $isHead = true;
        $list = [];
        $i = 0;

        foreach ($this->data as $value) {
            if ($value === $this->headToken) {
                $isHead = false;
                continue;
            }

            if (true === $isHead) {
                continue;
            }

            if ($value === $this->listToken) {
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
}
