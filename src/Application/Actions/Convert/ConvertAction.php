<?php

declare(strict_types=1);

namespace App\Application\Actions\Convert;

use App\Domain\Image\ConversionFailed;
use App\Domain\Image\ImageConverter;
use App\Domain\Image\ImageFormat;
use App\Domain\Image\SafeName;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Converts one uploaded image.
 *
 * Answers either JSON with a data URL, or the image itself as a download.
 */
final class ConvertAction
{
    public function __construct(
        private readonly ImageConverter $converter,
        private readonly bool $asDownload = false,
    ) {
    }

    /** @param array<string, string> $args */
    public function __invoke(Request $request, Response $response, array $args = []): Response
    {
        try {
            $to = OutputFormat::from($request, $args);
            $upload = Upload::single($request);
            $bytes = $upload->getStream()->getContents();

            $info = $this->converter->inspect($bytes);
            $converted = $this->converter->convert($bytes, $to);
        } catch (ConversionFailed $failure) {
            return Problem::respond($response, $failure);
        }

        if ($this->asDownload) {
            $name = SafeName::forDownload($upload->getClientFilename(), $to);
            $response->getBody()->write($converted);

            return $response
                ->withHeader('Content-Type', $to->mimeType())
                ->withHeader('Content-Disposition', 'attachment; filename="' . $name . '"')
                ->withHeader('Content-Length', (string) strlen($converted))
                ->withHeader('X-Content-Type-Options', 'nosniff');
        }

        return Problem::json($response, 200, [
            'status' => 200,
            'format' => $to->value,
            'width' => $info->width,
            'height' => $info->height,
            'sourceFormat' => $info->format->value,
            'sourceBytes' => $info->bytes,
            'bytes' => strlen($converted),
            // Kept for the original clients, which read exactly this field.
            'base64' => 'data:' . $to->mimeType() . ';base64,' . base64_encode($converted),
        ]);
    }
}
