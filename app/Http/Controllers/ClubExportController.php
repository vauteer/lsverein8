<?php

namespace App\Http\Controllers;

use App\ClubExport;
use App\Models\Club;
use Illuminate\Http\Response;

/**
 * Hands a club its own slice of the database as an SQL script.
 *
 * The club comes from the route, not from currentClub(): root may be working
 * in one club while looking at another's page, and exporting the wrong one
 * would be silent.
 */
class ClubExportController extends Controller
{
    public function __invoke(Club $club): Response
    {
        $export = new ClubExport($club);
        $sql = $export->toSql();

        return response($sql)
            ->header('Content-Type', 'application/sql')
            ->header('Content-Length', (string) strlen($sql))
            ->header('Content-Disposition', 'attachment; filename="'.$export->filename().'"');
    }
}
