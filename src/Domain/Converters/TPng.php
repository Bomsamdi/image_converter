<?php
namespace App\Domain\Converters;
trait TPng {
    function createPng($path){
        return imagecreatefrompng($path);
    }
}
?>