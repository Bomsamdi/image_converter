<?php

namespace App\Domain\Converters;

trait TJpg
{
    public static function createJpg($path)
    {
        return imagecreatefromjpeg($path);
    }
}
