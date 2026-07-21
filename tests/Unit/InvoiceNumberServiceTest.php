<?php

use App\Domain\Repositories\InvoiceSequenceRepository;
use App\Domain\Services\InvoiceNumberService;

final class FakeInvoiceSequenceRepository implements InvoiceSequenceRepository
{
    /** @param ArrayObject<string, int> $sequences */
    public function __construct(
        private readonly int $tenantId,
        private readonly ArrayObject $sequences,
    ) {}

    public function next(int $year): int
    {
        $key = $this->tenantId.':'.$year;

        $next = ($this->sequences[$key] ?? 0) + 1;
        $this->sequences[$key] = $next;

        return $next;
    }
}

it('formats invoice numbers and resets each year', function () {
    $sequences = new ArrayObject;
    $service = new InvoiceNumberService(new FakeInvoiceSequenceRepository(1, $sequences));

    expect($service->next(2026))->toBe('INV-2026-00001')
        ->and($service->next(2026))->toBe('INV-2026-00002')
        ->and($service->next(2027))->toBe('INV-2027-00001');
});

it('keeps tenant sequences independent', function () {
    $sequences = new ArrayObject;
    $tenantA = new InvoiceNumberService(new FakeInvoiceSequenceRepository(10, $sequences));
    $tenantB = new InvoiceNumberService(new FakeInvoiceSequenceRepository(20, $sequences));

    expect($tenantA->next(2026))->toBe('INV-2026-00001')
        ->and($tenantA->next(2026))->toBe('INV-2026-00002')
        ->and($tenantB->next(2026))->toBe('INV-2026-00001');
});

it('rejects invalid invoice years and repository sequences', function () {
    $sequences = new ArrayObject;

    expect(fn () => (new InvoiceNumberService(new FakeInvoiceSequenceRepository(1, $sequences)))->next(0))
        ->toThrow(InvalidArgumentException::class);

    $repository = new class implements InvoiceSequenceRepository
    {
        public function next(int $year): int
        {
            return 0;
        }
    };

    expect(fn () => (new InvoiceNumberService($repository))->next(2026))
        ->toThrow(InvalidArgumentException::class, 'positive');
});
