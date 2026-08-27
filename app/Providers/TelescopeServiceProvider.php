<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->hideSensitiveRequestDetails();

        Telescope::filter(fn (IncomingEntry $entry): bool => $this->shouldRecord($entry));
    }

    /**
     * Outside the local environment only entries pointing at a problem are
     * kept, so a healthy site records next to nothing. Setting
     * TELESCOPE_RECORD_EVERYTHING records all traffic there as well, which
     * is read per entry so the switch takes effect without a redeploy.
     */
    protected function shouldRecord(IncomingEntry $entry): bool
    {
        return $this->app->environment('local')
            || (bool) config('telescope.record_everything', false)
            || $entry->isReportableException()
            || $entry->isFailedRequest()
            || $entry->isFailedJob()
            || $entry->isScheduledTask()
            || $entry->hasMonitoredTag();
    }

    /**
     * Decide who may open Telescope.
     *
     * The parent implementation lets *anyone* through in a local environment
     * (`app()->environment('local') || Gate::check(...)`) — a guest included,
     * since the package's routes carry no auth middleware of their own. An
     * entry names members, emails and query bindings across every club, so
     * that bypass is dropped: the `viewTelescope` gate decides in every
     * environment, exactly like the log viewer. `config/telescope.php` adds
     * `auth` in front so a guest gets the login screen instead of a bare 403.
     */
    protected function authorization(): void
    {
        Telescope::auth(fn (Request $request): bool => (bool) $request->user()?->can('viewTelescope'));
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }
}
