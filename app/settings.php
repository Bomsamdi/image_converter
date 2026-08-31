<?php

declare(strict_types=1);

use App\Application\Settings\Settings;
use App\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Monolog\Level;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        SettingsInterface::class => function () {
            // Error details are off unless asked for. The original had them on
            // unconditionally, which sends a stack trace to whoever asks.
            $debug = filter_var(
                $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: 'false',
                FILTER_VALIDATE_BOOLEAN
            );
            $inDocker = isset($_ENV['docker']) || getenv('docker') !== false;

            return new Settings([
                'displayErrorDetails' => $debug,
                'logError' => true,
                'logErrorDetails' => $debug,
                'logger' => [
                    'name' => 'image-converter',
                    'path' => $inDocker ? 'php://stdout' : __DIR__ . '/../logs/app.log',
                    'level' => $debug ? Level::Debug : Level::Info,
                ],
                // Every limit is overridable, so a private deployment can be
                // generous and a public one can be strict.
                'limits' => [
                    'maxBytes' => (int) ($_ENV['MAX_IMAGE_BYTES'] ?? 20 * 1024 * 1024),
                    'maxPixels' => (int) ($_ENV['MAX_IMAGE_PIXELS'] ?? 40_000_000),
                    'maxArchiveEntries' => (int) ($_ENV['MAX_ARCHIVE_ENTRIES'] ?? 500),
                    'maxArchiveBytes' => (int) ($_ENV['MAX_ARCHIVE_BYTES'] ?? 200 * 1024 * 1024),
                    'quality' => (int) ($_ENV['OUTPUT_QUALITY'] ?? 82),
                ],
            ]);
        }
    ]);
};
