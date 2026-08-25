<?php

namespace App\Console\Commands;

use App\Backup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:backup {--force : Create a backup even without changes}')]
#[Description('Create a database backup')]
class BackupCommand extends Command
{
    public function handle(): int
    {
        if (! $this->option('force') && ! Backup::isDirty()) {
            $this->info('No changes since the last backup.');

            return self::SUCCESS;
        }

        $this->info('Creating backup …');

        $start = microtime(true);

        $result = Backup::create(force: true);

        if (! $result['success']) {
            $this->error($result['message']);

            return self::FAILURE;
        }

        $elapsed = number_format((microtime(true) - $start) * 1000).'ms';
        $this->info("Backup created in {$elapsed}: {$result['file']}");

        return self::SUCCESS;
    }
}
