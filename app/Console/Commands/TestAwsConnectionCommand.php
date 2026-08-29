<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('aws:test')]
#[Description('Test the S3 connection the backup upload uses')]
class TestAwsConnectionCommand extends Command
{
    /**
     * Ported from lscraft5. Here the s3 disk carries the database dumps that
     * Backup::upload() sends off when `backup.s3.enabled` is on, so this is the
     * way to check the credentials *before* switching that on.
     */
    public function handle(): int
    {
        $this->info('Starting AWS S3 connection test...');

        $testFilePath = 'aws-test-'.time().'.txt';

        try {
            $this->info('1. Attempting to upload a test file...');
            $testContent = 'This is a test file created at '.now();

            $success = Storage::disk('s3')->put($testFilePath, $testContent);

            if (! $success) {
                $this->error('× Failed to upload test file');

                return self::FAILURE;
            }

            $this->info('✓ Successfully uploaded test file to: '.$testFilePath);

            $this->info('2. All directories in root:');
            foreach (Storage::disk('s3')->directories('') as $directory) {
                $this->line('- '.$directory);
            }

            $this->info('3. All files in root:');
            foreach (Storage::disk('s3')->files('') as $file) {
                $this->line('- '.$file);
            }

            $this->info('4. File exists check:');
            $exists = Storage::disk('s3')->exists($testFilePath);
            $this->line("File {$testFilePath} exists: ".($exists ? 'Yes' : 'No'));

            $this->info('5. Full URL to file:');
            $this->line(Storage::disk('s3')->url($testFilePath));

            $this->info('6. Attempting to read the uploaded file...');
            $readContent = Storage::disk('s3')->get($testFilePath);

            if ($readContent === $testContent) {
                $this->info('✓ Successfully read the test file');
            } else {
                $this->error('× File content verification failed');
            }

            $this->info('7. Cleaning up - Deleting test file...');

            if (Storage::disk('s3')->delete($testFilePath)) {
                $this->info('✓ Successfully deleted test file');
            } else {
                $this->error('× Failed to delete test file');
            }
        } catch (Exception $e) {
            $this->error('An error occurred: '.$e->getMessage());
            $this->line('Stack trace:');
            $this->line($e->getTraceAsString());

            return self::FAILURE;
        }

        $this->info('AWS S3 test completed!');

        return self::SUCCESS;
    }
}
