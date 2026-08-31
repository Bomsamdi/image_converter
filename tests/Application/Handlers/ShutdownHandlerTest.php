<?php

declare(strict_types=1);

namespace Tests\Application\Handlers;

use App\Application\Handlers\ShutdownHandler;
use PHPUnit\Framework\TestCase;

final class ShutdownHandlerTest extends TestCase
{
    /** A fatal error is the only thing that should make this handler speak. */
    public function testFatalTypesAreRecognised(): void
    {
        self::assertTrue(ShutdownHandler::isFatal(E_ERROR));
        self::assertTrue(ShutdownHandler::isFatal(E_PARSE));
        self::assertTrue(ShutdownHandler::isFatal(E_COMPILE_ERROR));
        self::assertTrue(ShutdownHandler::isFatal(E_USER_ERROR));
    }

    /**
     * The bug this pins: `@getimagesizefromstring()` on a text file inside an
     * archive records an E_WARNING even though the call is suppressed and its
     * result handled. Treating that as fatal wrote a 500 over a finished ZIP,
     * and only a running server showed it.
     */
    public function testASuppressedWarningIsNotFatal(): void
    {
        self::assertFalse(ShutdownHandler::isFatal(E_WARNING));
        self::assertFalse(ShutdownHandler::isFatal(E_NOTICE));
        self::assertFalse(ShutdownHandler::isFatal(E_DEPRECATED));
        self::assertFalse(ShutdownHandler::isFatal(E_USER_WARNING));
        self::assertFalse(ShutdownHandler::isFatal(E_USER_NOTICE));
        self::assertFalse(ShutdownHandler::isFatal(E_USER_DEPRECATED));
    }
}
