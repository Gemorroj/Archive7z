<?php

declare(strict_types=1);

namespace Archive7z\Config;

/**
 * @see https://documentation.help/7-Zip/method.htm#Solid
 */
final readonly class SolidMode implements \Stringable
{
    public const ON = 'on';
    public const OFF = 'off';
    /**
     * Use a separate solid block for each new file extension.
     */
    public const E = 'e';

    private function __construct(
        private ?string $mode,
        private ?int $filesLimit,
        private ?int $totalSizeLimit,
    ) {
    }

    public static function on(): self
    {
        return new self(self::ON, null, null);
    }

    public static function off(): self
    {
        return new self(self::OFF, null, null);
    }

    /**
     * Use a separate solid block for each new file extension.
     */
    public static function perExtension(): self
    {
        return new self(self::E, null, null);
    }

    public static function filesLimit(int $limit): self
    {
        return new self(null, $limit, null);
    }

    public static function totalSizeLimit(int $limit): self
    {
        return new self(null, null, $limit);
    }

    public static function limits(?int $filesLimit, ?int $totalSizeLimit): self
    {
        return new self(null, $filesLimit, $totalSizeLimit);
    }

    public function __toString(): string
    {
        if (null !== $this->filesLimit || null !== $this->totalSizeLimit) {
            $mode = '';
            if (null !== $this->filesLimit) {
                $mode .= $this->filesLimit.'f';
            }
            if (null !== $this->totalSizeLimit) {
                $mode .= $this->totalSizeLimit.'b';
            }

            return $mode;
        }

        return $this->mode ?? '';
    }
}
