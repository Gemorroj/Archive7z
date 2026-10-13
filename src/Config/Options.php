<?php

declare(strict_types=1);

namespace Archive7z\Config;

use Archive7z\Exception\InvalidArgumentException;

/**
 * Immutable configuration of an {@see \Archive7z\Archive7z} instance.
 */
final readonly class Options
{
    /**
     * Overwrite all existing files.
     *
     * 7-Zip maps the "-ao" post char "asut" to Overwrite/Skip/Rename/
     * RenameExisting (ArchiveCommandLine.cpp: kOverwritePostCharSet).
     *
     * @see https://documentation.help/7-Zip/overwrite.htm
     */
    public const OVERWRITE_MODE_A = '-aoa';
    /**
     * Skip extracting of existing files.
     *
     * @see https://documentation.help/7-Zip/overwrite.htm
     */
    public const OVERWRITE_MODE_S = '-aos';
    /**
     * Auto rename extracting file (for example, name.txt will be renamed to name_1.txt).
     *
     * @see https://documentation.help/7-Zip/overwrite.htm
     */
    public const OVERWRITE_MODE_U = '-aou';
    /**
     * Auto rename existing file (for example, name.txt will be renamed to name_1.txt).
     *
     * @see https://documentation.help/7-Zip/overwrite.htm
     */
    public const OVERWRITE_MODE_T = '-aot';

    private ?string $password;

    /**
     * From 0 to 9.
     *
     * @see https://documentation.help/7-Zip/method.htm#ZipX
     * @see https://documentation.help/7-Zip/method.htm#BZip2X
     * @see https://documentation.help/7-Zip/method.htm#SevenZipX
     */
    private int $compressionLevel;

    /**
     * @see https://documentation.help/7-Zip/overwrite.htm
     */
    private string $overwriteMode;

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

    private FallbackEncoding $fallbackEncoding;

    /**
     * Additional environment variables for the 7-Zip process.
     *
     * @var array<string, string>
     */
    private array $env;

    /**
     * @param array<string, string> $env additional environment variables for the 7-Zip process
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        ?string $password = null,
        int $compressionLevel = 9,
        string $overwriteMode = self::OVERWRITE_MODE_A,
        ?SolidMode $solidMode = null,
        bool $encryptFilenames = false,
        ?FallbackEncoding $fallbackEncoding = null,
        array $env = [],
    ) {
        if ($compressionLevel < 0 || $compressionLevel > 9) {
            throw new InvalidArgumentException('Compression level is not available');
        }

        if (!\in_array($overwriteMode, [
            self::OVERWRITE_MODE_A,
            self::OVERWRITE_MODE_S,
            self::OVERWRITE_MODE_T,
            self::OVERWRITE_MODE_U,
        ], true)) {
            throw new InvalidArgumentException('Overwrite mode is not available');
        }

        $this->password = $password;
        $this->compressionLevel = $compressionLevel;
        $this->overwriteMode = $overwriteMode;
        $this->solidMode = $solidMode;
        $this->encryptFilenames = $encryptFilenames;
        $this->fallbackEncoding = $fallbackEncoding ?? FallbackEncoding::asIs();
        $this->env = $env;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getCompressionLevel(): int
    {
        return $this->compressionLevel;
    }

    public function getOverwriteMode(): string
    {
        return $this->overwriteMode;
    }

    public function getSolidMode(): ?SolidMode
    {
        return $this->solidMode;
    }

    public function isEncryptFilenames(): bool
    {
        return $this->encryptFilenames;
    }

    public function getFallbackEncoding(): FallbackEncoding
    {
        return $this->fallbackEncoding;
    }

    /**
     * @return array<string, string>
     */
    public function getEnv(): array
    {
        return $this->env;
    }
}
