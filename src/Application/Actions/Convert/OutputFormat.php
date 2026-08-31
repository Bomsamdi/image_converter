<?php

declare(strict_types=1);

namespace App\Application\Actions\Convert;

use App\Domain\Image\ConversionFailed;
use App\Domain\Image\ImageFormat;
use Psr\Http\Message\ServerRequestInterface as Request;

/** Reads the wanted output format from the route, the query or the form. */
final class OutputFormat
{
    /** @param array<string, string> $args */
    public static function from(Request $request, array $args): ImageFormat
    {
        $asked = $args['format'] ?? null;

        if ($asked === null) {
            $query = $request->getQueryParams();
            $asked = is_string($query['format'] ?? null) ? $query['format'] : null;
        }
        if ($asked === null) {
            $body = $request->getParsedBody();
            if (is_array($body) && is_string($body['format'] ?? null)) {
                $asked = $body['format'];
            }
        }
        if ($asked === null) {
            return ImageFormat::Webp;
        }

        $format = ImageFormat::fromName($asked);
        if ($format === null || !in_array($format, ImageFormat::outputs(), true)) {
            throw ConversionFailed::unsupportedOutput($asked);
        }
        return $format;
    }
}
