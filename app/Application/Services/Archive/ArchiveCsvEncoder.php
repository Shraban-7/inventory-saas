<?php

namespace App\Application\Services\Archive;

use App\Domain\Entities\ArchiveDataset;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use JsonException;

final class ArchiveCsvEncoder
{
    /**
     * @param  list<string|null>  $fields
     */
    public function encodeRow(array $fields): string
    {
        $encoded = array_map(fn (?string $field): string => $this->encodeField($field), $fields);

        return implode(',', $encoded)."\r\n";
    }

    public function encodeField(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $needsQuotes = str_contains($value, ',')
            || str_contains($value, '"')
            || str_contains($value, "\r")
            || str_contains($value, "\n");

        if ($needsQuotes) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }

    public function encodeDecimal(mixed $value, int $scale): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($scale < 0) {
            throw new \InvalidArgumentException('Decimal scale must be non-negative.');
        }

        if (is_float($value)) {
            throw new \InvalidArgumentException('Float values cannot be encoded as exact decimals.');
        }

        if (is_int($value)) {
            $raw = (string) $value;
        } elseif (is_string($value) || $value instanceof \Stringable) {
            $raw = trim((string) $value);
        } else {
            throw new \InvalidArgumentException('Decimal value must be a string or integer.');
        }

        if ($raw === '' || preg_match('/[eE]/', $raw) === 1) {
            throw new \InvalidArgumentException('Malformed decimal value.');
        }

        if (preg_match('/^([+-])?(\d+)(?:\.(\d+))?$/', $raw, $matches) !== 1) {
            throw new \InvalidArgumentException('Malformed decimal value.');
        }

        $sign = $matches[1] === '-' ? '-' : '';
        $integer = ltrim($matches[2], '0');
        if ($integer === '') {
            $integer = '0';
        }

        $fraction = $matches[3] ?? '';

        if (strlen($fraction) > $scale) {
            $overflow = substr($fraction, $scale);
            if (ltrim($overflow, '0') !== '') {
                throw new \InvalidArgumentException('Decimal exceeds configured scale.');
            }

            $fraction = substr($fraction, 0, $scale);
        }

        $fraction = str_pad($fraction, $scale, '0', STR_PAD_RIGHT);

        if ($integer === '0' && ltrim($fraction, '0') === '') {
            $sign = '';
        }

        if ($scale === 0) {
            return $sign.$integer;
        }

        return $sign.$integer.'.'.$fraction;
    }

    public function encodeTimestamp(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value)
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s.u\Z');
        }

        return (new DateTimeImmutable((string) $value))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.u\Z');
    }

    public function encodeDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (new DateTimeImmutable((string) $value))->format('Y-m-d');
    }

    /**
     * @param  array<string, mixed>|list<mixed>|null  $value
     */
    public function encodeJson(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    public function columnsFor(ArchiveDataset $dataset, string $object): array
    {
        return match ($dataset) {
            ArchiveDataset::StockMovements => [
                'id',
                'tenant_id',
                'product_variant_id',
                'branch_id',
                'type',
                'quantity_delta',
                'unit_cost',
                'source_type',
                'source_id',
                'created_at',
            ],
            ArchiveDataset::AuditLogs => [
                'id',
                'tenant_id',
                'user_id',
                'action',
                'entity_type',
                'entity_id',
                'old_values',
                'new_values',
                'ip_address',
                'user_agent',
                'session_id',
                'created_at',
            ],
            ArchiveDataset::JournalEntries => match ($object) {
                'headers' => [
                    'id',
                    'tenant_id',
                    'branch_id',
                    'journal_entry_number',
                    'description',
                    'reference_type',
                    'reference_id',
                    'posted_at',
                    'created_at',
                ],
                'lines' => [
                    'id',
                    'tenant_id',
                    'journal_entry_id',
                    'coa_id',
                    'debit',
                    'credit',
                    'description',
                    'created_at',
                ],
                default => throw new \InvalidArgumentException("Unknown journal archive object [{$object}]."),
            },
        };
    }
}
