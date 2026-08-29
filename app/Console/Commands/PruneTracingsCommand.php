<?php

namespace App\Console\Commands;

use App\Enums\ActionType;
use App\Models\Tracing;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('app:prune-tracings {--months=12 : How many whole months to keep} {--dry-run : Report what would go without deleting}')]
#[Description('Delete tracings older than the retention window')]
class PruneTracingsCommand extends Command
{
    /**
     * The cut is aligned to a month boundary rather than taken as a rolling
     * `subMonths(12)` from today, so the dashboard's login card can never lose
     * the oldest bar it draws: the card shows twelve whole months back from the
     * start of this one, and this keeps everything from that boundary on.
     */
    public function handle(): int
    {
        $months = (int) $this->option('months');

        if ($months < 1) {
            $this->error('--months must be at least 1.');

            return self::FAILURE;
        }

        $cutoff = now()->startOfMonth()->subMonths($months);
        $spared = $this->lastLoginOfDormantAccounts($cutoff);

        $stale = Tracing::where('at', '<', $cutoff)->whereNotIn('id', $spared);
        $count = $stale->count();

        $kept = $spared->isEmpty()
            ? ''
            : ", keeping the last login of {$spared->count()} dormant account(s)";

        if ($count === 0) {
            $this->info("Nothing to prune before {$cutoff->toDateString()}{$kept}.");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Would delete {$count} tracings before {$cutoff->toDateString()}{$kept}.");

            return self::SUCCESS;
        }

        $deleted = $stale->delete();

        $this->info("Deleted {$deleted} tracings before {$cutoff->toDateString()}{$kept}.");

        return self::SUCCESS;
    }

    /**
     * The ids of the newest login of every account that has not signed in
     * within the window.
     *
     * Without this the user list would report those accounts as never having
     * signed in at all: `User::lastLogin()` and the `withLastLoginAt` scope
     * read this same table, so pruning the row silently rewrites a fact about
     * somebody rather than merely forgetting an old one. An account that *has*
     * signed in recently needs no exemption — its old rows are the ones the
     * retention window is actually about.
     *
     * @return Collection<int, int>
     */
    private function lastLoginOfDormantAccounts(CarbonInterface $cutoff): Collection
    {
        $signedInRecently = Tracing::query()
            ->actionType(ActionType::Login)
            ->where('at', '>=', $cutoff)
            ->distinct()
            ->pluck('user_id');

        return Tracing::query()
            ->actionType(ActionType::Login)
            ->where('at', '<', $cutoff)
            ->whereNotIn('user_id', $signedInRecently)
            // Ordered in SQL, deduplicated in PHP: "the newest row per group"
            // has no portable single query, and this only ever loads the old
            // logins of accounts that have gone quiet.
            ->orderByDesc('at')
            ->orderByDesc('id')
            ->get(['id', 'user_id'])
            ->unique('user_id')
            ->pluck('id');
    }
}
