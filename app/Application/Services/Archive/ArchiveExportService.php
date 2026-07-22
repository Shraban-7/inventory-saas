<?php

namespace App\Application\Services\Archive;

use App\Domain\Entities\ArchiveDataset;
use App\Domain\Entities\ArchiveExportStatus;
use App\Infrastructure\Models\ArchiveExport;
use App\Infrastructure\Models\AuditLog;
use App\Infrastructure\Models\JournalEntry;
use App\Infrastructure\Models\JournalEntryLine;
use App\Infrastructure\Models\StockMovement;
use DateTimeImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class ArchiveExportService
{
    public function __construct(
        private readonly ArchiveCsvEncoder $encoder,
    ) {}

    public function retentionCutoff(): DateTimeImmutable
    {
        return new DateTimeImmutable('-'.(int) config('archive.retention_years', 5).' years');
    }

    public function maxClosedYear(?DateTimeImmutable $cutoff = null): ?int
    {
        $cutoff ??= $this->retentionCutoff();
        $candidate = (int) $cutoff->format('Y') - 1;
        $yearEnd = new DateTimeImmutable(sprintf('%d-12-31 23:59:59', $candidate));

        if ($yearEnd >= $cutoff) {
            $candidate--;
        }

        return $candidate >= 1970 ? $candidate : null;
    }

    /**
     * @return list<int>
     */
    public function discoverYearsWithData(ArchiveDataset $dataset, ?DateTimeImmutable $cutoff = null): array
    {
        $cutoff ??= $this->retentionCutoff();
        $maxClosedYear = $this->maxClosedYear($cutoff);

        if ($maxClosedYear === null) {
            return [];
        }

        $years = match ($dataset) {
            ArchiveDataset::StockMovements => $this->pluckClosedYears(
                StockMovement::query()->where('created_at', '<', $cutoff->format('Y-m-d H:i:s')),
                'created_at',
                $maxClosedYear,
            ),
            ArchiveDataset::AuditLogs => $this->pluckClosedYears(
                AuditLog::query()->where('created_at', '<', $cutoff->format('Y-m-d H:i:s')),
                'created_at',
                $maxClosedYear,
            ),
            ArchiveDataset::JournalEntries => $this->pluckClosedYears(
                JournalEntry::query()->where('posted_at', '<', $cutoff->format('Y-m-d')),
                'posted_at',
                $maxClosedYear,
            ),
        };

        return $years;
    }

    /**
     * @param  Builder<*>  $query
     * @return list<int>
     */
    private function pluckClosedYears(Builder $query, string $column, int $maxClosedYear): array
    {
        $driver = DB::connection()->getDriverName();
        $isMysql = in_array($driver, ['mysql', 'mariadb'], true);

        if ($column === 'posted_at') {
            $years = $isMysql
                ? $query->whereRaw('YEAR(posted_at) <= ?', [$maxClosedYear])
                    ->selectRaw('DISTINCT YEAR(posted_at) as period_year')
                    ->orderBy('period_year')
                    ->pluck('period_year')
                : $query->whereRaw("CAST(strftime('%Y', posted_at) AS INTEGER) <= ?", [$maxClosedYear])
                    ->selectRaw("DISTINCT CAST(strftime('%Y', posted_at) AS INTEGER) as period_year")
                    ->orderBy('period_year')
                    ->pluck('period_year');
        } else {
            $years = $isMysql
                ? $query->whereRaw('YEAR(created_at) <= ?', [$maxClosedYear])
                    ->selectRaw('DISTINCT YEAR(created_at) as period_year')
                    ->orderBy('period_year')
                    ->pluck('period_year')
                : $query->whereRaw("CAST(strftime('%Y', created_at) AS INTEGER) <= ?", [$maxClosedYear])
                    ->selectRaw("DISTINCT CAST(strftime('%Y', created_at) AS INTEGER) as period_year")
                    ->orderBy('period_year')
                    ->pluck('period_year');
        }

        return array_values(array_map(
            static fn (mixed $year): int => (int) $year,
            $years->all(),
        ));
    }

    /**
     * Atomically claim an export row for processing.
     *
     * Only Pending, Failed, or stale Exporting rows may be claimed. Completed and
     * fresh Exporting rows return null (no-op) so concurrent workers cannot rewrite
     * the same object prefix.
     */
    public function claim(int $exportId): ?ArchiveExport
    {
        return DB::transaction(function () use ($exportId): ?ArchiveExport {
            $export = ArchiveExport::query()
                ->whereKey($exportId)
                ->lockForUpdate()
                ->first();

            if (! $export instanceof ArchiveExport) {
                return null;
            }

            if ($export->status === ArchiveExportStatus::Completed) {
                return null;
            }

            if ($export->status === ArchiveExportStatus::Exporting && ! $this->isStaleExporting($export)) {
                return null;
            }

            $diskName = (string) config('archive.disk', 's3');
            $basePath = $this->objectPrefix($export);

            $export->forceFill([
                'status' => ArchiveExportStatus::Exporting,
                'disk' => $diskName,
                'path' => $basePath.'/manifest.json',
                'error_code' => null,
                'started_at' => now(),
                'completed_at' => null,
                'manifest' => null,
                'checksum' => null,
                'row_count' => null,
            ])->save();

            return $export->refresh();
        });
    }

    public function export(ArchiveExport $export): ArchiveExport
    {
        $claimed = $this->claim((int) $export->getKey());

        if (! $claimed instanceof ArchiveExport) {
            return $export->fresh() ?? $export;
        }

        $diskName = (string) $claimed->disk;
        $schemaVersion = (string) $claimed->schema_version;
        $dataset = $claimed->dataset;
        $year = (int) $claimed->period_year;
        $basePath = $this->objectPrefix($claimed);

        try {
            $manifest = $this->withStringifiedFetches(
                fn (): array => match ($dataset) {
                    ArchiveDataset::StockMovements, ArchiveDataset::AuditLogs => $this->exportSimpleDataset(
                        Storage::disk($diskName),
                        $dataset,
                        $year,
                        $basePath,
                        (int) $claimed->tenant_id,
                        $schemaVersion,
                    ),
                    ArchiveDataset::JournalEntries => $this->exportJournalBundle(
                        Storage::disk($diskName),
                        $year,
                        $basePath,
                        (int) $claimed->tenant_id,
                        $schemaVersion,
                    ),
                },
            );

            $disk = Storage::disk($diskName);
            $manifestJson = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $manifestChecksum = hash('sha256', $manifestJson);
            $manifestPath = $basePath.'/manifest.json';

            $disk->put($manifestPath, $manifestJson, array_merge(
                ['visibility' => config('archive.upload.visibility', 'private')],
                config('archive.upload.manifest_options', []),
            ));

            $this->assertObjectChecksum($disk, $manifestPath, $manifestChecksum);

            $claimed->forceFill([
                'status' => ArchiveExportStatus::Completed,
                'manifest' => $manifest,
                'checksum' => $manifestChecksum,
                'row_count' => (int) ($manifest['row_count'] ?? 0),
                'completed_at' => now(),
                'error_code' => null,
            ])->save();
        } catch (Throwable $exception) {
            $claimed->forceFill([
                'status' => ArchiveExportStatus::Failed,
                'error_code' => 'archive_export_failed',
                'completed_at' => now(),
            ])->save();

            throw $exception;
        }

        return $claimed->refresh();
    }

    public function objectPrefix(ArchiveExport $export): string
    {
        return sprintf(
            '%s/%d/%s/%d/v%s',
            trim((string) config('archive.path_prefix', 'archives'), '/'),
            (int) $export->tenant_id,
            $export->dataset->value,
            (int) $export->period_year,
            (string) $export->schema_version,
        );
    }

    private function isStaleExporting(ArchiveExport $export): bool
    {
        if ($export->started_at === null) {
            return true;
        }

        $timeout = (int) config('archive.export_timeout_seconds', 300);
        $grace = (int) config('archive.export_claim_grace_seconds', 30);
        $staleBefore = now()->subSeconds(max(1, $timeout + $grace));

        return $export->started_at->lte($staleBefore);
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function withStringifiedFetches(callable $callback): mixed
    {
        $pdo = DB::connection()->getPdo();
        $previous = $pdo->getAttribute(\PDO::ATTR_STRINGIFY_FETCHES);
        $pdo->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, true);

        try {
            return $callback();
        } finally {
            $pdo->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, $previous);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function exportSimpleDataset(
        Filesystem $disk,
        ArchiveDataset $dataset,
        int $year,
        string $basePath,
        int $tenantId,
        string $schemaVersion,
    ): array {
        $objectPath = $basePath.'/data.csv';
        $columns = $this->encoder->columnsFor($dataset, 'data');
        $query = $this->baseQueryFor($dataset, $year);
        $result = $this->streamQueryToCsv($disk, $objectPath, $columns, $query, function (Model $row) use ($dataset): array {
            return $this->mapSimpleRow($dataset, $row);
        });

        return [
            'schema_version' => $schemaVersion,
            'tenant_id' => $tenantId,
            'dataset' => $dataset->value,
            'period_year' => $year,
            'objects' => [
                [
                    'name' => 'data',
                    'path' => $objectPath,
                    'checksum' => $result['checksum'],
                    'row_count' => $result['row_count'],
                    'min_id' => $result['min_id'],
                    'max_id' => $result['max_id'],
                    'min_date' => $result['min_date'],
                    'max_date' => $result['max_date'],
                ],
            ],
            'row_count' => $result['row_count'],
            'checksums' => [
                'data' => $result['checksum'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function exportJournalBundle(
        Filesystem $disk,
        int $year,
        string $basePath,
        int $tenantId,
        string $schemaVersion,
    ): array {
        $headersPath = $basePath.'/headers.csv';
        $linesPath = $basePath.'/lines.csv';
        $headerColumns = $this->encoder->columnsFor(ArchiveDataset::JournalEntries, 'headers');
        $lineColumns = $this->encoder->columnsFor(ArchiveDataset::JournalEntries, 'lines');
        $cutoff = $this->retentionCutoff();

        $headerQuery = JournalEntry::query()
            ->whereYear('posted_at', $year)
            ->where('posted_at', '<', $cutoff->format('Y-m-d'))
            ->orderBy('id');

        $headers = $this->streamQueryToCsv(
            $disk,
            $headersPath,
            $headerColumns,
            $headerQuery,
            fn (JournalEntry $entry): array => [
                (string) $entry->getKey(),
                (string) $entry->tenant_id,
                (string) $entry->branch_id,
                (string) $entry->journal_entry_number,
                $entry->description,
                $entry->reference_type,
                $entry->reference_id !== null ? (string) $entry->reference_id : null,
                $this->encoder->encodeDate($entry->posted_at),
                $this->encoder->encodeTimestamp($entry->created_at),
            ],
        );

        $entryIdSubquery = JournalEntry::query()
            ->whereYear('posted_at', $year)
            ->where('posted_at', '<', $cutoff->format('Y-m-d'))
            ->select('id');

        $lineQuery = JournalEntryLine::query()
            ->whereIn('journal_entry_id', $entryIdSubquery)
            ->orderBy('id');

        $lines = $this->streamQueryToCsv(
            $disk,
            $linesPath,
            $lineColumns,
            $lineQuery,
            fn (JournalEntryLine $line): array => [
                (string) $line->getKey(),
                (string) $line->tenant_id,
                (string) $line->journal_entry_id,
                (string) $line->coa_id,
                $this->encoder->encodeDecimal($line->getRawOriginal('debit'), 2),
                $this->encoder->encodeDecimal($line->getRawOriginal('credit'), 2),
                $line->description,
                $this->encoder->encodeTimestamp($line->created_at),
            ],
        );

        return [
            'schema_version' => $schemaVersion,
            'tenant_id' => $tenantId,
            'dataset' => ArchiveDataset::JournalEntries->value,
            'period_year' => $year,
            'objects' => [
                [
                    'name' => 'headers',
                    'path' => $headersPath,
                    'checksum' => $headers['checksum'],
                    'row_count' => $headers['row_count'],
                    'min_id' => $headers['min_id'],
                    'max_id' => $headers['max_id'],
                    'min_date' => $headers['min_date'],
                    'max_date' => $headers['max_date'],
                ],
                [
                    'name' => 'lines',
                    'path' => $linesPath,
                    'checksum' => $lines['checksum'],
                    'row_count' => $lines['row_count'],
                    'min_id' => $lines['min_id'],
                    'max_id' => $lines['max_id'],
                    'min_date' => $lines['min_date'],
                    'max_date' => $lines['max_date'],
                ],
            ],
            'row_count' => $headers['row_count'] + $lines['row_count'],
            'checksums' => [
                'headers' => $headers['checksum'],
                'lines' => $lines['checksum'],
            ],
        ];
    }

    /**
     * @return Builder<*>
     */
    private function baseQueryFor(ArchiveDataset $dataset, int $year): Builder
    {
        $cutoff = $this->retentionCutoff();

        return match ($dataset) {
            ArchiveDataset::StockMovements => StockMovement::query()
                ->whereYear('created_at', $year)
                ->where('created_at', '<', $cutoff->format('Y-m-d H:i:s'))
                ->orderBy('id'),
            ArchiveDataset::AuditLogs => AuditLog::query()
                ->whereYear('created_at', $year)
                ->where('created_at', '<', $cutoff->format('Y-m-d H:i:s'))
                ->orderBy('id'),
            ArchiveDataset::JournalEntries => JournalEntry::query()
                ->whereYear('posted_at', $year)
                ->where('posted_at', '<', $cutoff->format('Y-m-d'))
                ->orderBy('id'),
        };
    }

    /**
     * @return list<string|null>
     */
    private function mapSimpleRow(ArchiveDataset $dataset, Model $row): array
    {
        return match ($dataset) {
            ArchiveDataset::StockMovements => [
                (string) $row->getKey(),
                (string) $row->getAttribute('tenant_id'),
                (string) $row->getAttribute('product_variant_id'),
                (string) $row->getAttribute('branch_id'),
                $row->getAttribute('type') instanceof \BackedEnum
                    ? (string) $row->getAttribute('type')->value
                    : (string) $row->getAttribute('type'),
                $this->encoder->encodeDecimal($row->getRawOriginal('quantity_delta'), 4),
                $this->encoder->encodeDecimal($row->getRawOriginal('unit_cost'), 4),
                ($sourceType = $row->getAttribute('source_type')) !== null ? (string) $sourceType : null,
                $row->getAttribute('source_id') !== null ? (string) $row->getAttribute('source_id') : null,
                $this->encoder->encodeTimestamp($row->getAttribute('created_at')),
            ],
            ArchiveDataset::AuditLogs => [
                (string) $row->getKey(),
                (string) $row->getAttribute('tenant_id'),
                $row->getAttribute('user_id') !== null ? (string) $row->getAttribute('user_id') : null,
                (string) $row->getAttribute('action'),
                (string) $row->getAttribute('entity_type'),
                (string) $row->getAttribute('entity_id'),
                $this->encoder->encodeJson($row->getAttribute('old_values')),
                $this->encoder->encodeJson($row->getAttribute('new_values')),
                ($ip = $row->getAttribute('ip_address')) !== null ? (string) $ip : null,
                ($ua = $row->getAttribute('user_agent')) !== null ? (string) $ua : null,
                ($session = $row->getAttribute('session_id')) !== null ? (string) $session : null,
                $this->encoder->encodeTimestamp($row->getAttribute('created_at')),
            ],
            ArchiveDataset::JournalEntries => throw new RuntimeException('Journal rows use the bundle exporter.'),
        };
    }

    /**
     * @param  Builder<*>  $query
     * @param  list<string>  $columns
     * @param  callable(mixed): list<string|null>  $mapper
     * @return array{checksum: string, row_count: int, min_id: int|null, max_id: int|null, min_date: string|null, max_date: string|null}
     */
    private function streamQueryToCsv(
        Filesystem $disk,
        string $objectPath,
        array $columns,
        Builder $query,
        callable $mapper,
    ): array {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new RuntimeException('Unable to open temporary archive stream.');
        }

        $hash = hash_init('sha256');
        $rowCount = 0;
        $minId = null;
        $maxId = null;
        $minDate = null;
        $maxDate = null;

        try {
            $header = $this->encoder->encodeRow($columns);
            fwrite($stream, $header);
            hash_update($hash, $header);

            $query->chunkById((int) config('archive.chunk_size', 500), function ($rows) use (
                $stream,
                $hash,
                $mapper,
                &$rowCount,
                &$minId,
                &$maxId,
                &$minDate,
                &$maxDate,
            ): void {
                foreach ($rows as $row) {
                    $fields = $mapper($row);
                    $line = $this->encoder->encodeRow($fields);
                    fwrite($stream, $line);
                    hash_update($hash, $line);
                    $rowCount++;

                    $id = (int) $row->getKey();
                    $minId = $minId === null ? $id : min($minId, $id);
                    $maxId = $maxId === null ? $id : max($maxId, $id);

                    $dateValue = $row->getAttribute('posted_at') ?? $row->getAttribute('created_at');
                    $encodedDate = $dateValue !== null
                        ? ($row->getAttribute('posted_at') !== null
                            ? $this->encoder->encodeDate($dateValue)
                            : $this->encoder->encodeTimestamp($dateValue))
                        : null;

                    if ($encodedDate !== null) {
                        $minDate = $minDate === null || $encodedDate < $minDate ? $encodedDate : $minDate;
                        $maxDate = $maxDate === null || $encodedDate > $maxDate ? $encodedDate : $maxDate;
                    }
                }
            });

            rewind($stream);

            $disk->writeStream($objectPath, $stream, array_merge(
                ['visibility' => config('archive.upload.visibility', 'private')],
                config('archive.upload.options', []),
            ));
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $checksum = hash_final($hash);
        $this->assertObjectChecksum($disk, $objectPath, $checksum);
        $this->assertObjectRows($disk, $objectPath, $rowCount);

        return [
            'checksum' => $checksum,
            'row_count' => $rowCount,
            'min_id' => $minId,
            'max_id' => $maxId,
            'min_date' => $minDate,
            'max_date' => $maxDate,
        ];
    }

    private function assertObjectChecksum(Filesystem $disk, string $path, string $expectedChecksum): void
    {
        $readStream = $disk->readStream($path);

        if ($readStream === null) {
            throw new RuntimeException('Unable to read archived object for checksum verification.');
        }

        $hash = hash_init('sha256');

        try {
            while (! feof($readStream)) {
                $chunk = fread($readStream, 1024 * 1024);

                if ($chunk === false) {
                    throw new RuntimeException('Failed while verifying archived object checksum.');
                }

                if ($chunk !== '') {
                    hash_update($hash, $chunk);
                }
            }
        } finally {
            if (is_resource($readStream)) {
                fclose($readStream);
            }
        }

        $actual = hash_final($hash);

        if (! hash_equals($expectedChecksum, $actual)) {
            throw new RuntimeException('Archived object checksum mismatch.');
        }
    }

    private function assertObjectRows(Filesystem $disk, string $path, int $expectedRows): void
    {
        $readStream = $disk->readStream($path);

        if ($readStream === null) {
            throw new RuntimeException('Unable to read archived CSV for row-count verification.');
        }

        $dataRows = 0;

        try {
            $header = fgetcsv($readStream, 0, ',', '"', '');

            if ($header === false) {
                throw new RuntimeException('Archived CSV is missing a header row.');
            }

            while (($row = fgetcsv($readStream, 0, ',', '"', '')) !== false) {
                if ($row === [null]) {
                    continue;
                }

                $dataRows++;
            }
        } finally {
            if (is_resource($readStream)) {
                fclose($readStream);
            }
        }

        if ($dataRows !== $expectedRows) {
            throw new RuntimeException('Archived object row count mismatch.');
        }
    }
}
