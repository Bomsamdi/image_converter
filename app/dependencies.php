<?php

declare(strict_types=1);

use App\Application\Settings\SettingsInterface;
use App\Domain\Image\ArchiveConverter;
use App\Domain\Image\ConversionLimits;
use App\Domain\Image\ImageConverter;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Slim\Views\PhpRenderer;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        LoggerInterface::class => function (ContainerInterface $c) {
            $loggerSettings = $c->get(SettingsInterface::class)->get('logger');
            $logger = new Logger($loggerSettings['name']);
            $logger->pushProcessor(new UidProcessor());
            $logger->pushHandler(new StreamHandler($loggerSettings['path'], $loggerSettings['level']));
            return $logger;
        },

        // Absolute, because the original path was relative to the working
        // directory and only resolved when the server was started from public/.
        PhpRenderer::class => fn () => new PhpRenderer(__DIR__ . '/../templates/'),

        ConversionLimits::class => fn (ContainerInterface $c) => ConversionLimits::fromSettings(
            $c->get(SettingsInterface::class)->get('limits')
        ),

        ImageConverter::class => fn (ContainerInterface $c) => new ImageConverter(
            $c->get(ConversionLimits::class)
        ),

        ArchiveConverter::class => fn (ContainerInterface $c) => new ArchiveConverter(
            $c->get(ImageConverter::class),
            $c->get(ConversionLimits::class)
        ),
    ]);
};
