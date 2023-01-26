<?php

namespace App\Domain\Converters;

trait TPng
{
    public static function createPng($path)
    {
        return imagecreatefrompng($path);
    }
}
