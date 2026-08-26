<?php

use App\Http\Controllers\BackupController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\ClubSwitchController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UserController;
use App\Models\Club;
use App\Models\Event;
use App\Models\Role;
use App\Models\Section;
use App\Models\Subscription;
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

    Route::get('roles', [RoleController::class, 'index'])
        ->name('roles.index')->can('viewAny', Role::class);
    Route::get('roles/create', [RoleController::class, 'create'])
        ->name('roles.create')->can('create', Role::class);
    Route::post('roles', [RoleController::class, 'store'])
        ->name('roles.store')->can('create', Role::class);
    Route::get('roles/{role}/edit', [RoleController::class, 'edit'])
        ->name('roles.edit')->can('update', 'role');
    Route::put('roles/{role}', [RoleController::class, 'update'])
        ->name('roles.update')->can('update', 'role');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])
        ->name('roles.destroy')->can('delete', 'role');

    Route::get('subscriptions', [SubscriptionController::class, 'index'])
        ->name('subscriptions.index')->can('viewAny', Subscription::class);
    Route::get('subscriptions/create', [SubscriptionController::class, 'create'])
        ->name('subscriptions.create')->can('create', Subscription::class);
    Route::post('subscriptions', [SubscriptionController::class, 'store'])
        ->name('subscriptions.store')->can('create', Subscription::class);
    Route::get('subscriptions/{subscription}/edit', [SubscriptionController::class, 'edit'])
        ->name('subscriptions.edit')->can('update', 'subscription');
    Route::put('subscriptions/{subscription}', [SubscriptionController::class, 'update'])
        ->name('subscriptions.update')->can('update', 'subscription');
    Route::delete('subscriptions/{subscription}', [SubscriptionController::class, 'destroy'])
        ->name('subscriptions.destroy')->can('delete', 'subscription');
    Route::post('subscriptions/debit', [SubscriptionController::class, 'debit'])
        ->name('subscriptions.debit')->can('debit', Subscription::class);

    // The generated SEPA and BLSV files. {filename} carries no club prefix;
    // DownloadController adds the caller's own, so the URL cannot name
    // another club's file.
    Route::get('downloads/{filename}', [DownloadController::class, 'show'])
        ->name('downloads.show')->middleware('can:downloadGeneratedFiles');

    // Root sees and edits every club; a club admin only the one they are
    // currently working in, so there is no index for them (ClubPolicy).
    Route::get('clubs', [ClubController::class, 'index'])
        ->name('clubs.index')->can('viewAny', Club::class);
    Route::get('clubs/create', [ClubController::class, 'create'])
        ->name('clubs.create')->can('create', Club::class);
    Route::post('clubs', [ClubController::class, 'store'])
        ->name('clubs.store')->can('create', Club::class);
    Route::get('clubs/{club}/edit', [ClubController::class, 'edit'])
        ->name('clubs.edit')->can('update', 'club');
    Route::put('clubs/{club}', [ClubController::class, 'update'])
        ->name('clubs.update')->can('update', 'club');
    Route::delete('clubs/{club}', [ClubController::class, 'destroy'])
        ->name('clubs.destroy')->can('delete', 'club');
    Route::post('clubs/{club}/switch', [ClubSwitchController::class, 'store'])
        ->name('clubs.switch')->can('switchTo', 'club');

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
