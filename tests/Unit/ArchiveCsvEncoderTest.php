<?php

use App\Application\Services\Archive\ArchiveCsvEncoder;

it('normalizes decimals with exact fixed-point string math', function () {
    $encoder = new ArchiveCsvEncoder;

    expect($encoder->encodeDecimal('9007199254740993.1234', 4))->toBe('9007199254740993.1234')
        ->and($encoder->encodeDecimal('-42.5', 4))->toBe('-42.5000')
        ->and($encoder->encodeDecimal('10.00', 2))->toBe('10.00')
        ->and($encoder->encodeDecimal('1.2500', 4))->toBe('1.2500')
        ->and($encoder->encodeDecimal(15, 2))->toBe('15.00')
        ->and($encoder->encodeDecimal('-0.0000', 4))->toBe('0.0000')
        ->and($encoder->encodeDecimal(null, 2))->toBeNull();
});

it('rejects unsafe or oversized decimal encodings', function (mixed $value, int $scale) {
    $encoder = new ArchiveCsvEncoder;

    expect(fn () => $encoder->encodeDecimal($value, $scale))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'float input' => [1.25, 2],
    'scientific notation' => ['1.2e3', 2],
    'non-zero beyond scale' => ['1.2345', 2],
    'malformed text' => ['12.3.4', 2],
    'empty string' => ['', 2],
    'alpha junk' => ['12.3a', 2],
]);

it('allows trailing zeros beyond scale when they are zero-only', function () {
    $encoder = new ArchiveCsvEncoder;

    expect($encoder->encodeDecimal('1.2300', 2))->toBe('1.23');
});
