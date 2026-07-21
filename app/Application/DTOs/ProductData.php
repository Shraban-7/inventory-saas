<?php

namespace App\Application\DTOs;

use App\Domain\Entities\CostingMethod;

final readonly class ProductData
{
    /** @param list<array{sku: string, barcode?: string|null, cost_price: string, sale_price: string, reorder_point?: int, attribute_value_ids?: list<int>}> $variants */
    public function __construct(
        public int $categoryId,
        public string $name,
        public ?string $description,
        public CostingMethod $costingMethod,
        public array $variants,
    ) {}
}
