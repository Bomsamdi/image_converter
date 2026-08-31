<?php

/**
 * Builds the application.
 *
 * One place, used by both the front controller and the tests - the original had
 * the same twenty lines in `public/index.php` and in `tests/TestCase.php`, so a
 * test could pass against wiring the server never used.
 */

declare(strict_types=1);

use DI\ContainerBuilder;
use Slim\App;
use Slim\Factory\AppFactory;

return function (bool $compile = false): App {
    $containerBuilder = new ContainerBuilder();
    if ($compile) {
        $containerBuilder->enableCompilation(__DIR__ . '/../var/cache');
    }

    (require __DIR__ . '/settings.php')($containerBuilder);
    (require __DIR__ . '/dependencies.php')($containerBuilder);

    AppFactory::setContainer($containerBuilder->build());
    $app = AppFactory::create();

    (require __DIR__ . '/middleware.php')($app);
    (require __DIR__ . '/routes.php')($app);

    return $app;
};
