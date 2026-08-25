<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureGates();
    }

    /**
     * Gates that are not tied to a model, and so have no policy of their own.
     */
    protected function configureGates(): void
    {
        // Reading storage/logs means reading every club's data: stack traces
        // carry member names, emails and query bindings. That is above a club
        // admin's pay grade, so the log viewer is root-only (users.admin).
        //
        // Without this gate the package's own route is open in every
        // environment that is not production — it only aborts when
        // App::isProduction() is true. The typed User parameter also denies
        // guests, since the package route carries no auth middleware.
        // (bool): see SectionPolicy — a freshly created model has no `admin`
        // attribute loaded, so the raw value is null rather than false.
        Gate::define('viewLogViewer', fn (User $user): bool => (bool) $user->admin);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
