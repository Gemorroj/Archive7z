<?php

declare(strict_types=1);

namespace Archive7z\Service\Container;

use Archive7z\Exception\RuntimeException;

interface NameReaderInterface
{
    /**
     * Reads the raw entry names (in archive order).
     *
     * @throws RuntimeException
     *
     * @return RawName[]
     */
    public function read(string $filename): array;
}
