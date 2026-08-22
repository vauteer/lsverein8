<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('app:clear-log')]
#[Description('Clear storage/logs/laravel.log')]
class ClearLogCommand extends Command
{
    public function handle(): int
    {
        File::put(storage_path('logs/laravel.log'), '');

        $this->info('Log cleared');

        return self::SUCCESS;
    }
}
