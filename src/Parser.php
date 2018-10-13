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

    /**
     * @return array
     */
    public function parseEntries()
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
                $i++;
                continue;
            }

            $entry = $this->parseEntry($value);
            $list[$i][\key($entry)] = \current($entry);
        }

        return $list;
    }

    /**
     * @param string $line
     * @return string[]
     */
    protected function parseEntry($line)
    {
        list($k, $v) = \explode(' =', $line, 2);
        $v = \ltrim($v);

        return [$k => $v];
    }
}
