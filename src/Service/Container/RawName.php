<?php

declare(strict_types=1);

namespace Archive7z\Service\Container;

/**
 * A raw (undecoded) entry name read from the container, with an explicit
 * hint whether the container already stores it as UTF-8.
 */
final readonly class RawName
{
    public function __construct(
        public string $value,
        public bool $utf8,
    ) {
    }
}
