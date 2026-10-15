<?php

declare(strict_types=1);

namespace Archive7z\Config;

use Archive7z\Exception\InvalidArgumentException;

/**
 * Configuration of the extraction side of an {@see Options}.
 */
final readonly class ExtractOptions
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

    /**
     * @see https://documentation.help/7-Zip/overwrite.htm
     */
    private string $overwriteMode;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(string $overwriteMode = self::OVERWRITE_MODE_A)
    {
        if (!\in_array($overwriteMode, [
            self::OVERWRITE_MODE_A,
            self::OVERWRITE_MODE_S,
            self::OVERWRITE_MODE_T,
            self::OVERWRITE_MODE_U,
        ], true)) {
            throw new InvalidArgumentException('Overwrite mode is not available');
        }

        $this->overwriteMode = $overwriteMode;
    }

    public function getOverwriteMode(): string
    {
        return $this->overwriteMode;
    }
}
