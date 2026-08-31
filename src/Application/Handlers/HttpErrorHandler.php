<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use Psr\Http\Message\ResponseInterface as Response;
use Slim\Exception\HttpException;
use Slim\Handlers\ErrorHandler as SlimErrorHandler;

/**
 * Answers every failure in the same shape the converter uses: a status code
 * that means what it says, and `{"status": n, "error": "..."}`.
 *
 * The skeleton's version wrapped errors in an `ActionError` type with its own
 * vocabulary ("RESOURCE_NOT_FOUND"), which no client of this service reads.
 */
class HttpErrorHandler extends SlimErrorHandler
{
    protected function respond(): Response
    {
        $exception = $this->exception;
        $status = 500;
        $message = 'An internal error occurred while processing the request.';

        if ($exception instanceof HttpException) {
            $status = $exception->getCode();
            $message = $exception->getMessage();
        } elseif ($this->displayErrorDetails) {
            $message = $exception->getMessage();
        }

        $payload = json_encode(
            ['status' => $status, 'error' => $message],
            JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );

        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write((string) $payload);

        return $response->withHeader('Content-Type', 'application/json');
    }
}
