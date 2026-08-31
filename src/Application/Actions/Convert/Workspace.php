<?php

declare(strict_types=1);

namespace App\Application\Actions\Convert;

use RuntimeException;

/**
 * A private temporary directory for one request, removed when it is done.
 *
 * The old version wrote into `app/upload`, a folder inside the document root
 * that every request shared - so two people converting at once deleted each
 * other's files, and whatever was left sat where the web server could serve it.
 */
final class Workspace
{
    private function __construct(private readonly string $root)
    {
    }

    public static function create(): self
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'image-converter-' . bin2hex(random_bytes(8));
        if (!mkdir($root, 0o700, true) && !is_dir($root)) {
            throw new RuntimeException('Could not create a temporary directory.');
        }
        return new self($root);
    }

    public function path(string $name): string
    {
        return $this->root . DIRECTORY_SEPARATOR . basename($name);
    }

    public function close(): void
    {
        if (!is_dir($this->root)) {
            return;
        }
        foreach ((array) glob($this->root . DIRECTORY_SEPARATOR . '*') as $file) {
            if (is_string($file)) {
                @unlink($file);
            }
        }
        @rmdir($this->root);
    }
}
