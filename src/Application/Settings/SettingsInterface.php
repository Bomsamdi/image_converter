<?php

declare(strict_types=1);

namespace App\Application\Settings;

interface SettingsInterface
{
    /** Everything when [$key] is empty, one entry otherwise, null if absent. */
    public function get(string $key = ''): mixed;
}
