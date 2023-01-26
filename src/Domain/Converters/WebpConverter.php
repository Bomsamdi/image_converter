<?php

namespace App\Domain\Converters;

use App\Domain\Converters\IConverter;
use Psr\Http\Message\ResponseInterface as Response;

class WebpConverter extends Converter implements IConverter
{
    use TPng;
    use TJpg;
    use TImage;

    public static array $supportedFormats = ['png', 'jpg', 'jpeg'];

    public static function checkFileFormat($format): bool
    {
        if (!in_array($format, WebpConverter::$supportedFormats)) {
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
}
