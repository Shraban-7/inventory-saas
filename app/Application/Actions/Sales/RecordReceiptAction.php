<?php

namespace App\Application\Actions\Sales;

use App\Application\Actions\Accounting\CreateJournalEntryAction;
use App\Application\DTOs\JournalEntryData;
use App\Application\DTOs\JournalEntryLineData;
use App\Application\DTOs\ReceiptData;
use App\Application\Services\SystemChartOfAccountResolver;
use App\Domain\Entities\InvoiceStatus;
use App\Domain\Entities\Money;
use App\Domain\Events\ReceiptRecorded;
use App\Domain\Exceptions\InvalidInvoiceStateException;
use App\Domain\Exceptions\InvalidSalesDataException;
use App\Infrastructure\Models\AuditLog;
use App\Infrastructure\Models\Invoice;
use App\Infrastructure\Models\Receipt;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use OverflowException;

final readonly class RecordReceiptAction
{
    public function __construct(
        private SystemChartOfAccountResolver $accounts,
        private CreateJournalEntryAction $journals,
    ) {}

    public function handle(ReceiptData $data, int $actingUserId): Receipt
    {
        return DB::transaction(function () use ($data, $actingUserId): Receipt {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($data->invoiceId);
            $rawStatus = $invoice->getRawOriginal('status');

            if (! is_string($rawStatus)
                || ! in_array(InvoiceStatus::from($rawStatus), [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid], true)) {
                throw new InvalidInvoiceStateException('Receipts can only be recorded against issued or partially paid invoices.');
            }

            if ($data->reference !== null && (trim($data->reference) === '' || mb_strlen($data->reference) > 255)) {
                throw new InvalidSalesDataException('Receipt reference must be non-empty and at most 255 characters.');
            }

            try {
                $amount = Money::fromDecimal($data->amount);
                $balance = Money::fromDecimal(DecimalSnapshot::from($invoice, 'balance_due'));
            } catch (InvalidArgumentException|OverflowException $exception) {
                throw new InvalidSalesDataException($exception->getMessage(), previous: $exception);
            }

            if (! $amount->isPositive()) {
                throw new InvalidSalesDataException('Receipt amount must be positive.');
            }

            if ($amount->compare($balance) > 0) {
                throw new InvalidSalesDataException('Receipt amount cannot exceed the invoice balance.');
            }

            $remaining = $balance->subtract($amount);
            $receipt = Receipt::query()->create([
                'branch_id' => $invoice->branch_id,
                'customer_id' => $invoice->customer_id,
                'invoice_id' => $invoice->getKey(),
                'amount' => $amount->toDecimal(),
                'payment_method' => $data->paymentMethod,
                'payment_date' => $data->paymentDate->format('Y-m-d'),
                'reference' => $data->reference,
            ]);

            $invoice->forceFill([
                'balance_due' => $remaining->toDecimal(),
                'status' => $remaining->isZero() ? InvoiceStatus::Paid : InvoiceStatus::PartiallyPaid,
            ])->save();

            $this->journals->handle(new JournalEntryData(
                $invoice->branch_id,
                'JRN-RCT-'.$receipt->getKey(),
                'invoice',
                $invoice->getKey(),
                $data->paymentDate,
                'Receipt for '.$invoice->invoice_number,
                [
                    new JournalEntryLineData($this->accounts->id('1100'), $amount->toDecimal(), '0.00', 'Cash/Bank'),
                    new JournalEntryLineData($this->accounts->id('1200'), '0.00', $amount->toDecimal(), 'Accounts receivable'),
                ],
            ));

            AuditLog::query()->create([
                'user_id' => $actingUserId,
                'action' => 'RECEIPT_RECORDED',
                'entity_type' => 'invoice',
                'entity_id' => $invoice->getKey(),
                'old_values' => ['balance_due' => $balance->toDecimal(), 'status' => $rawStatus],
                'new_values' => [
                    'receipt_id' => $receipt->getKey(),
                    'amount' => $amount->toDecimal(),
                    'balance_due' => $remaining->toDecimal(),
                    'status' => $remaining->isZero() ? InvoiceStatus::Paid->value : InvoiceStatus::PartiallyPaid->value,
                    'payment_method' => $data->paymentMethod->value,
                    'payment_date' => $data->paymentDate->format('Y-m-d'),
                ],
            ]);

            $receiptId = $receipt->getKey();
            DB::afterCommit(static fn () => event(new ReceiptRecorded($receiptId)));

            return $receipt;
        });
    }
}
