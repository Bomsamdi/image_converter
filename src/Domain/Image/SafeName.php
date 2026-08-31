<?php

declare(strict_types=1);

namespace App\Domain\Image;

/**
 * Turns a name a client chose into one that is safe to put in a path, a ZIP
 * entry or a Content-Disposition header.
 *
 * The client's filename is never trusted for anything else: it decides what
 * the download is called, and nothing about where bytes go.
 */
final class SafeName
{
    /** One path segment: no separators, no traversal, no control characters. */
    public static function forPathSegment(string $name): string
    {
        $name = str_replace(["\0", '/', '\\'], '', $name);
        $name = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
        $name = trim($name, " .\t\n\r");
        // Windows reserves these, and a ZIP is often unpacked there.
        if ($name === '' || preg_match('/^(con|prn|aux|nul|com\d|lpt\d)$/i', $name) === 1) {
            return 'file';
        }
        return mb_substr($name, 0, 120);
    }

    /**
     * A download name: the client's stem, sanitised, with the new extension.
     */
    public static function forDownload(?string $clientName, ImageFormat $to, string $fallback = 'image'): string
    {
        // Take the last segment first, then sanitise it: sanitising the whole
        // string turns "../../etc/passwd" into "etcpasswd", which is safe but
        // reads like a bug to whoever downloads it.
        $last = basename(str_replace('\\', '/', (string) $clientName));
        $stem = pathinfo(self::forPathSegment($last), PATHINFO_FILENAME);
        if ($stem === '' || $stem === 'file') {
            $stem = $fallback;
        }
        return $stem . '.' . $to->extension();
    }
}
