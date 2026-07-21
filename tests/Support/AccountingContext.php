<?php

namespace Tests\Support;

use App\Application\Actions\Accounting\CreateJournalEntryAction;
use App\Application\DTOs\JournalEntryData;
use App\Application\DTOs\JournalEntryLineData;
use App\Infrastructure\Models\ChartOfAccount;
use App\Infrastructure\Models\JournalEntry;
use App\Infrastructure\Models\Role;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final readonly class AccountingContext
{
    public function __construct(public PurchasingContext $purchasing) {}

    public static function create(string $role = 'Admin'): self
    {
        return new self(PurchasingContext::create($role));
    }

    public function account(string $code): ChartOfAccount
    {
        return ChartOfAccount::query()->where('code', $code)->firstOrFail();
    }

    public function useScopedRole(string $role, int $branchId): void
    {
        $user = $this->purchasing->sales->user;
        $user->roles()->detach();
        $user->roles()->attach(
            Role::query()->where('name', $role)->valueOrFail('id'),
            ['branch_id' => $branchId],
        );
        $user->unsetRelation('roles');
    }

    public function addRole(string $role, ?int $branchId = null): void
    {
        $user = $this->purchasing->sales->user;
        $user->roles()->attach(
            Role::query()->where('name', $role)->valueOrFail('id'),
            ['branch_id' => $branchId],
        );
        $user->unsetRelation('roles');
    }

    /**
     * @param  list<JournalEntryLineData>|null  $lines
     * @return array<string, mixed>
     */
    public function manualJournalPayload(?array $lines = null, ?int $branchId = null): array
    {
        return [
            'branch_id' => $branchId ?? $this->purchasing->sales->branch->getKey(),
            'posted_at' => '2026-07-22',
            'description' => 'Month-end adjustment',
            'lines' => array_map(
                fn (JournalEntryLineData $line): array => [
                    'coa_id' => $line->coaId,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                    'description' => $line->description,
                ],
                $lines ?? [
                    new JournalEntryLineData($this->account('1100')->getKey(), '25.10', '0.00', 'Cash'),
                    new JournalEntryLineData($this->account('4000')->getKey(), '0.00', '25.10', 'Revenue'),
                ],
            ),
        ];
    }

    /**
     * @param  list<JournalEntryLineData>|null  $lines
     */
    public function createJournal(
        string $number,
        string $date = '2026-07-22',
        ?int $branchId = null,
        ?array $lines = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): JournalEntry {
        return DB::transaction(fn (): JournalEntry => app(CreateJournalEntryAction::class)->handle(
            new JournalEntryData(
                $branchId ?? $this->purchasing->sales->branch->getKey(),
                $number,
                $referenceType,
                $referenceId,
                new DateTimeImmutable($date),
                'Accounting test journal',
                $lines ?? [
                    new JournalEntryLineData($this->account('1100')->getKey(), '10.00', '0.00', null),
                    new JournalEntryLineData($this->account('4000')->getKey(), '0.00', '10.00', null),
                ],
            ),
        ));
    }
}
