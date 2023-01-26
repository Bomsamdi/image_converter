<?php

declare(strict_types=1);

require '..' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
require '..' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Domain' . DIRECTORY_SEPARATOR . 'Converters' . DIRECTORY_SEPARATOR . 'WebpConverter.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {

    $app->options('/{routes:.*}', function (Request $request, Response $response) {
        // CORS Pre-Flight OPTIONS Request Handler
        return $response;
    });

    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write('Hello world!');
        return $response;
    });

    $app->post('/convert_to_webp', function (Request $request, Response $response) {
        if(isset($_FILES['file'])){
            $uploadedFile = $_FILES['file'];
            if ($uploadedFile['error'] === 0) {
                $file_name = $uploadedFile["name"];
                $path = __DIR__ . DIRECTORY_SEPARATOR . "upload";
                $target_path = __DIR__ . DIRECTORY_SEPARATOR . "upload" . DIRECTORY_SEPARATOR . $file_name;
                move_uploaded_file($uploadedFile["tmp_name"],$target_path);

                $valueArray = explode('.',$uploadedFile['name']);
                if($valueArray[1] !== 'png' && $valueArray[1] !== 'jpeg' && $valueArray[1] !== 'jpg'){
                    unlink($target_path);
                    return WebpConverter::badResponse($response);
                }
                if($valueArray[1] === 'png'){
    				$img = imagecreatefrompng($target_path);
                }
                else if($valueArray[1] === 'jpeg' || $valueArray[1] === 'jpg'){
                    $img = imagecreatefromjpeg($target_path);
                }

                imagepalettetotruecolor($img);
                imagewebp($img, $path . DIRECTORY_SEPARATOR . $valueArray[0] . '.webp');
                imagedestroy($img);
                unlink($target_path);

                $resp = [ 'data' => 'file cornvertion success',
                          'fileUrl' => $path . DIRECTORY_SEPARATOR . $valueArray[0] . '.webp' ]; // TMP
            }else{
                $resp = [ 'data' => 'file upload fail' ];
            }
        }else{
            $resp = [ 'data' => 'request without file' ];
        }
        $response->getBody()->write(json_encode($resp));
        
        return $response;
    });

};
