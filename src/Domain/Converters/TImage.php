<?php
namespace App\Domain\Converters;
trait TImage {
    function toTrueColor($img) {
        return imagepalettetotruecolor($img);
    }
}
?>