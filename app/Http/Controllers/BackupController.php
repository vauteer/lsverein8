<?php

namespace App\Http\Controllers;

use App\Backup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Number;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Every action here is gated by `manageBackups` on the route, which is
 * root-only: a backup is the whole installation, not one club's data.
 */
class BackupController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('backups/Index', [
            'backups' => collect(Backup::all())->map(function (array $backup) {
                $date = Carbon::createFromTimestamp($backup['timestamp'], config('app.timezone'));

                return [
                    'id' => $backup['id'],
                    'date' => $date->format('d.m.Y H:i'),
                    'filename' => $backup['filename'],
                    'age' => $date->diffForHumans(),
                    'size' => Number::fileSize(File::size(Backup::path($backup['filename'])), precision: 1),
                ];
            })->all(),
        ]);
    }

    public function store(): RedirectResponse
    {
        $result = Backup::create();

        Inertia::flash('toast', match (true) {
            $result['success'] => ['type' => 'success', 'message' => __('Backup created.')],
            $result['skipped'] ?? false => ['type' => 'info', 'message' => __('No changes since the last backup.')],
            default => ['type' => 'error', 'message' => __('The backup could not be created.')]
        });

        return to_route('backups.index');
    }

    public function download(string $filename): BinaryFileResponse
    {
        return response()->download($this->validatedPath($filename));
    }

    /**
     * Replace the database with the given backup, taking a safety backup first.
     */
    public function restore(string $filename): RedirectResponse
    {
        $this->validatedPath($filename);

        $success = Backup::restore($filename);

        Inertia::flash('toast', $success
            ? ['type' => 'success', 'message' => __('Backup restored.')]
            : ['type' => 'error', 'message' => __('The backup could not be restored.')]);

        return to_route('backups.index');
    }

    public function destroy(string $filename): RedirectResponse
    {
        File::delete($this->validatedPath($filename));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Backup deleted.')]);

        return to_route('backups.index');
    }

    /**
     * The full path of the given backup, aborting if it is not an existing
     * backup file. Guards against path traversal in the filename parameter.
     */
    private function validatedPath(string $filename): string
    {
        abort_unless(collect(Backup::all())->contains('filename', $filename), 404);

        return Backup::path($filename);
    }
}
