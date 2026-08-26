<?php

use App\Http\Controllers\BackupController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\ClubSwitchController;
use App\Http\Controllers\DebitController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberExportController;
use App\Http\Controllers\Members\MemberEventController;
use App\Http\Controllers\Members\MemberItemController;
use App\Http\Controllers\Members\MemberRoleController;
use App\Http\Controllers\Members\MemberSectionController;
use App\Http\Controllers\Members\MembershipController;
use App\Http\Controllers\Members\MemberSubscriptionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UserController;
use App\Models\Club;
use App\Models\Debit;
use App\Models\Event;
use App\Models\Item;
use App\Models\Member;
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

    // The member list is readable by everybody in the club; MemberPolicy and
    // MemberResource are what keep the bank details and the finance columns
    // to an admin.
    Route::get('members', [MemberController::class, 'index'])
        ->name('members.index')->can('viewAny', Member::class);
    // Before members/{member} would ever be consulted, and readable by
    // everybody who may read the list — the exports carry nothing the list
    // does not already show.
    Route::get('members/export/{format}', MemberExportController::class)
        ->name('members.export')->can('viewAny', Member::class);
    Route::get('members/create', [MemberController::class, 'create'])
        ->name('members.create')->can('create', Member::class);
    Route::post('members', [MemberController::class, 'store'])
        ->name('members.store')->can('create', Member::class);
    Route::get('members/{member}', [MemberController::class, 'show'])
        ->name('members.show')->can('view', 'member');
    Route::get('members/{member}/edit', [MemberController::class, 'edit'])
        ->name('members.edit')->can('update', 'member');
    Route::put('members/{member}', [MemberController::class, 'update'])
        ->name('members.update')->can('update', 'member');
    Route::put('members/{member}/resign', [MemberController::class, 'resign'])
        ->name('members.resign')->can('resign', 'member');
    Route::delete('members/{member}', [MemberController::class, 'destroy'])
        ->name('members.destroy')->can('delete', 'member');

    // The member's six relations, all edited from the member page itself.
    // Changing any of them is an update of the member, so they all sit behind
    // the same policy check; the row is addressed by pivot id, because the
    // same section or role may appear twice with different ranges.
    foreach ([
        'memberships' => MembershipController::class,
        'sections' => MemberSectionController::class,
        'roles' => MemberRoleController::class,
        'events' => MemberEventController::class,
        'subscriptions' => MemberSubscriptionController::class,
    ] as $relation => $controller) {
        Route::post("members/{member}/{$relation}", [$controller, 'store'])
            ->name("members.{$relation}.store")->can('update', 'member');
        Route::put("members/{member}/{$relation}/{row}", [$controller, 'update'])
            ->name("members.{$relation}.update")->can('update', 'member');
        Route::delete("members/{member}/{$relation}/{row}", [$controller, 'destroy'])
            ->name("members.{$relation}.destroy")->can('update', 'member');
    }

    // The inventory is opt-in per club, so these carry ItemPolicy on top —
    // a club that keeps no inventory cannot issue one either.
    Route::post('members/{member}/items', [MemberItemController::class, 'store'])
        ->name('members.items.store')->can('update', 'member')->can('viewAny', Item::class);
    Route::put('members/{member}/items/{row}', [MemberItemController::class, 'update'])
        ->name('members.items.update')->can('update', 'member')->can('viewAny', Item::class);
    Route::delete('members/{member}/items/{row}', [MemberItemController::class, 'destroy'])
        ->name('members.items.destroy')->can('update', 'member')->can('viewAny', Item::class);

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

    // One-off direct debits, kept until a collection run takes them along and
    // clears them. Admin-only throughout: a row names a member and the money
    // about to leave their account.
    Route::get('debits', [DebitController::class, 'index'])
        ->name('debits.index')->can('viewAny', Debit::class);
    Route::get('debits/create', [DebitController::class, 'create'])
        ->name('debits.create')->can('create', Debit::class);
    Route::post('debits', [DebitController::class, 'store'])
        ->name('debits.store')->can('create', Debit::class);
    Route::get('debits/{debit}/edit', [DebitController::class, 'edit'])
        ->name('debits.edit')->can('update', 'debit');
    Route::put('debits/{debit}', [DebitController::class, 'update'])
        ->name('debits.update')->can('update', 'debit');
    Route::delete('debits/{debit}', [DebitController::class, 'destroy'])
        ->name('debits.destroy')->can('delete', 'debit');
    Route::post('debits/collect', [DebitController::class, 'collect'])
        ->name('debits.collect')->can('debit', Debit::class);

    // Only for a club that has switched the inventory on; ItemPolicy refuses
    // every action for one that has not.
    Route::get('items', [ItemController::class, 'index'])
        ->name('items.index')->can('viewAny', Item::class);
    Route::get('items/create', [ItemController::class, 'create'])
        ->name('items.create')->can('create', Item::class);
    Route::post('items', [ItemController::class, 'store'])
        ->name('items.store')->can('create', Item::class);
    Route::get('items/{item}/edit', [ItemController::class, 'edit'])
        ->name('items.edit')->can('update', 'item');
    Route::put('items/{item}', [ItemController::class, 'update'])
        ->name('items.update')->can('update', 'item');
    Route::delete('items/{item}', [ItemController::class, 'destroy'])
        ->name('items.destroy')->can('delete', 'item');

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
