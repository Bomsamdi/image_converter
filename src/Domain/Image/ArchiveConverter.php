<?php

declare(strict_types=1);

namespace App\Domain\Image;

use ZipArchive;

/**
 * Converts every image in a ZIP and returns a new ZIP.
 *
 * Nothing is ever extracted to disk. Entries are read straight out of the
 * archive with a stream, converted in memory, and written into the output
 * archive - which sidesteps zip-slip entirely rather than trying to detect it,
 * because there is no path to escape from.
 *
 * The old version did the opposite: it wiped the whole upload directory on
 * every request, called `extractTo` on an untrusted archive, and left the
 * unpacked files in a web-served folder.
 */
final class ArchiveConverter
{
    public function __construct(
        private readonly ImageConverter $images,
        private readonly ConversionLimits $limits = new ConversionLimits(),
    ) {
    }

    /**
     * Reads the ZIP at [$sourcePath] and writes a converted one to
     * [$destinationPath].
     *
     * Entries that are not images this service reads are skipped, not refused:
     * an archive exported from a design tool is full of stray files, and
     * failing the whole request over a `.DS_Store` would be useless.
     *
     * @return ArchiveResult what was converted and what was skipped
     * @throws ConversionFailed
     */
    public function convert(string $sourcePath, string $destinationPath, ImageFormat $to): ArchiveResult
    {
        $source = new ZipArchive();
        if ($source->open($sourcePath, ZipArchive::RDONLY) !== true) {
            throw ConversionFailed::notAnArchive();
        }

        try {
            $this->guardArchive($source);

            $out = new ZipArchive();
            if ($out->open($destinationPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw ConversionFailed::notAnArchive();
            }

            $converted = 0;
            $skipped = [];
            $unpacked = 0;

            for ($i = 0; $i < $source->numFiles; $i++) {
                $stat = $source->statIndex($i);
                if ($stat === false || str_ends_with((string) $stat['name'], '/')) {
                    continue;
                }

                $name = (string) $stat['name'];
                $unpacked += (int) $stat['size'];
                if ($unpacked > $this->limits->maxArchiveBytes) {
                    $out->close();
                    throw ConversionFailed::archiveTooBig($this->limits->maxArchiveBytes);
                }

                $bytes = $source->getFromIndex($i, $this->limits->maxBytes + 1);
                if ($bytes === false || !$this->images->isSupported($bytes)) {
                    $skipped[] = $name;
                    continue;
                }

                try {
                    $out->addFromString($this->renamed($name, $to), $this->images->convert($bytes, $to));
                    $converted++;
                } catch (ConversionFailed) {
                    // One bad image does not spoil the archive.
                    $skipped[] = $name;
                }
            }

            $out->close();

            if ($converted === 0) {
                @unlink($destinationPath);
                throw ConversionFailed::archiveEmpty();
            }

            return new ArchiveResult(converted: $converted, skipped: $skipped);
        } finally {
            $source->close();
        }
    }

    /**
     * Keeps the entry's folders, swaps the extension, and drops anything that
     * could climb out of the archive when someone unpacks it.
     */
    private function renamed(string $entry, ImageFormat $to): string
    {
        $parts = array_filter(
            explode('/', str_replace('\\', '/', $entry)),
            static fn (string $part): bool => $part !== '' && $part !== '.' && $part !== '..',
        );

        $safe = array_map(SafeName::forPathSegment(...), $parts);
        $file = (string) array_pop($safe);
        $stem = pathinfo($file, PATHINFO_FILENAME);
        $safe[] = ($stem === '' ? 'image' : $stem) . '.' . $to->extension();

        return implode('/', $safe);
    }

    private function guardArchive(ZipArchive $archive): void
    {
        if ($archive->numFiles > $this->limits->maxArchiveEntries) {
            throw ConversionFailed::archiveTooManyEntries($this->limits->maxArchiveEntries);
        }
    }
}
