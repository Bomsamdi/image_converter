<?php

declare(strict_types=1);

namespace App\Domain\Image;

use GdImage;

/**
 * Converts one image's bytes into another format's bytes.
 *
 * Everything here works on strings rather than paths. That is deliberate: the
 * old version moved the upload into a web-served folder under a name the
 * client chose, which is how an uploaded `photo.png.php` became executable.
 * Bytes in, bytes out, nothing on disk.
 */
final class ImageConverter
{
    public function __construct(private readonly ConversionLimits $limits = new ConversionLimits())
    {
    }

    public function limits(): ConversionLimits
    {
        return $this->limits;
    }

    /**
     * Reads [$bytes] and returns the same image encoded as [$to].
     *
     * @throws ConversionFailed when the input is not an image this service
     *         handles, is over a limit, or cannot be decoded.
     */
    public function convert(string $bytes, ImageFormat $to): string
    {
        if (!in_array($to, ImageFormat::outputs(), true)) {
            throw ConversionFailed::unsupportedOutput($to->value);
        }
        if (!$to->isWritable()) {
            throw ConversionFailed::formatUnavailable($to);
        }

        $this->guardSize($bytes);
        $source = $this->inspect($bytes);
        $this->guardPixels($source);

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw ConversionFailed::decodeFailed();
        }

        // A palette image has no alpha channel, and WebP and AVIF both want
        // true colour; without this a converted GIF-like PNG loses its
        // transparency to black.
        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);

        // No imagedestroy: it has done nothing since PHP 8.0, when GdImage
        // became an object the collector owns, and PHP 8.5 deprecates it.
        return $this->encode($image, $to);
    }

    /**
     * What the bytes actually are, whatever the filename claimed.
     *
     * @throws ConversionFailed
     */
    public function inspect(string $bytes): ImageInfo
    {
        $size = @getimagesizefromstring($bytes);
        if ($size === false) {
            throw ConversionFailed::unsupportedInput();
        }

        $format = ImageFormat::fromImageType($size[2]);
        if ($format === null) {
            throw ConversionFailed::unsupportedInput();
        }

        return new ImageInfo(
            format: $format,
            width: $size[0],
            height: $size[1],
            bytes: strlen($bytes),
        );
    }

    /** True when the bytes are an image this service can read. */
    public function isSupported(string $bytes): bool
    {
        try {
            $this->inspect($bytes);
            return true;
        } catch (ConversionFailed) {
            return false;
        }
    }

    private function encode(GdImage $image, ImageFormat $to): string
    {
        ob_start();
        $written = match ($to) {
            ImageFormat::Webp => imagewebp($image, null, $this->limits->quality),
            ImageFormat::Avif => imageavif($image, null, $this->limits->quality),
            ImageFormat::Png => imagepng($image),
            ImageFormat::Jpeg => imagejpeg($image, null, $this->limits->quality),
        };
        $encoded = (string) ob_get_clean();

        if ($written === false || $encoded === '') {
            throw ConversionFailed::encodeFailed($to);
        }
        return $encoded;
    }

    private function guardSize(string $bytes): void
    {
        $length = strlen($bytes);
        if ($length > $this->limits->maxBytes) {
            throw ConversionFailed::tooLarge($length, $this->limits->maxBytes);
        }
    }

    private function guardPixels(ImageInfo $info): void
    {
        if ($info->pixels() > $this->limits->maxPixels) {
            throw ConversionFailed::tooManyPixels($info->pixels(), $this->limits->maxPixels);
        }
    }
}
