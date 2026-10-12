<?php

declare(strict_types=1);

namespace Archive7z\Service\Process;

use Archive7z\Exception\RuntimeException;

/**
 * A temporary 7-Zip list file holding an entry name/mask in the archive code page.
 *
 * 7-Zip reads it with an explicit -scs code page, so 8-bit names survive the
 * command line regardless of the platform ANSI/locale encoding.
 */
final class MaskListFile
{
    private readonly string $path;

    public function __construct(string $content)
    {
        $path = \tempnam(\sys_get_temp_dir(), 'archive7z_');
        if (false === $path || false === \file_put_contents($path, $content."\n")) {
            throw new RuntimeException('Cannot create a temporary list file for the entry name');
        }

        $this->path = $path;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function delete(): void
    {
        if (\is_file($this->path)) {
            @\unlink($this->path);
        }
    }

    public function __destruct()
    {
        $this->delete();
    }
}
