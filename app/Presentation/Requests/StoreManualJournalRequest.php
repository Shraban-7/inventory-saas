<?php

namespace App\Presentation\Requests;

use App\Application\DTOs\JournalEntryLineData;
use App\Application\DTOs\ManualJournalData;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

class StoreManualJournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantExists = static fn (string $table) => Rule::exists($table, 'id')
            ->where('tenant_id', current_tenant_id());

        return [
            'branch_id' => ['required', 'integer', $tenantExists('branches')],
            'posted_at' => ['required', 'date_format:Y-m-d'],
            'description' => ['required', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.coa_id' => ['required', 'integer', $tenantExists('chart_of_accounts')],
            'lines.*.debit' => ['required', 'numeric', 'decimal:0,2', 'gte:0'],
            'lines.*.credit' => ['required', 'numeric', 'decimal:0,2', 'gte:0'],
            'lines.*.description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function journalData(): ManualJournalData
    {
        $data = $this->validated();
        $validatedLines = $data['lines'] ?? null;

        if (! is_array($validatedLines)) {
            throw new LogicException('Validated journal lines must be an array.');
        }

        $lines = [];
        foreach ($validatedLines as $line) {
            if (! is_array($line)) {
                throw new LogicException('Each validated journal line must be an array.');
            }

            $lines[] = new JournalEntryLineData(
                (int) $line['coa_id'],
                (string) $line['debit'],
                (string) $line['credit'],
                isset($line['description']) ? (string) $line['description'] : null,
            );
        }

        return new ManualJournalData(
            (int) $data['branch_id'],
            new DateTimeImmutable((string) $data['posted_at']),
            (string) $data['description'],
            $lines,
        );
    }
}
