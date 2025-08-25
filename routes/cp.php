<?php

use Illuminate\Support\Facades\Route;
use EmranAlhaddad\ContentSync\Http\Controllers\ExportController;
use EmranAlhaddad\ContentSync\Http\Controllers\ImportController;

Route::middleware(['web', 'statamic.cp.authenticated'])
    ->name('content-sync.')
    ->prefix('cp/content-sync')
    ->group(function () {
        Route::get('/options', [ExportController::class, 'options']);
        Route::post('/export', [ExportController::class, 'export']);
        Route::post('/import/preview', [ImportController::class, 'preview']);
        Route::post('/import/commit', [ImportController::class, 'commit']);
    });
