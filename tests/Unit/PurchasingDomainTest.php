<?php

use App\Domain\Entities\LotBalance;
use App\Domain\Entities\Money;
use App\Domain\Entities\PricedBillItem;
use App\Domain\Entities\PurchasingDocumentType;
use App\Domain\Entities\Quantity;
use App\Domain\Exceptions\InsufficientStockException;
use App\Domain\Repositories\PurchasingSequenceRepository;
use App\Domain\Services\BillDomainService;
use App\Domain\Services\FifoCostCalculator;
use App\Domain\Services\PurchasingNumberService;

function purchasingLot(int $id, string $quantity, string $cost, string $receivedAt): LotBalance
{
    return new LotBalance(
        $id,
        10,
        20,
        Quantity::from($quantity),
        $cost,
        new DateTimeImmutable($receivedAt),
    );
}

it('allocates FIFO across multiple lots with exact weighted cost', function () {
    $result = (new FifoCostCalculator)->oldestFirst([
        purchasingLot(3, '4.0000', '2.5000', '2026-07-03 10:00:00'),
        purchasingLot(1, '2.0000', '1.2500', '2026-07-01 10:00:00'),
        purchasingLot(2, '3.0000', '2.0000', '2026-07-02 10:00:00'),
    ], '6.0000');

    expect(array_column($result->allocations, 'lotId'))->toBe([1, 2, 3])
        ->and(array_map(fn ($allocation) => $allocation->quantity->toDecimal(), $result->allocations))
        ->toBe(['2.0000', '3.0000', '1.0000'])
        ->and($result->totalCost->toDecimal())->toBe('11.00')
        ->and($result->weightedUnitCost)->toBe('1.8333');
});

it('uses lot id as the deterministic received timestamp tie breaker', function () {
    $calculator = new FifoCostCalculator;
    $lots = [
        purchasingLot(9, '1.0000', '9.0000', '2026-07-01 10:00:00.123456'),
        purchasingLot(4, '1.0000', '4.0000', '2026-07-01 10:00:00.123456'),
    ];

    expect($calculator->oldestFirst($lots, '1.0000')->allocations[0]->lotId)->toBe(4)
        ->and($calculator->newestFirst($lots, '1.0000')->allocations[0]->lotId)->toBe(9);
});

it('allocates supplier returns newest first', function () {
    $result = (new FifoCostCalculator)->newestFirst([
        purchasingLot(1, '3.0000', '1.0000', '2026-07-01'),
        purchasingLot(2, '3.0000', '3.0000', '2026-07-02'),
    ], '4.0000');

    expect(array_column($result->allocations, 'lotId'))->toBe([2, 1])
        ->and($result->totalCost->toDecimal())->toBe('10.00')
        ->and($result->weightedUnitCost)->toBe('2.5000');
});

it('rejects FIFO deductions exceeding available lots', function () {
    (new FifoCostCalculator)->oldestFirst([
        purchasingLot(1, '0.2500', '4.0000', '2026-07-01'),
    ], '0.2501');
})->throws(InsufficientStockException::class);

it('formats purchasing numbers and delegates type and year to its repository', function () {
    $repository = new class implements PurchasingSequenceRepository
    {
        /** @var list<array{PurchasingDocumentType, int}> */
        public array $calls = [];

        public function next(PurchasingDocumentType $documentType, int $year): int
        {
            $this->calls[] = [$documentType, $year];

            return count($this->calls);
        }
    };
    $service = new PurchasingNumberService($repository);

    expect($service->next(PurchasingDocumentType::PurchaseOrder, 2026))->toBe('PO-2026-00001')
        ->and($service->next(PurchasingDocumentType::GoodsReceiptNote, 2027))->toBe('GRN-2027-00002')
        ->and($repository->calls)->toBe([
            [PurchasingDocumentType::PurchaseOrder, 2026],
            [PurchasingDocumentType::GoodsReceiptNote, 2027],
        ]);
});

it('calculates exact bill gross tax total and line snapshots', function () {
    $totals = (new BillDomainService)->calculate([
        new PricedBillItem(1, '1.2500', '10.0050', 8, '7.5000', 21),
        new PricedBillItem(2, '2.0000', '3.3333'),
    ]);

    expect($totals->gross->toDecimal())->toBe('19.18')
        ->and($totals->tax->toDecimal())->toBe('0.94')
        ->and($totals->total->toDecimal())->toBe('20.12')
        ->and($totals->lines[0]->gross->toDecimal())->toBe('12.51')
        ->and($totals->lines[0]->tax->toDecimal())->toBe('0.94')
        ->and($totals->lines[0]->total->toDecimal())->toBe('13.45');
});

it('validates bill item invariants', function (array $items, string $message) {
    expect(fn () => (new BillDomainService)->calculate($items))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'empty' => [[], 'at least one'],
    'duplicate variant' => [[new PricedBillItem(1, '1', '1'), new PricedBillItem(1, '1', '1')], 'duplicate'],
    'zero quantity' => [[new PricedBillItem(1, '0', '1')], 'quantity'],
    'zero cost' => [[new PricedBillItem(1, '1', '0')], 'unit cost'],
    'partial tax snapshot' => [[new PricedBillItem(1, '1', '1', 2, null, null)], 'Tax snapshots'],
]);

it('calculates large unit prices without native integer multiplication overflow', function () {
    expect(Money::fromDecimal('9999999999999.99')->unitPriceForQuantity(Quantity::from('99999999999.9999')))
        ->toBe('100.0000')
        ->and(Money::fromDecimal('9999999.99')->unitPriceForQuantity(Quantity::from('0.0001')))
        ->toBe('99999999900.0000');
});
