<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Application\ResponseEmitter\ResponseEmitter;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpInternalServerErrorException;

/**
 * Turns a fatal error into a 500 the client can read, instead of a blank page.
 *
 * Only a *fatal* error. The skeleton's version acted on whatever
 * `error_get_last()` returned, and that function reports the last error of any
 * severity - including one that was suppressed with `@` and handled. So a
 * request that skipped a non-image inside an archive finished perfectly, sent
 * its ZIP, and then had a 500 written on top of it by this handler. It only
 * showed up against a running server, because a test that calls
 * `$app->handle()` never reaches the shutdown.
 */
final class ShutdownHandler
{
    private const FATAL = E_ERROR
        | E_PARSE
        | E_CORE_ERROR
        | E_CORE_WARNING
        | E_COMPILE_ERROR
        | E_COMPILE_WARNING
        | E_USER_ERROR;

    public function __construct(
        private readonly Request $request,
        private readonly HttpErrorHandler $errorHandler,
        private readonly bool $displayErrorDetails,
    ) {
    }

    /** Whether an error of this type ended the request. */
    public static function isFatal(int $type): bool
    {
        return ($type & self::FATAL) !== 0;
    }

    public function __invoke(): void
    {
        $error = error_get_last();
        if ($error === null || !self::isFatal((int) $error['type'])) {
            return;
        }

        $message = 'An error occurred while processing the request.';
        if ($this->displayErrorDetails) {
            $message = sprintf(
                '%s in %s on line %d.',
                $error['message'],
                $error['file'],
                $error['line'],
            );
        }

        $response = ($this->errorHandler)(
            $this->request,
            new HttpInternalServerErrorException($this->request, $message),
            $this->displayErrorDetails,
            false,
            false,
        );

        (new ResponseEmitter())->emit($response);
    }
}
