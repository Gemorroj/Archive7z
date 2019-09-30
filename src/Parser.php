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
            if (!is_null($entry)) {
                $list[$i][\key($entry)] = \current($entry);
            }
        }

        return $list;
    }

    /**
     * @param string $line
     * @return string[]|null
     */
    protected function parseEntry($line)
    {
        $keyValue = \explode(' =', $line, 2);
        if (sizeof($keyValue) < 2) {
            return null;
        } else {
            list($k, $v) = $keyValue;
            $v = \ltrim($v);
    
            return [$k => $v];
        }
    }
}
