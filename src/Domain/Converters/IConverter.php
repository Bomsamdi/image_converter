<?php
namespace App\Domain\Converters;
interface IConverter {
  static function fromPng($path, $file) : string;
  static function fromJpg($path, $file) : string;
}
?>