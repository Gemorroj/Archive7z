<?php

declare(strict_types=1);

namespace Archive7z\Service\Container;

final class NameReaderResolver
{
    /**
     * @return NameReaderInterface[]
     */
    private function readers(): array
    {
        return [new ZipNameReader(), new TarNameReader(), new GzipNameReader()];
    }

    /**
     * Detects the container format by asking each reader in turn.
     */
    public function resolve(string $filename): ?NameReaderInterface
    {
        foreach ($this->readers() as $reader) {
            if ($reader->matches($filename)) {
                return $reader;
            }
        }

        return null;
    }
}
