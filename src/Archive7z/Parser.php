<?php
/**
 *
 * This software is distributed under the GNU GPL v3.0 license.
 *
 * @author    Gemorroj
 * @copyright 2013 http://wapinet.ru
 * @license   http://www.gnu.org/licenses/gpl-3.0.txt
 * @link      https://github.com/Gemorroj/Archive7z
 * @version   0.2 alpha
 *
 */

namespace Archive7z;

class Parser
{
    protected $headToken = '----------';
    protected $listToken = '';

    protected $data;

    /**
     * @param array $data cli output
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
        $list = array();
        $i = 0;

        foreach ($this->data as $value) {
            if ($value === $this->headToken) {
                $head = false;
                continue;
            }

            if ($head === true) {
                continue;
            }

            if ($value === $this->listToken) {
                $i++;
                continue;
            }

            $entry = $this->parseEntry($value);
            $list[$i][key($entry)] = current($entry);
        }

        return $list;
    }

    /**
     * @param string $line
     * @return array
     */
    protected function parseEntry($line)
    {
        list($k, $v) = explode(' =', $line, 2);
        $v = ltrim($v);

        return array($k => $v);
    }
}
