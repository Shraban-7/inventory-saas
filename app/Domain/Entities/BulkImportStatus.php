<?php

namespace App\Domain\Entities;

enum BulkImportStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
