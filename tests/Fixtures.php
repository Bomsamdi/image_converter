<?php

declare(strict_types=1);

namespace Tests;

use ZipArchive;

/**
 * Images and archives made on the fly.
 *
 * Nothing binary is committed: a fixture nobody can read is a fixture nobody
 * checks, and a repository full of sample PNGs is how this one grew to 1.5 MB.
 */
final class Fixtures
{
    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    public static function png(int $width = 40, int $height = 30, bool $withAlpha = true): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        $solid = (int) imagecolorallocate($image, 0x4F, 0x46, 0xE5);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $solid);
        if ($withAlpha) {
            $clear = (int) imagecolorallocatealpha($image, 0, 0, 0, 127);
            imagefilledrectangle($image, 0, 0, (int) ($width / 2), (int) ($height / 2), $clear);
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    public static function jpeg(int $width = 40, int $height = 30): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle(
            $image,
            0,
            0,
            $width - 1,
            $height - 1,
            (int) imagecolorallocate($image, 0xEC, 0x48, 0x99)
        );

        ob_start();
        imagejpeg($image, null, 90);

        return (string) ob_get_clean();
    }

    /**
     * A ZIP holding exactly the entries given.
     *
     * @param array<string, string> $entries name to contents
     */
    public static function zip(array $entries): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'fixture-zip');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        $bytes = (string) file_get_contents($path);
        @unlink($path);

        return $bytes;
    }

    /** How many megapixels a PNG of this size claims, without the pixels. */
    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    public static function hugePng(int $width, int $height): string
    {
        // A one-colour image compresses to almost nothing, which is exactly the
        // shape of a decompression bomb.
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, 0);

        ob_start();
        imagepng($image, null, 9);

        return (string) ob_get_clean();
    }
}
