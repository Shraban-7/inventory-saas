<?php

namespace App\Application\Actions\Purchasing;

use App\Application\Actions\Accounting\CreateJournalEntryAction;
use App\Application\DTOs\JournalEntryData;
use App\Application\Services\SystemChartOfAccountResolver;
use App\Domain\Entities\BillStatus;
use App\Domain\Entities\PricedBillItem;
use App\Domain\Events\BillApproved;
use App\Domain\Exceptions\InvalidBillStateException;
use App\Domain\Exceptions\InvalidPurchasingDataException;
use App\Domain\Services\BillDomainService;
use App\Infrastructure\Models\AuditLog;
use App\Infrastructure\Models\Bill;
use App\Infrastructure\Models\BillItem;
use App\Infrastructure\Models\Tax;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use OverflowException;

final readonly class ApproveBillAction
{
    public function __construct(
        private BillDomainService $domain,
        private SystemChartOfAccountResolver $accounts,
        private CreateJournalEntryAction $journals,
    ) {}

    public function handle(int $billId, int $actingUserId): Bill
    {
        return DB::transaction(function () use ($billId, $actingUserId): Bill {
            $bill = Bill::query()->lockForUpdate()->findOrFail($billId);
            $status = BillStatus::from((string) $bill->getRawOriginal('status'));

            if ($status !== BillStatus::Draft) {
                throw new InvalidBillStateException('Only a draft bill can be approved.');
            }

            $items = BillItem::query()->where('bill_id', $bill->getKey())->orderBy('id')->lockForUpdate()->get();
            $taxIds = $items->pluck('tax_id')->filter()->unique()->values()->all();
            $taxes = Tax::query()->whereKey($taxIds)->orderBy('id')->lockForUpdate()->get(['id', 'coa_id'])->keyBy('id');
            $priced = [];

            foreach ($items as $item) {
                $tax = $item->tax_id === null ? null : $taxes->get($item->tax_id);
                $priced[] = new PricedBillItem(
                    $item->product_variant_id,
                    (string) $item->quantity,
                    (string) $item->unit_cost,
                    $item->tax_id,
                    $item->tax_id === null ? null : (string) $item->tax_rate_snapshot,
                    $tax?->coa_id,
                );
            }

            try {
                $totals = $this->domain->calculate($priced);
            } catch (InvalidArgumentException|OverflowException $exception) {
                throw new InvalidPurchasingDataException($exception->getMessage(), previous: $exception);
            }

            if ($totals->total->toDecimal() !== (string) $bill->total_amount
                || $totals->tax->toDecimal() !== (string) $bill->tax_amount) {
                throw new InvalidPurchasingDataException('Bill snapshots do not match the immutable bill totals.');
            }

            $systemCodes = ['2000', $bill->grn_id === null ? '6000' : '2050'];
            $this->journals->handle(new JournalEntryData(
                $bill->branch_id,
                'JRN-BILL-'.$bill->getKey(),
                'bill',
                (int) $bill->getKey(),
                new DateTimeImmutable((string) $bill->getRawOriginal('bill_date')),
                'Approve bill '.$bill->bill_number,
                PurchasingJournalLineFactory::bill($totals, $this->accounts->ids($systemCodes), $bill->grn_id !== null),
            ));
            $bill->forceFill(['status' => BillStatus::Approved])->save();
            AuditLog::query()->create([
                'user_id' => $actingUserId,
                'action' => 'BILL_APPROVED',
                'entity_type' => 'bill',
                'entity_id' => $bill->getKey(),
                'old_values' => ['status' => BillStatus::Draft->value],
                'new_values' => [
                    'status' => BillStatus::Approved->value,
                    'gross' => $totals->gross->toDecimal(),
                    'tax' => $totals->tax->toDecimal(),
                    'total' => $totals->total->toDecimal(),
                ],
            ]);

            $id = (int) $bill->getKey();
            DB::afterCommit(static fn () => event(new BillApproved($id)));

            return $bill->load(['items', 'journalEntries.lines']);
        });
    }
}
