<?php

namespace App\Domain\Entities;

use InvalidArgumentException;

final readonly class Quantity
{
    private function __construct(private int $units) {}

    public static function from(string|int|float $value): self
    {
        $value = is_float($value) ? number_format($value, 4, '.', '') : (string) $value;

        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,4}))?$/', $value, $matches)) {
            throw new InvalidArgumentException('Quantity must have at most four decimal places.');
        }

        $units = ((int) $matches[2] * 10000) + (int) str_pad($matches[3] ?? '', 4, '0');

        return new self($matches[1] === '-' ? -$units : $units);
    }

    public function add(self $quantity): self
    {
        return new self($this->units + $quantity->units);
    }

    public function subtract(self $quantity): self
    {
        return new self($this->units - $quantity->units);
    }

    public function isPositive(): bool
    {
        return $this->units > 0;
    }

    public function isNegative(): bool
    {
        return $this->units < 0;
    }

    public function equals(self $quantity): bool
    {
        return $this->units === $quantity->units;
    }

    public function compare(self $quantity): int
    {
        return $this->units <=> $quantity->units;
    }

    public function isZero(): bool
    {
        return $this->units === 0;
    }

    public function units(): int
    {
        return $this->units;
    }

    public function abs(): self
    {
        return new self(abs($this->units));
    }

    public function toDecimal(): string
    {
        $sign = $this->units < 0 ? '-' : '';
        $absolute = abs($this->units);

        return sprintf('%s%d.%04d', $sign, intdiv($absolute, 10000), $absolute % 10000);
    }
}
