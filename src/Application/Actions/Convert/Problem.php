<?php

declare(strict_types=1);

namespace App\Application\Actions\Convert;

use App\Domain\Image\ConversionFailed;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * The error shape.
 *
 * The original answered HTTP 200 with `{"status": 400}` in the body, so every
 * client had to parse the payload to learn a request had failed. The status
 * line says it now, and the field is kept so the old clients still read
 * something they recognise.
 */
final class Problem
{
    public static function respond(Response $response, ConversionFailed $failure): Response
    {
        return self::json($response, 422, [
            'status' => 422,
            'error' => $failure->getMessage(),
        ]);
    }

    /** @param array<string, mixed> $payload */
    public static function json(Response $response, int $status, array $payload): Response
    {
        $response->getBody()->write(
            (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}
