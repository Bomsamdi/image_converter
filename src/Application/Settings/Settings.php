<?php

declare(strict_types=1);

namespace App\Application\Settings;

final class Settings implements SettingsInterface
{
    /** @param array<string, mixed> $settings */
    public function __construct(private readonly array $settings)
    {
    }

    public function get(string $key = ''): mixed
    {
        return $key === '' ? $this->settings : ($this->settings[$key] ?? null);
    }
}
