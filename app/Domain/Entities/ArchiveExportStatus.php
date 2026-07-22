<?php

namespace App\Domain\Entities;

enum ArchiveExportStatus: string
{
    case Pending = 'pending';
    case Exporting = 'exporting';
    case Completed = 'completed';
    case Failed = 'failed';
}
