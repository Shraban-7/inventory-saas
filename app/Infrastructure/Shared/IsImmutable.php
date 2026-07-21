<?php

namespace App\Infrastructure\Shared;

use App\Domain\Exceptions\ImmutableRecordException;

trait IsImmutable
{
    public static function bootIsImmutable(): void
    {
        static::updating(fn () => throw new ImmutableRecordException);
        static::deleting(fn () => throw new ImmutableRecordException);
    }

    /** @param array<string, mixed> $options */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new ImmutableRecordException;
        }

        return parent::save($options);
    }
}
