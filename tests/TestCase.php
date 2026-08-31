<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request as SlimRequest;
use Slim\Psr7\UploadedFile;
use Slim\Psr7\Uri;

abstract class TestCase extends PHPUnitTestCase
{
    /**
     * The same application the front controller builds.
     *
     * @return App<ContainerInterface|null>
     */
    protected function getAppInstance(): App
    {
        $app = (require __DIR__ . '/../app/bootstrap.php')(compile: false);
        $app->addRoutingMiddleware();
        $app->addBodyParsingMiddleware();
        $app->addErrorMiddleware(false, false, false);

        return $app;
    }

    /** @param array<string, string> $headers */
    protected function createRequest(
        string $method,
        string $path,
        array $headers = ['HTTP_ACCEPT' => 'application/json'],
    ): Request {
        $uri = new Uri('', '', 80, $path);
        $handle = fopen('php://temp', 'w+');
        self::assertNotFalse($handle);
        $stream = (new StreamFactory())->createStreamFromResource($handle);

        $h = new Headers();
        foreach ($headers as $name => $value) {
            $h->addHeader($name, $value);
        }

        return new SlimRequest($method, $uri, $h, [], [], $stream);
    }

    /**
     * A request carrying one uploaded file, the way a browser would send it.
     */
    protected function createUploadRequest(
        string $path,
        string $bytes,
        string $clientName = 'image.png',
        string $field = 'file',
        int $error = UPLOAD_ERR_OK,
    ): Request {
        $temp = (string) tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($temp, $bytes);

        $upload = new UploadedFile(
            $temp,
            $clientName,
            'application/octet-stream',
            strlen($bytes),
            $error,
        );

        return $this->createRequest('POST', $path)
            ->withUploadedFiles([$field => $upload]);
    }
}
