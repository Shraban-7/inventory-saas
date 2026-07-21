<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['auth', 'tenant'])
    ->group(function (): void {
        // Protected API routes are registered here by later domain phases.
    });
