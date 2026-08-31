<?php

declare(strict_types=1);

namespace App\Domain\Image;

/** What came out of converting an archive. */
final class ArchiveResult
{
    /** @param list<string> $skipped */
    public function __construct(
        public readonly int $converted,
        public readonly array $skipped = [],
    ) {
    }
}
