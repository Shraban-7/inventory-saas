<?php

namespace App\Domain\Entities;

enum BulkImportRowStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
