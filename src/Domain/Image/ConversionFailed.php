<?php

declare(strict_types=1);

namespace App\Domain\Image;

use RuntimeException;

/**
 * A conversion the caller can fix: a wrong format, a file too large, an
 * archive with nothing in it.
 *
 * Distinct from a programming error on purpose - every one of these is a 4xx,
 * and the message is safe to show.
 */
final class ConversionFailed extends RuntimeException
{
    public static function unsupportedInput(): self
    {
        return new self(
            'The file is not a PNG, JPEG, WebP or AVIF image. The check reads '
            . 'the file, not its name.'
        );
    }

    public static function unsupportedOutput(string $asked): self
    {
        return new self(sprintf('"%s" is not a format this service writes.', $asked));
    }

    public static function formatUnavailable(ImageFormat $format): self
    {
        return new self(sprintf(
            'This server\'s GD cannot write %s. It was built without support for it.',
            strtoupper($format->value)
        ));
    }

    public static function tooLarge(int $bytes, int $limit): self
    {
        return new self(sprintf(
            'The file is %s and the limit is %s.',
            self::human($bytes),
            self::human($limit)
        ));
    }

    public static function tooManyPixels(int $pixels, int $limit): self
    {
        return new self(sprintf(
            'The image is %s megapixels and the limit is %s. A small file can '
            . 'still decode to an enormous bitmap.',
            number_format($pixels / 1e6, 1),
            number_format($limit / 1e6, 1)
        ));
    }

    public static function decodeFailed(): self
    {
        return new self('The image could not be decoded. It may be truncated.');
    }

    public static function encodeFailed(ImageFormat $format): self
    {
        return new self(sprintf('The image could not be written as %s.', strtoupper($format->value)));
    }

    public static function notAnArchive(): self
    {
        return new self('The file is not a readable ZIP archive.');
    }

    public static function archiveEmpty(): self
    {
        return new self('The archive holds no PNG, JPEG, WebP or AVIF images.');
    }

    public static function archiveTooBig(int $limit): self
    {
        return new self(sprintf(
            'The archive unpacks to more than %s.',
            self::human($limit)
        ));
    }

    public static function archiveTooManyEntries(int $limit): self
    {
        return new self(sprintf('The archive holds more than %d files.', $limit));
    }

    public static function missingUpload(): self
    {
        return new self('The request carried no file in the "file" field.');
    }

    public static function uploadFailed(int $phpUploadError): self
    {
        return new self(sprintf('The upload did not complete (PHP error %d).', $phpUploadError));
    }

    private static function human(int $bytes): string
    {
        return $bytes >= 1024 * 1024
            ? number_format($bytes / (1024 * 1024), 1) . ' MB'
            : number_format($bytes / 1024, 0) . ' KB';
    }
}
