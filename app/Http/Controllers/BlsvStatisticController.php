<?php

namespace App\Http\Controllers;

use App\Models\Club;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The yearly report to the Bayerischer Landes-Sportverband: one CSV per BLSV
 * section, one covering every section, and the age statistic as a PDF.
 *
 * Building them is the page: a GET writes the files and then lists them, the
 * way lsverein7 did. That is deliberate rather than a POST plus a redirect —
 * nothing is stored beyond storage/downloads, and the numbers must reflect the
 * membership at the moment they are asked for, so a reload is meant to rebuild
 * rather than to show a stale set.
 */
class BlsvStatisticController extends Controller
{
    public function __invoke(Club $club): Response
    {
        // The 585-member club walks its members once per section; the default
        // 30s is tight once every section is populated.
        set_time_limit(120);

        return Inertia::render('clubs/BlsvStatistic', [
            'clubId' => $club->id,
            'clubName' => $club->name,
            // Der Stichtag ist der 1. Januar: Austritte zum 31.12. und
            // Eintritte zum 1.1. sind darin schon berücksichtigt.
            'keyDate' => formatDate(now()->startOfYear()),
            'downloads' => $club->getBLSVStatistic(),
        ]);
    }
}
