<?php

namespace App\Domain\Converters;

trait TImage
{
    public static function toTrueColor($img): bool
    {
        return imagepalettetotruecolor($img);
    }
}
