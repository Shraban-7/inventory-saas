<?php

namespace App\Domain\Entities;

use InvalidArgumentException;
use OverflowException;

final readonly class Money
{
    private const MAX_CENTS = 999999999999999;

    private function __construct(private int $cents) {}

    public static function fromDecimal(string $value): self
    {
        [$negative, $digits] = self::parseDecimal($value, 2, 15, 'Money');
        $cents = (int) $digits;

        return new self($negative ? -$cents : $cents);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public static function quantityTimesPrice(string $quantity, string $unitPrice): self
    {
        [$quantityNegative, $quantityUnits] = self::parseDecimal($quantity, 4, 15, 'Quantity');
        [$priceNegative, $priceUnits] = self::parseDecimal($unitPrice, 4, 15, 'Unit price');

        if ($quantityNegative || $priceNegative) {
            throw new InvalidArgumentException('Quantity and unit price cannot be negative.');
        }

        return self::fromRoundedProduct($quantityUnits, $priceUnits, 6);
    }

    public function percentage(string $rate): self
    {
        [$rateNegative, $rateUnits] = self::parseDecimal($rate, 4, 7, 'Tax rate');

        if ($rateNegative) {
            throw new InvalidArgumentException('Tax rate cannot be negative.');
        }

        $absolute = self::fromRoundedProduct((string) abs($this->cents), $rateUnits, 6);

        return $this->cents < 0 ? new self(-$absolute->cents) : $absolute;
    }

    public function add(self $money): self
    {
        if (($money->cents > 0 && $this->cents > self::MAX_CENTS - $money->cents)
            || ($money->cents < 0 && $this->cents < -self::MAX_CENTS - $money->cents)) {
            throw new OverflowException('Money exceeds decimal(15,2) bounds.');
        }

        return new self($this->cents + $money->cents);
    }

    public function subtract(self $money): self
    {
        return $this->add(new self(-$money->cents));
    }

    public function compare(self $money): int
    {
        return $this->cents <=> $money->cents;
    }

    public function isPositive(): bool
    {
        return $this->cents > 0;
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function toDecimal(): string
    {
        $sign = $this->cents < 0 ? '-' : '';
        $absolute = abs($this->cents);

        return sprintf('%s%d.%02d', $sign, intdiv($absolute, 100), $absolute % 100);
    }

    public function unitPriceForQuantity(Quantity $quantity): string
    {
        if (! $quantity->isPositive() || $this->isNegative()) {
            throw new InvalidArgumentException('A positive quantity and non-negative money are required.');
        }

        $units = $quantity->units();
        [$scaledPrice, $remainder] = self::divideDigitsByInt(
            (string) $this->cents.'000000',
            $units,
        );

        if ($remainder * 2 >= $units) {
            $scaledPrice = self::incrementDigits($scaledPrice);
        }

        $scaledPrice = ltrim($scaledPrice, '0');
        $scaledPrice = $scaledPrice === '' ? '0' : $scaledPrice;

        if (mb_strlen($scaledPrice) > 15) {
            throw new OverflowException('Calculated unit price exceeds decimal(15,4) bounds.');
        }

        $padded = str_pad($scaledPrice, 5, '0', STR_PAD_LEFT);

        $whole = ltrim(mb_substr($padded, 0, -4), '0');

        return ($whole === '' ? '0' : $whole).'.'.mb_substr($padded, -4);
    }

    /**
     * @return array{bool, non-empty-string}
     */
    private static function parseDecimal(string $value, int $scale, int $precision, string $label): array
    {
        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,'.$scale.'}))?$/', $value, $matches)) {
            throw new InvalidArgumentException("{$label} must be a decimal string with at most {$scale} decimal places.");
        }

        $integer = ltrim($matches[2], '0');
        $integer = $integer === '' ? '0' : $integer;
        $fraction = str_pad($matches[3] ?? '', $scale, '0');
        $digits = ltrim($integer.$fraction, '0');
        $digits = $digits === '' ? '0' : $digits;

        if (mb_strlen($integer) + $scale > $precision || mb_strlen($digits) > $precision) {
            throw new OverflowException("{$label} exceeds decimal({$precision},{$scale}) bounds.");
        }

        return [$matches[1] === '-' && $digits !== '0', $digits];
    }

    private static function fromRoundedProduct(string $left, string $right, int $divisorDigits): self
    {
        $product = self::multiplyDigits($left, $right);
        $padded = str_pad($product, $divisorDigits + 1, '0', STR_PAD_LEFT);
        $whole = mb_substr($padded, 0, -$divisorDigits);
        $remainder = mb_substr($padded, -$divisorDigits);

        if ($remainder[0] >= '5') {
            $whole = self::incrementDigits($whole);
        }

        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;

        if (mb_strlen($whole) > 15 || (int) $whole > self::MAX_CENTS) {
            throw new OverflowException('Calculated money exceeds decimal(15,2) bounds.');
        }

        return new self((int) $whole);
    }

    private static function multiplyDigits(string $left, string $right): string
    {
        $result = array_fill(0, mb_strlen($left) + mb_strlen($right), 0);

        for ($i = mb_strlen($left) - 1; $i >= 0; $i--) {
            for ($j = mb_strlen($right) - 1; $j >= 0; $j--) {
                $position = $i + $j + 1;
                $result[$position] += ((int) $left[$i]) * ((int) $right[$j]);
            }
        }

        for ($i = count($result) - 1; $i > 0; $i--) {
            $result[$i - 1] += intdiv($result[$i], 10);
            $result[$i] %= 10;
        }

        $digits = ltrim(implode('', $result), '0');

        return $digits === '' ? '0' : $digits;
    }

    private static function incrementDigits(string $digits): string
    {
        $characters = str_split($digits);

        for ($index = count($characters) - 1; $index >= 0; $index--) {
            if ($characters[$index] !== '9') {
                $characters[$index] = (string) (((int) $characters[$index]) + 1);

                return implode('', $characters);
            }

            $characters[$index] = '0';
        }

        return '1'.implode('', $characters);
    }

    /** @return array{non-empty-string, int} */
    private static function divideDigitsByInt(string $digits, int $divisor): array
    {
        if ($divisor <= 0) {
            throw new InvalidArgumentException('The divisor must be positive.');
        }

        $quotient = '';
        $remainder = 0;

        foreach (str_split($digits) as $digit) {
            $value = ($remainder * 10) + (int) $digit;
            $quotient .= (string) intdiv($value, $divisor);
            $remainder = $value % $divisor;
        }

        $quotient = ltrim($quotient, '0');

        return [$quotient === '' ? '0' : $quotient, $remainder];
    }
}
