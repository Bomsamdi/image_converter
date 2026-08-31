<?php

declare(strict_types=1);

namespace App\Domain\Image;

/**
 * The formats this service reads and writes.
 *
 * A format is decided by what the bytes are, never by what the filename says -
 * see {@see ImageReader}.
 */
enum ImageFormat: string
{
    case Png = 'png';
    case Jpeg = 'jpeg';
    case Webp = 'webp';
    case Avif = 'avif';

    /**
     * The format of an image GD reported as [IMAGETYPE_*], or null when it is
     * one this service does not handle.
     */
    public static function fromImageType(int $imageType): ?self
    {
        return match ($imageType) {
            IMAGETYPE_PNG => self::Png,
            IMAGETYPE_JPEG => self::Jpeg,
            IMAGETYPE_WEBP => self::Webp,
            IMAGETYPE_AVIF => self::Avif,
            default => null,
        };
    }

    public static function fromName(string $name): ?self
    {
        return match (strtolower(trim($name))) {
            'webp' => self::Webp,
            'avif' => self::Avif,
            'png' => self::Png,
            'jpeg', 'jpg' => self::Jpeg,
            default => null,
        };
    }

    /**
     * The formats a caller may ask for as output.
     *
     * @return list<self>
     */
    public static function outputs(): array
    {
        return [self::Webp, self::Avif];
    }

    public function mimeType(): string
    {
        return 'image/' . $this->value;
    }

    public function extension(): string
    {
        return $this->value === 'jpeg' ? 'jpg' : $this->value;
    }

    /** Whether the build of GD in this process can write this format. */
    public function isWritable(): bool
    {
        return match ($this) {
            self::Webp => function_exists('imagewebp'),
            self::Avif => function_exists('imageavif'),
            self::Png => function_exists('imagepng'),
            self::Jpeg => function_exists('imagejpeg'),
        };
    }
}
