<?php

namespace App\Domain\Converters;

use App\Domain\Converters\IConverter;
use Psr\Http\Message\ResponseInterface as Response;
use ZipArchive;

class WebpConverter extends Converter implements IConverter
{
    use TPng;
    use TJpg;
    use TImage;

    public static array $supportedFormats = ['png', 'jpg', 'jpeg'];
    public static array $supportedArchiveFormats = ['zip', 'rar', '7z'];

    public static function checkFileFormat($format): bool
    {
        if (!in_array($format, self::$supportedFormats)) {
            return true;
        } else {
            return false;
        }
    }

    public static function checkArchiveFormat($format): bool
    {
        if (!in_array($format, self::$supportedArchiveFormats)) {
            return true;
        } else {
            return false;
        }
    }

    public static function convertImage($img, $path): bool
    {
        return imagewebp($img, $path);
    }

    public static function successResponse(Response $response, $base64): Response
    {
        $model = new ResponseModel(status: 200, base64: $base64);
        $response->getBody()->write($model->jsonSerialize());
        return $response;
    }

    public static function wrongFileFormatResponse(Response $response): Response
    {
        $model = new ResponseModel(status: 400, error: 'Wrong file format');
        $response->getBody()->write($model->jsonSerialize());
        return $response;
    }

    public static function missingFileResponse(Response $response): Response
    {
        $model = new ResponseModel(status: 400, error:'Request without file');
        $response->getBody()->write($model->jsonSerialize());
        return $response;
    }

    public static function uploadingFileFailedResponse(Response $response): Response
    {
        $model = new ResponseModel(status: 400, error: 'File upload failed');
        $response->getBody()->write($model->jsonSerialize());
        return $response;
    }

    private static function createZip($files = [], $dest = '', $overwrite = false)
    {
        if (file_exists($dest) && !$overwrite) {
            return false;
        }
        if (($files)) {
            $zip = new ZipArchive();
            if ($zip->open($dest, $overwrite ? ZIPARCHIVE::OVERWRITE : ZIPARCHIVE::CREATE) !== true) {
                return false;
            }
            foreach ($files as $file) {
                $zip->addFile($file, $file);
            }
            $zip->close();
            return file_exists($dest);
        } else {
            return false;
        }
    }

    public static function addZip($source, $destination)
    {
        $files_to_zip = glob($source . '/*');
        self::createZip($files_to_zip, $destination);
    }
}

class FlxZipArchive extends ZipArchive
{
    public function addDir($location, $name)
    {
        $this->addEmptyDir($name);
        $this->addDirDo($location, $name);
    }
    private function addDirDo($location, $name)
    {
        $name .= '/';
        $location .= '/';
        $dir = opendir($location);
        while ($file = readdir($dir)) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            $do = (filetype($location . $file) == 'dir') ? 'addDir' : 'addFile';
            $this->$do($location . $file, $name . $file);
        }
    }
}
