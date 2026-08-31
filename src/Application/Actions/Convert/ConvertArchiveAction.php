<?php

declare(strict_types=1);

namespace App\Application\Actions\Convert;

use App\Domain\Image\ArchiveConverter;
use App\Domain\Image\ArchiveResult;
use App\Domain\Image\ConversionFailed;
use App\Domain\Image\SafeName;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Stream;

/** Converts every image in an uploaded ZIP and returns a new ZIP. */
final class ConvertArchiveAction
{
    public function __construct(private readonly ArchiveConverter $archives)
    {
    }

    /** @param array<string, string> $args */
    public function __invoke(Request $request, Response $response, array $args = []): Response
    {
        $workspace = Workspace::create();

        try {
            $to = OutputFormat::from($request, $args);
            $upload = Upload::single($request);

            // The upload already lives in a temporary file; the ZIP extension
            // needs a path, so it is moved rather than read into memory.
            $source = $workspace->path('source.zip');
            $upload->moveTo($source);
            $destination = $workspace->path('converted.zip');

            $result = $this->archives->convert($source, $destination, $to);
            $bytes = (string) file_get_contents($destination);
            $name = SafeName::forDownload($upload->getClientFilename(), $to, 'images');
        } catch (ConversionFailed $failure) {
            return Problem::respond($response, $failure);
        } finally {
            $workspace->close();
        }

        $name = pathinfo($name, PATHINFO_FILENAME) . '-' . $to->value . '.zip';

        $response->getBody()->write($bytes);

        return $response
            ->withHeader('Content-Type', 'application/zip')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $name . '"')
            ->withHeader('Content-Length', (string) strlen($bytes))
            ->withHeader('X-Content-Type-Options', 'nosniff')
            // So a caller knows what happened without unpacking the archive.
            ->withHeader('X-Converted-Count', (string) $result->converted)
            ->withHeader('X-Skipped-Count', (string) count($result->skipped));
    }
}
