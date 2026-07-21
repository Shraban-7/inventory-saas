<?php

use App\Domain\Entities\PricedInvoiceItem;
use App\Domain\Services\InvoiceDomainService;

it('calculates scale four snapshots with half-up money rounding', function () {
    $item = new PricedInvoiceItem(11, '1.2345', '10.0050', '4.3210', 7, '7.2500', 21);

    $totals = (new InvoiceDomainService)->calculate([$item]);
    $line = $totals->lines[0];

    expect($totals->subtotal->toDecimal())->toBe('12.35')
        ->and($totals->tax->toDecimal())->toBe('0.90')
        ->and($totals->total->toDecimal())->toBe('13.25')
        ->and($totals->cost->toDecimal())->toBe('5.33')
        ->and($line->quantity)->toBe('1.2345')
        ->and($line->unitPrice)->toBe('10.0050')
        ->and($line->costPrice)->toBe('4.3210')
        ->and($line->taxRate)->toBe('7.2500');
});

it('rounds exact half cents away from zero', function () {
    $totals = (new InvoiceDomainService)->calculate([
        new PricedInvoiceItem(1, '1.0000', '0.0050', '0.0050', null, null, null),
    ]);

    expect($totals->subtotal->toDecimal())->toBe('0.01')
        ->and($totals->cost->toDecimal())->toBe('0.01');
});

it('rejects empty and nonpositive invoice lines', function (array $items) {
    expect(fn () => (new InvoiceDomainService)->calculate($items))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'empty invoice' => [[]],
    'zero quantity' => [[new PricedInvoiceItem(1, '0.0000', '1.0000', '1.0000', null, null, null)]],
    'negative quantity' => [[new PricedInvoiceItem(1, '-1.0000', '1.0000', '1.0000', null, null, null)]],
]);

it('requires complete immutable tax snapshots', function () {
    expect(fn () => (new InvoiceDomainService)->calculate([
        new PricedInvoiceItem(1, '1.0000', '1.0000', '1.0000', 4, null, null),
    ]))->toThrow(InvalidArgumentException::class, 'Tax snapshots');
});
