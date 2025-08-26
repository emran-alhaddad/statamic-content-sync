<?php

use Illuminate\Support\Facades\Route;
use EmranAlhaddad\ContentSync\Http\Controllers\ExportController;
use EmranAlhaddad\ContentSync\Http\Controllers\ImportController;
use EmranAlhaddad\ContentSync\Http\Controllers\ExportDownloadController;

Route::middleware(['web', 'statamic.cp.authenticated'])
    ->name('content-sync.')
    ->prefix('cp/content-sync')
    ->group(function () {
        Route::get('/options', [ExportController::class, 'options'])->name('options');
        Route::post('/export', [ExportController::class, 'export'])->name('export');

        Route::get('/download/{file}', [ExportDownloadController::class, 'download'])
            ->where('file', '[A-Za-z0-9._-]+')
            ->name('download');

        Route::post('/import/preview', [ImportController::class, 'preview'])->name('preview');
        Route::post('/import/commit',  [ImportController::class, 'commit'])->name('commit');
    });
