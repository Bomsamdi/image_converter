<?php

namespace App\Domain\Converters;

interface IConverter
{
    public static function convertImage($img, $path);
}
