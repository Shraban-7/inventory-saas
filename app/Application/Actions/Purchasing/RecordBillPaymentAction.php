<?php

namespace App\Application\Actions\Purchasing;

use App\Application\Actions\Accounting\CreateJournalEntryAction;
use App\Application\DTOs\BillPaymentData;
use App\Application\DTOs\JournalEntryData;
use App\Application\Services\SystemChartOfAccountResolver;
use App\Domain\Entities\BillStatus;
use App\Domain\Entities\Money;
use App\Domain\Events\BillPaymentRecorded;
use App\Domain\Exceptions\InvalidBillStateException;
use App\Domain\Exceptions\InvalidPurchasingDataException;
use App\Infrastructure\Models\AuditLog;
use App\Infrastructure\Models\Bill;
use App\Infrastructure\Models\BillPayment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use OverflowException;

final readonly class RecordBillPaymentAction
{
    public function __construct(
        private SystemChartOfAccountResolver $accounts,
        private CreateJournalEntryAction $journals,
    ) {}

    public function handle(int $billId, BillPaymentData $data, int $actingUserId): BillPayment
    {
        return DB::transaction(function () use ($billId, $data, $actingUserId): BillPayment {
            $bill = Bill::query()->lockForUpdate()->findOrFail($billId);
            $status = BillStatus::from((string) $bill->getRawOriginal('status'));

            if (! in_array($status, [BillStatus::Approved, BillStatus::PartiallyPaid], true)) {
                throw new InvalidBillStateException('Only an approved or partially paid bill can receive a payment.');
            }

            try {
                $amount = Money::fromDecimal($data->amount);
                $balance = Money::fromDecimal((string) $bill->balance_due);
            } catch (InvalidArgumentException|OverflowException $exception) {
                throw new InvalidPurchasingDataException($exception->getMessage(), previous: $exception);
            }

            if (! $amount->isPositive()) {
                throw new InvalidPurchasingDataException('Bill payment amount must be greater than zero.');
            }

            if ($amount->compare($balance) > 0) {
                throw new InvalidPurchasingDataException('Bill payment amount cannot exceed the balance due.');
            }

            $remaining = $balance->subtract($amount);
            $payment = BillPayment::query()->create([
                'branch_id' => $bill->branch_id,
                'supplier_id' => $bill->supplier_id,
                'bill_id' => $bill->getKey(),
                'amount' => $amount->toDecimal(),
                'payment_method' => $data->paymentMethod,
                'payment_date' => $data->paymentDate->format('Y-m-d'),
                'reference' => $data->reference,
            ]);
            $this->journals->handle(new JournalEntryData(
                $bill->branch_id,
                'JRN-BPAY-'.$payment->getKey(),
                'bill_payment',
                (int) $payment->getKey(),
                $data->paymentDate,
                'Record bill payment '.$payment->getKey(),
                PurchasingJournalLineFactory::payment($amount, $this->accounts->ids(['1100', '2000'])),
            ));
            $bill->forceFill([
                'balance_due' => $remaining->toDecimal(),
                'status' => $remaining->isZero() ? BillStatus::Paid : BillStatus::PartiallyPaid,
            ])->save();
            AuditLog::query()->create([
                'user_id' => $actingUserId,
                'action' => 'BILL_PAYMENT_RECORDED',
                'entity_type' => 'bill_payment',
                'entity_id' => $payment->getKey(),
                'new_values' => [
                    'bill_id' => $bill->getKey(),
                    'amount' => $amount->toDecimal(),
                    'payment_method' => $data->paymentMethod->value,
                    'balance_due' => $remaining->toDecimal(),
                ],
            ]);

            $paymentId = (int) $payment->getKey();
            $recordedBillId = (int) $bill->getKey();
            DB::afterCommit(static fn () => event(new BillPaymentRecorded($recordedBillId, $paymentId)));

            return $payment->load('journalEntries.lines');
        });
    }
}
