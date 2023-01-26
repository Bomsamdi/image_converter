<?php
namespace App\Domain\Converters;
trait TJpg {
    function createJpg($path){
        return imagecreatefromjpeg($path);
    }
}
?>