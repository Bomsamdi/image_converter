<?php

declare(strict_types=1);

namespace App\Domain\Converters;

use JsonSerializable;

class ResponseModel implements JsonSerializable
{
    private int $status;

    private ?string $base64;

    private ?string $error;

    public function __construct(int $status, ?string $base64 = null, ?string $error = null)
    {
        $this->status = $status;
        $this->base64 = $base64;
        $this->error = $error;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getBase64(): ?string
    {
        return $this->base64;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function jsonSerialize(): false|string
    {
        return json_encode(array_filter([
            'status' => $this->status,
            'base64' => $this->base64,
            'error' => $this->error,
        ]));
    }
}
