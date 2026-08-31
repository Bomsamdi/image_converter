<?php

declare(strict_types=1);

namespace App\Domain\Image;

/**
 * The ceilings that keep a public endpoint from being a denial-of-service tool.
 *
 * Pixels matter as much as bytes: a 40 KB PNG can decode to 100 megapixels,
 * and GD allocates four bytes per pixel before anything else happens.
 */
final class ConversionLimits
{
    public function __construct(
        public readonly int $maxBytes = 20 * 1024 * 1024,
        public readonly int $maxPixels = 40_000_000,
        public readonly int $maxArchiveEntries = 500,
        public readonly int $maxArchiveBytes = 200 * 1024 * 1024,
        public readonly int $quality = 82,
    ) {
    }

    /** @param array<string, mixed> $settings */
    public static function fromSettings(array $settings): self
    {
        $limits = new self();
        return new self(
            maxBytes: (int) ($settings['maxBytes'] ?? $limits->maxBytes),
            maxPixels: (int) ($settings['maxPixels'] ?? $limits->maxPixels),
            maxArchiveEntries: (int) ($settings['maxArchiveEntries'] ?? $limits->maxArchiveEntries),
            maxArchiveBytes: (int) ($settings['maxArchiveBytes'] ?? $limits->maxArchiveBytes),
            quality: (int) ($settings['quality'] ?? $limits->quality),
        );
    }
}
