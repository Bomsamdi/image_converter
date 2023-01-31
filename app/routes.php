<?php

declare(strict_types=1);

use App\Domain\Converters\FlxZipArchive;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use App\Domain\Converters\WebpConverter;
use Slim\Psr7\Stream;

return function (App $app) {

    $uploadFolder = "upload";

    $app->options('/{routes:.*}', function (Request $request, Response $response) {
        // CORS Pre-Flight OPTIONS Request Handler
        return $response;
    });

    $app->get('/', function (Request $request, Response $response) use ($app) {
        $response = $app->getContainer()->get('view')->render($response, 'ConverterHome.phtml');
        return $response;
    });

    $app->get('/single-file-converter', function (Request $request, Response $response) use ($app) {

        $response = $app->getContainer()->get('view')->render($response, 'ConverterSingleFilePicker.phtml');
        return $response;
    });

    $app->get('/zip-file-converter', function (Request $request, Response $response) use ($app) {

        $response = $app->getContainer()->get('view')->render($response, 'ConverterZipPicker.phtml');
        return $response;
    });

    $app->post('/convert-to-webp', function (Request $request, Response $response) use ($uploadFolder)
    {
        if (isset($_FILES['file'])) {
            $uploadedFile = $_FILES['file'];
            if ($uploadedFile['error'] === 0) {
                $file_name = $uploadedFile["name"];
                $path = __DIR__ . DIRECTORY_SEPARATOR . $uploadFolder;
                $target_path = __DIR__ . DIRECTORY_SEPARATOR . $uploadFolder . DIRECTORY_SEPARATOR . $file_name;
                move_uploaded_file($uploadedFile["tmp_name"], $target_path);

                $valueArray = explode('.', $uploadedFile['name']);
                if (WebpConverter::checkFileFormat($valueArray[1])) {
                    unlink($target_path);
                }
                if ($valueArray[1] === 'png') {
                    $img = WebpConverter::createPng($target_path);
                } elseif ($valueArray[1] === 'jpeg' || $valueArray[1] === 'jpg') {
                    $img = WebpConverter::createJpg($target_path);
                } else {
                    return WebpConverter::wrongFileFormatResponse($response);
                }

                WebpConverter::toTrueColor($img);
                WebpConverter::convertImage($img, $path . DIRECTORY_SEPARATOR . $valueArray[0] . '.webp');
                imagedestroy($img);
                unlink($target_path);

                $pathWebpFile = $path . DIRECTORY_SEPARATOR . $valueArray[0] . '.webp';
                $base64 = WebpConverter::imageToBase64($pathWebpFile);
                unlink($pathWebpFile);

                return WebpConverter::successResponse($response, $base64);
            } else {
                return WebpConverter::uploadingFileFailedResponse($response);
            }
        } else {
            return WebpConverter::missingFileResponse($response);
        }
    });

    $app->post('/convert-to-webp-download', function (Request $request, Response $response) use ($uploadFolder)
    {
        if (isset($_FILES['file'])) {
            $uploadedFile = $_FILES['file'];
            if ($uploadedFile['error'] === 0) {
                $file_name = $uploadedFile["name"];
                $path = __DIR__ . DIRECTORY_SEPARATOR . $uploadFolder;
                $target_path = __DIR__ . DIRECTORY_SEPARATOR . $uploadFolder . DIRECTORY_SEPARATOR . $file_name;
                move_uploaded_file($uploadedFile["tmp_name"], $target_path);

                $valueArray = explode('.', $uploadedFile['name']);
                if (WebpConverter::checkFileFormat($valueArray[1])) {
                    unlink($target_path);
                }
                if ($valueArray[1] === 'png') {
                    $img = WebpConverter::createPng($target_path);
                } elseif ($valueArray[1] === 'jpeg' || $valueArray[1] === 'jpg') {
                    $img = WebpConverter::createJpg($target_path);
                } else {
                    return WebpConverter::wrongFileFormatResponse($response);
                }

                WebpConverter::toTrueColor($img);
                WebpConverter::convertImage($img, $path . DIRECTORY_SEPARATOR . $valueArray[0] . '.webp');
                imagedestroy($img);
                unlink($target_path);

                $pathWebpFile = $path . DIRECTORY_SEPARATOR . $valueArray[0] . '.webp';

                $fh = fopen($pathWebpFile, 'rb');

                $stream = new Stream($fh); // create a stream instance for the response body

                return $response->withHeader('Content-Type', 'application/force-download')
                    ->withHeader('Content-Type', 'application/octet-stream')
                    ->withHeader('Content-Type', 'application/download')
                    ->withHeader('Content-Description', 'File Transfer')
                    ->withHeader('Content-Transfer-Encoding', 'binary')
                    ->withHeader('Content-Disposition', 'attachment; filename="' . basename($pathWebpFile) . '"')
                    ->withHeader('Expires', '0')
                    ->withHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
                    ->withHeader('Pragma', 'public')
                    ->withBody($stream);
            } else {
                return WebpConverter::uploadingFileFailedResponse($response);
            }
        } else {
            return WebpConverter::missingFileResponse($response);
        }
    });

    $app->post('/convert-to-zip-webp-download', function (Request $request, Response $response) use ($uploadFolder)
    {
        $it = new RecursiveDirectoryIterator(__DIR__ . DIRECTORY_SEPARATOR . $uploadFolder, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it,
            RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $file) {
            if ($file->isDir()){
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        if (isset($_FILES['file'])) {
            $uploadedFile = $_FILES['file'];
            if ($uploadedFile['error'] === 0) {
                $file_name = $uploadedFile["name"];
                $path = __DIR__ . DIRECTORY_SEPARATOR . $uploadFolder;
                $target_path = __DIR__ . DIRECTORY_SEPARATOR . $uploadFolder . DIRECTORY_SEPARATOR . $file_name;
                move_uploaded_file($uploadedFile["tmp_name"], $target_path);

                $valueArray = explode('.', $uploadedFile['name']);
                if (WebpConverter::checkArchiveFormat($valueArray[1])) {
                    unlink($target_path);
                    return WebpConverter::wrongFileFormatResponse($response);
                }

                $zip = new ZipArchive();
                $zip->open($target_path);
                $zip->extractTo($path);
                $zip->close();

                unlink($target_path);
                $new_path = $path . DIRECTORY_SEPARATOR . $valueArray[0];

                $files = scandir($new_path);


                foreach ($files as $file){
                    if(WebpConverter::checkFileFormat(explode('.', $file)[1]) === false){
                        $images[] = $file;
                    }
                }

                foreach ($images as $image){
                    $imageArray = explode('.', $image);
                    $new_target_path = $new_path . DIRECTORY_SEPARATOR . $image;
                    if ($imageArray[1] === 'png') {
                        $img = WebpConverter::createPng($new_target_path);
                    } elseif ($imageArray[1] === 'jpeg' || $imageArray[1] === 'jpg') {
                        $img = WebpConverter::createJpg($new_target_path);
                    }
                    WebpConverter::toTrueColor($img);
                    WebpConverter::convertImage($img, $new_path . DIRECTORY_SEPARATOR . $imageArray[0] . '.webp');
                    imagedestroy($img);
                    unlink($new_target_path);
                }

                $za = new FlxZipArchive();
                $res = $za->open($new_path . '.zip', ZipArchive::CREATE);
                if($res === TRUE)
                {
                    $za->addDir($new_path, basename($new_path));
                    $za->close();
                }

                $it = new RecursiveDirectoryIterator($new_path, RecursiveDirectoryIterator::SKIP_DOTS);
                $files = new RecursiveIteratorIterator($it,
                    RecursiveIteratorIterator::CHILD_FIRST);
                foreach($files as $file) {
                    if ($file->isDir()){
                        rmdir($file->getRealPath());
                    } else {
                        unlink($file->getRealPath());
                    }
                }
                rmdir($new_path);
                //unlink($target_path);
//                $fh = fopen($new_path . '.zip', 'rb');
//
//                $stream = new Stream($fh); // create a stream instance for the response body

//                $response = $response
//                    ->withHeader('Content-Type', 'application/force-download')
//                    ->withHeader('Content-Description', 'File Transfer')
//                    ->withHeader('Content-length', filesize($new_path . '.zip'))
//                    ->withHeader('Content-Type', 'application/octet-stream')
//                    ->withHeader('Content-Disposition', 'attachment; filename="' . basename($new_path . '.zip') . '"')
//                    ->withHeader('Expires', '0')
//                    ->withHeader('Pragma', 'no-cache')
//                    ->withBody($stream);
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment;filename="'.basename($new_path . '.zip').'"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($new_path . '.zip'));

                ob_clean();
                flush();
                readfile($new_path . '.zip');
                exit;


//                return $response->withHeader('Content-Type', 'application/force-download')
//                    ->withHeader('Content-Type', 'application/octet-stream')
//                    ->withHeader('Content-Type', 'application/download')
//                    ->withHeader('Content-Type', 'application/zip')
//                    ->withHeader('Content-length', filesize($new_path . '.zip'))
//                    ->withHeader('Content-Description', 'File Transfer')
//                    ->withHeader('Content-Transfer-Encoding', 'binary')
//                    ->withHeader('Content-Disposition', 'attachment; filename="' . basename($new_path . '.zip') . '"')
//                    ->withHeader('Expires', '0')
//                    ->withHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
//                    ->withHeader('Pragma', 'public')
//                    ->withBody($stream);
                //return $response;
            } else {
                return WebpConverter::uploadingFileFailedResponse($response);
            }
        } else {
            return WebpConverter::missingFileResponse($response);
        }
    });
};
