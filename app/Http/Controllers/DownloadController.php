<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves what the app generates into storage/downloads: the SEPA file and its
 * cover sheet, and (once it has a screen) the BLSV statistic.
 *
 * The files are written with a `{club_id}_` prefix but the URLs carry only the
 * bare name, so a link is always resolved against the club the caller is
 * working in and can never name another club's file.
 */
class DownloadController extends Controller
{
    public function show(string $filename): BinaryFileResponse
    {
        // A route parameter never spans a slash, and every path this builds
        // starts with the club prefix, so the name cannot climb out of the
        // directory. basename() is the belt to that pair of braces.
        abort_unless($filename === basename($filename), 404);

        $path = storage_path('downloads/'.currentClubId().'_'.$filename);

        abort_unless(is_file($path), 404);

        return response()->download($path, $filename);
    }
}
