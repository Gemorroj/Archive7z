<?php

declare(strict_types=1);

namespace Archive7z\Config;

use Archive7z\Exception\InvalidArgumentException;

/**
 * Configuration of the compression side of an {@see Options}.
 */
final readonly class CompressOptions
{
    /**
     * From 0 to 9.
     *
     * @see https://documentation.help/7-Zip/method.htm#ZipX
     * @see https://documentation.help/7-Zip/method.htm#BZip2X
     * @see https://documentation.help/7-Zip/method.htm#SevenZipX
     */
    private int $compressionLevel;

    /**
     * @see https://documentation.help/7-Zip/method.htm#Solid
     */
    private ?SolidMode $solidMode;

    /**
     * Encrypt archive header (7z archives only).
     *
     * @see https://documentation.help/7-Zip/method.htm#HeaderEncrypt
     */
    private bool $encryptFilenames;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(
        int $compressionLevel = 9,
        ?SolidMode $solidMode = null,
        bool $encryptFilenames = false,
    ) {
        if ($compressionLevel < 0 || $compressionLevel > 9) {
            throw new InvalidArgumentException('Compression level is not available');
        }

        $this->compressionLevel = $compressionLevel;
        $this->solidMode = $solidMode;
        $this->encryptFilenames = $encryptFilenames;
    }

    public function getCompressionLevel(): int
    {
        return $this->compressionLevel;
    }

    public function getSolidMode(): ?SolidMode
    {
        return $this->solidMode;
    }

    public function isEncryptFilenames(): bool
    {
        return $this->encryptFilenames;
    }
}
