<?php

declare(strict_types=1);

namespace App\Ui\Http;

use Fig\Http\Message\StatusCodeInterface;
use JsonSerializable;
use Slim\Psr7\Stream;

final class Payload implements JsonSerializable
{
    public function __construct(
        private int $status = StatusCodeInterface::STATUS_OK,
        private ?array $data = null,
        private ?string $error = null,
        private ?Stream $stream = null,
    ) {}

    public function status(): int
    {
        return $this->status;
    }

    public function data(): array
    {
        return $this->data;
    }

    public function error(): ?string
    {
        return $this->error;
    }

    public function stream(): ?Stream
    {
        return $this->stream;
    }

    public function jsonSerialize(): array
    {
        $payload = [
            'statusCode' => $this->status,
        ];

        if ($this->data !== null) {
            $payload['data'] = $this->data;
        } elseif ($this->error !== null) {
            $payload['error'] = $this->error;
        }

        return $payload;
    }
}
