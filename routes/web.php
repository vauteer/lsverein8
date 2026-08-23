<?php

use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\UserController;
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
});

require __DIR__.'/settings.php';
