<?php

namespace App\Infrastructure\Models;

use App\Infrastructure\Shared\HasTenantScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'year', 'sequence'])]
class InvoiceSequence extends Model
{
    use HasTenantScope;

    /** @var array<string, mixed> */
    protected $attributes = ['sequence' => 0];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['year' => 'integer', 'sequence' => 'integer'];
    }
}
