<?php

namespace App\Application\DTOs;

final readonly class SupplierData
{
    /** @param array<string, mixed>|null $address */
    public function __construct(
        public string $name,
        public ?string $contactName = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?array $address = null,
    ) {}
}
