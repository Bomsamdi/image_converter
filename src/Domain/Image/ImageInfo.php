<?php

declare(strict_types=1);

namespace App\Domain\Image;

/** What an image turned out to be, read from its bytes. */
final class ImageInfo
{
    public function __construct(
        public readonly ImageFormat $format,
        public readonly int $width,
        public readonly int $height,
        public readonly int $bytes,
    ) {
    }

    public function pixels(): int
    {
        return $this->width * $this->height;
    }
}
