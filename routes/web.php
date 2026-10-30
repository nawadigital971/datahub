<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Audience\AudienceController;
use App\Http\Controllers\Audience\SegmentController;
use App\Http\Controllers\Exports\ExportController;
use App\Http\Controllers\Health\DataHealthController;
use App\Http\Controllers\Imports\ImportBatchController;
use App\Http\Controllers\Imports\ImportMappingController;
use App\Http\Controllers\Imports\StagedRowController;
use App\Http\Controllers\ReferenceData\ReferenceDataController;
use App\Http\Controllers\Sources\SourceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('/', '/audience')->name('home');

    Route::get('audience', [AudienceController::class, 'index'])
        ->middleware('permission:'.Permission::ContactsView->value)
        ->name('audience.index');

    Route::middleware('permission:'.Permission::SegmentsManage->value)->group(function () {
        Route::post('segments', [SegmentController::class, 'store'])->name('segments.store');
        Route::delete('segments/{segment}', [SegmentController::class, 'destroy'])->name('segments.destroy');
    });

    Route::middleware('permission:'.Permission::ImportsCreate->value.'|'.Permission::ImportsReview->value)->group(function () {
        Route::get('imports', [ImportBatchController::class, 'index'])->name('imports.index');
        Route::get('imports/{batch}', [ImportBatchController::class, 'show'])->whereNumber('batch')->name('imports.show');
        Route::get('imports/{batch}/mapping', [ImportMappingController::class, 'edit'])->whereNumber('batch')->name('imports.mapping.edit');
    });

    Route::middleware('permission:'.Permission::ImportsCreate->value)->group(function () {
        Route::post('imports', [ImportBatchController::class, 'store'])->name('imports.store');
        Route::put('imports/{batch}/mapping', [ImportMappingController::class, 'update'])->whereNumber('batch')->name('imports.mapping.update');
    });

    Route::middleware('permission:'.Permission::ImportsReview->value)->group(function () {
        Route::patch('imports/{batch}/rows/{row}', [StagedRowController::class, 'update'])->name('imports.rows.update');
        Route::post('imports/{batch}/rows/bulk', [StagedRowController::class, 'bulk'])->name('imports.rows.bulk');
        Route::get('imports/{batch}/rows/{row}/merge', [StagedRowController::class, 'mergeComparison'])->name('imports.rows.merge.show');
        Route::put('imports/{batch}/rows/{row}/merge', [StagedRowController::class, 'saveMergeChoices'])->name('imports.rows.merge.update');
        Route::post('imports/{batch}/recheck', [ImportBatchController::class, 'recheck'])->whereNumber('batch')->name('imports.recheck');
    });

    Route::post('imports/{batch}/commit', [ImportBatchController::class, 'commit'])
        ->middleware('permission:'.Permission::ImportsCommit->value)->name('imports.commit');
    Route::post('imports/{batch}/discard', [ImportBatchController::class, 'discard'])
        ->middleware('permission:'.Permission::ImportsDiscard->value)->name('imports.discard');

    Route::get('exports', [ExportController::class, 'index'])
        ->middleware('permission:'.Permission::ExportsViewLog->value)
        ->name('exports.index');

    Route::middleware('permission:'.Permission::ExportsCreate->value)->group(function () {
        Route::get('exports/preview', [ExportController::class, 'preview'])->name('exports.preview');
        Route::post('exports', [ExportController::class, 'store'])->name('exports.store');
    });

    Route::get('exports/{export}', [ExportController::class, 'show'])->whereNumber('export')->name('exports.show');
    Route::get('exports/{export}/download', [ExportController::class, 'download'])->whereNumber('export')->name('exports.download');

    Route::middleware('permission:'.Permission::ContactsView->value)->group(function () {
        Route::get('health', [DataHealthController::class, 'index'])->name('health.index');
        Route::get('sources', [SourceController::class, 'index'])->name('sources.index');
    });

    Route::middleware('permission:'.Permission::SettingsUpdate->value)->group(function () {
        Route::post('sources', [SourceController::class, 'store'])->name('sources.store');
        Route::put('sources/{source}', [SourceController::class, 'update'])->name('sources.update');
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('permission:'.Permission::SettingsUpdate->value)->group(function () {
            Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::post('settings/general', [SettingsController::class, 'updateGeneral'])->name('settings.general.update');
            Route::put('settings/data', [SettingsController::class, 'updateData'])->name('settings.data.update');
        });

        Route::middleware('permission:'.Permission::SettingsUpdate->value.'|'.Permission::ContactsUpdate->value)->group(function () {
            Route::get('reference/{type?}', [ReferenceDataController::class, 'index'])->where('type', '[a-z_]+')->name('reference.index');
            Route::post('reference/{type}', [ReferenceDataController::class, 'store'])->name('reference.store');
            Route::put('reference/{type}/{item}', [ReferenceDataController::class, 'update'])->whereNumber('item')->name('reference.update');
            Route::delete('reference/{type}/{item}', [ReferenceDataController::class, 'destroy'])->whereNumber('item')->name('reference.destroy');
        });

        Route::middleware('permission:'.Permission::UsersManage->value)->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::post('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
            Route::post('users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');
            Route::post('users/{user}/tokens', [UserController::class, 'issueToken'])->name('users.tokens.store');
            Route::delete('users/{user}/tokens/{token}', [UserController::class, 'revokeToken'])->whereNumber('token')->name('users.tokens.destroy');
            Route::post('roles', [UserController::class, 'storeRole'])->name('roles.store');
            Route::put('roles/{role}', [UserController::class, 'updateRole'])->name('roles.update');
        });
    });

    if (app()->isLocal()) {
        Route::inertia('dev/components', 'dev/Components')->name('dev.components');
    }
});

require __DIR__.'/settings.php';
