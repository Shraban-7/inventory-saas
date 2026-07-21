<?php

namespace App\Infrastructure\Models;

use App\Domain\Entities\PurchasingDocumentType;
use App\Infrastructure\Shared\HasTenantScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'document_type', 'year', 'sequence'])]
class PurchasingSequence extends Model
{
    use HasTenantScope;

    /** @var array<string, mixed> */
    protected $attributes = ['sequence' => 0];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'document_type' => PurchasingDocumentType::class,
            'year' => 'integer',
            'sequence' => 'integer',
        ];
    }
}
