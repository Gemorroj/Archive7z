<?php

declare(strict_types=1);

namespace Archive7z\Service\Parser;

final class Parser
{
    private const HEAD_TOKEN_START = '--';
    private const HEAD_TOKEN_END = '';
    private const LIST_TOKEN_START = '----------';
    private const NEW_FILE_LIST_TOKEN = '';

    /**
     * raw cli output.
     *
     * @var string[]
     */
    private array $data;

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
            if (self::HEAD_TOKEN_START === $value) {
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
            if (self::HEAD_TOKEN_START === $value) {
                $isMess = false;
                continue;
            }

            if (true === $isMess) {
                continue;
            }

            if (self::HEAD_TOKEN_END === $value) {
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
        $raw = [];
        $i = 0;

        foreach ($this->data as $value) {
            if (self::LIST_TOKEN_START === $value) {
                $isHead = false;
                continue;
            }

            if (true === $isHead) {
                continue;
            }

            if (null !== $limit && $i >= $limit) {
                break;
            }
            if (self::NEW_FILE_LIST_TOKEN === $value) {
                ++$i;
                continue;
            }

            $entry = $this->parseEntry($value);
            if (!$entry) {
                break; // ends of list
            }

            $list[$i][\key($entry)] = \current($entry);
            $raw[$i][] = $value;
        }

        foreach ($raw as $index => $lines) {
            $list[$index][''] = $lines;
        }

        return $list;
    }

    /**
     * @return array<string, string>|null
     */
    private function parseEntry(string $line): ?array
    {
        if (!\str_contains($line, ' =')) {
            return null;
        }

        [$k, $v] = \explode(' =', $line, 2);

        return [$k => \ltrim($v)];
    }

    /**
     * @return array<string, string>|null
     */
    private function parseHeaderLine(string $line): ?array
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
