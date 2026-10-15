<?php

declare(strict_types=1);

namespace Archive7z\Config;

/**
 * Immutable configuration of an {@see \Archive7z\Archive7z} instance.
 *
 * Holds the options shared by every operation plus the two directional groups:
 * {@see ExtractOptions} for reading/extracting and {@see CompressOptions} for
 * creating/updating. Keeping them separate makes an option that does not apply
 * to the current direction impossible to pass.
 */
final readonly class Options
{
    /**
     * Shared by extraction and compression.
     */
    private ?string $password;

    /**
     * How entry names are handled when read from an archive and when a caller
     * addresses an entry by name. It does not affect how newly added entries
     * are named. Defaults to detection (see {@see FallbackEncoding::detect()});
     * pass {@see FallbackEncoding::asIs()} to leave the names untouched.
     */
    private FallbackEncoding $fallbackEncoding;

    /**
     * Additional environment variables for the 7-Zip process.
     *
     * @var array<string, string>
     */
    private array $env;

    private ExtractOptions $extract;

    private CompressOptions $compress;

    /**
     * @param array<string, string> $env additional environment variables for the 7-Zip process
     */
    public function __construct(
        ?string $password = null,
        ?FallbackEncoding $fallbackEncoding = null,
        array $env = [],
        ExtractOptions $extract = new ExtractOptions(),
        CompressOptions $compress = new CompressOptions(),
    ) {
        $this->password = $password;
        $this->fallbackEncoding = $fallbackEncoding ?? FallbackEncoding::detect();
        $this->env = $env;
        $this->extract = $extract;
        $this->compress = $compress;
    }

    public function getPassword(): ?string
    {
        return $this->password;
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

    public function getExtract(): ExtractOptions
    {
        return $this->extract;
    }

    public function getCompress(): CompressOptions
    {
        return $this->compress;
    }
}
