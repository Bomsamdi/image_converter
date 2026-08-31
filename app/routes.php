<?php

declare(strict_types=1);

use App\Application\Actions\Convert\ConvertAction;
use App\Application\Actions\Convert\ConvertArchiveAction;
use App\Domain\Image\ArchiveConverter;
use App\Domain\Image\ImageConverter;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Views\PhpRenderer;

return function (App $app) {
    $container = $app->getContainer();
    if (!$container instanceof ContainerInterface) {
        throw new RuntimeException('The application was built without a container.');
    }

    $page = function (string $template) use ($container): callable {
        return function (Request $request, Response $response) use ($container, $template): Response {
            $view = $container->get(PhpRenderer::class);
            return $view->render($response, $template);
        };
    };

    $convert = function (bool $asDownload) use ($container): callable {
        return function (Request $request, Response $response, array $args) use ($container, $asDownload): Response {
            $action = new ConvertAction($container->get(ImageConverter::class), $asDownload);
            return $action($request, $response, $args);
        };
    };

    $convertArchive = function () use ($container): callable {
        return function (Request $request, Response $response, array $args) use ($container): Response {
            $action = new ConvertArchiveAction($container->get(ArchiveConverter::class));
            return $action($request, $response, $args);
        };
    };

    $app->options('/{routes:.*}', fn (Request $request, Response $response) => $response);

    $app->get('/', $page('ConverterHome.phtml'));
    $app->get('/single-file-converter', $page('ConverterSingleFilePicker.phtml'));
    $app->get('/zip-file-converter', $page('ConverterZipPicker.phtml'));

    // The current API: the format is part of the path.
    $app->post('/api/convert[/{format}]', $convert(false));
    $app->post('/api/convert-download[/{format}]', $convert(true));
    $app->post('/api/convert-archive[/{format}]', $convertArchive());

    // The original paths, kept so anything already pointed at them keeps
    // working. They are WebP only, which is all they ever did.
    $webp = fn (callable $handler): callable =>
        fn (Request $request, Response $response): Response =>
            $handler($request, $response, ['format' => 'webp']);

    $app->post('/convert-to-webp', $webp($convert(false)));
    $app->post('/convert-to-webp-download', $webp($convert(true)));
    $app->post('/convert-to-zip-webp-download', $webp($convertArchive()));
};
