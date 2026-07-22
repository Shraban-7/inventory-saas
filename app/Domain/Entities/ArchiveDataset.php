<?php

namespace App\Domain\Entities;

enum ArchiveDataset: string
{
    case StockMovements = 'stock_movements';
    case AuditLogs = 'audit_logs';
    case JournalEntries = 'journal_entries';
}
