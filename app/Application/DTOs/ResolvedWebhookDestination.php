<?php

namespace App\Application\DTOs;

final readonly class ResolvedWebhookDestination
{
    public function __construct(
        public string $url,
        public string $host,
        public int $port,
        public string $address,
    ) {}

    public function curlResolveEntry(): string
    {
        $address = str_contains($this->address, ':')
            ? "[{$this->address}]"
            : $this->address;

        return "{$this->host}:{$this->port}:{$address}";
    }
}
