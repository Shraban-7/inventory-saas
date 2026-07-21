<?php

namespace App\Application\Actions\Sales;

use App\Domain\Exceptions\InvalidSalesDataException;
use Illuminate\Database\Eloquent\Model;

final class DecimalSnapshot
{
    public static function from(Model $model, string $attribute): string
    {
        $value = $model->getAttribute($attribute);

        if (! is_string($value)) {
            throw new InvalidSalesDataException("The {$attribute} decimal snapshot is invalid.");
        }

        return $value;
    }
}
