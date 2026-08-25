<?php

use App\Http\Controllers\BackupController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\UserController;
use App\Models\Event;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // Reachable by the impersonated session too, which is never root.
    Route::delete('impersonate', [ImpersonationController::class, 'destroy'])->name('impersonate.destroy');

    Route::get('users', [UserController::class, 'index'])
        ->name('users.index')->can('viewAny', User::class);
    Route::get('users/create', [UserController::class, 'create'])
        ->name('users.create')->can('create', User::class);
    Route::post('users', [UserController::class, 'store'])
        ->name('users.store')->can('create', User::class);
    Route::get('users/{user}/edit', [UserController::class, 'edit'])
        ->name('users.edit')->can('update', 'user');
    Route::put('users/{user}', [UserController::class, 'update'])
        ->name('users.update')->can('update', 'user');
    Route::delete('users/{user}', [UserController::class, 'destroy'])
        ->name('users.destroy')->can('delete', 'user');
    Route::post('users/{user}/impersonate', [ImpersonationController::class, 'store'])
        ->name('users.impersonate')->can('impersonate', 'user');

    Route::get('sections', [SectionController::class, 'index'])
        ->name('sections.index')->can('viewAny', Section::class);
    Route::get('sections/create', [SectionController::class, 'create'])
        ->name('sections.create')->can('create', Section::class);
    Route::post('sections', [SectionController::class, 'store'])
        ->name('sections.store')->can('create', Section::class);
    Route::get('sections/{section}/edit', [SectionController::class, 'edit'])
        ->name('sections.edit')->can('update', 'section');
    Route::put('sections/{section}', [SectionController::class, 'update'])
        ->name('sections.update')->can('update', 'section');
    Route::delete('sections/{section}', [SectionController::class, 'destroy'])
        ->name('sections.destroy')->can('delete', 'section');

    Route::get('events', [EventController::class, 'index'])
        ->name('events.index')->can('viewAny', Event::class);
    Route::get('events/create', [EventController::class, 'create'])
        ->name('events.create')->can('create', Event::class);
    Route::post('events', [EventController::class, 'store'])
        ->name('events.store')->can('create', Event::class);
    Route::get('events/{event}/edit', [EventController::class, 'edit'])
        ->name('events.edit')->can('update', 'event');
    Route::put('events/{event}', [EventController::class, 'update'])
        ->name('events.update')->can('update', 'event');
    Route::delete('events/{event}', [EventController::class, 'destroy'])
        ->name('events.destroy')->can('delete', 'event');

    // Root-only; a backup spans every club. {filename} is validated against
    // the listing in the controller, so it cannot escape the directory.
    Route::middleware('can:manageBackups')->group(function () {
        Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('backups', [BackupController::class, 'store'])->name('backups.store');
        Route::get('backups/{filename}', [BackupController::class, 'download'])->name('backups.download');
        Route::post('backups/{filename}/restore', [BackupController::class, 'restore'])->name('backups.restore');
        Route::delete('backups/{filename}', [BackupController::class, 'destroy'])->name('backups.destroy');
    });
});

require __DIR__.'/settings.php';
