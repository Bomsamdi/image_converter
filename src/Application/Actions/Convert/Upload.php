<?php

declare(strict_types=1);

namespace App\Application\Actions\Convert;

use App\Domain\Image\ConversionFailed;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Pulls the one uploaded file out of a request.
 *
 * Read through PSR-7 rather than `$_FILES`, so the same code path is what the
 * tests exercise - the old version reached for the superglobal and was
 * therefore untestable.
 */
final class Upload
{
    public static function single(Request $request, string $field = 'file'): UploadedFileInterface
    {
        $files = $request->getUploadedFiles();
        $file = $files[$field] ?? null;

        if ($file instanceof UploadedFileInterface === false) {
            throw ConversionFailed::missingUpload();
        }
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw ConversionFailed::uploadFailed($file->getError());
        }
        return $file;
    }
}
