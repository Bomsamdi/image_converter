<?php

namespace App\Domain\Converters;

abstract class Converter
{
    public static function imageToBase64($pathWebpFile): string
    {
        $type = pathinfo($pathWebpFile, PATHINFO_EXTENSION);
        $data = file_get_contents($pathWebpFile);
        return 'data:image/' . $type . ';base64,' . base64_encode($data);
    }
}
