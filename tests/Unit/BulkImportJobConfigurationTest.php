<?php

use App\Application\Jobs\FinalizeBulkImportJob;
use App\Application\Jobs\ProcessBulkImportChunkJob;
use App\Application\Jobs\ProcessBulkImportJob;
use App\Notifications\BulkImportFinishedNotification;
use Illuminate\Contracts\Queue\ShouldBeUnique;

it('configures all bulk import jobs for chunking retries and the imports queue', function () {
    $parent = new ProcessBulkImportJob(1, 'import-id');
    $chunk = new ProcessBulkImportChunkJob(1, 'import-id', 1, 'products', [], []);
    $finalize = new FinalizeBulkImportJob(1, 'import-id');

    expect(ProcessBulkImportJob::CHUNK_SIZE)->toBe(100)
        ->and($parent)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($parent->uniqueId())->toBe('import-id')
        ->and($parent->uniqueFor)->toBe(300)
        ->and($parent->queue)->toBe('imports')
        ->and($chunk->queue)->toBe('imports')
        ->and($chunk->afterCommit)->toBeTrue()
        ->and($finalize->queue)->toBe('imports')
        ->and($parent->tries)->toBe(3)
        ->and($chunk->tries)->toBe(3)
        ->and($finalize->tries)->toBe(3)
        ->and($parent->backoff)->toBe([5, 15, 30])
        ->and($chunk->backoff)->toBe([5, 15, 30])
        ->and($finalize->backoff)->toBe([5, 15, 30]);
});

it('routes bulk import database notifications to the notifications queue', function () {
    $notification = new BulkImportFinishedNotification(
        'import-id',
        'completed',
        2,
        1,
        1,
        0,
    );

    expect($notification->queue)->toBe('notifications')
        ->and($notification->via(new stdClass))->toBe(['database'])
        ->and($notification->toDatabase(new stdClass))->not->toHaveKey('exception');
});
