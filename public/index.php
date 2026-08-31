<?php

declare(strict_types=1);

use App\Application\Handlers\HttpErrorHandler;
use App\Application\Handlers\ShutdownHandler;
use App\Application\ResponseEmitter\ResponseEmitter;
use App\Application\Settings\SettingsInterface;
use Slim\Factory\ServerRequestCreatorFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = (require __DIR__ . '/../app/bootstrap.php')(
    compile: filter_var($_ENV['APP_COMPILE'] ?? getenv('APP_COMPILE') ?: 'false', FILTER_VALIDATE_BOOLEAN)
);

$container = $app->getContainer();
$settings = $container->get(SettingsInterface::class);
$displayErrorDetails = (bool) $settings->get('displayErrorDetails');

$request = ServerRequestCreatorFactory::create()->createServerRequestFromGlobals();

$errorHandler = new HttpErrorHandler($app->getCallableResolver(), $app->getResponseFactory());
register_shutdown_function(new ShutdownHandler($request, $errorHandler, $displayErrorDetails));

$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(
    $displayErrorDetails,
    (bool) $settings->get('logError'),
    (bool) $settings->get('logErrorDetails'),
    $container->get(Psr\Log\LoggerInterface::class)
);
$errorMiddleware->setDefaultErrorHandler($errorHandler);

(new ResponseEmitter())->emit($app->handle($request));
