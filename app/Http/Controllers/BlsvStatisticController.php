<?php

namespace App\Http\Controllers;

use App\Enums\MemberExport;
use App\Models\Club;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Everything the club sends to the Bayerischer Landes-Sportverband.
 *
 * Two reports, and they are not the same list. index() is the way in: it names
 * both and hands out the Nachmeldung directly, while the yearly Meldung sits
 * behind a button.
 *
 * That split is load-bearing. build() *writes* — a GET creates the files and
 * then lists them, the way lsverein7 did, deliberately rather than a POST plus
 * a redirect: nothing is stored beyond storage/downloads, and the numbers must
 * reflect the membership at the moment they are asked for, so a reload is
 * meant to rebuild rather than to show a stale set. That is fine behind a
 * button and wrong behind a sidebar entry, where every idle click would walk
 * every section of a 585-member club and write nine files.
 */
class BlsvStatisticController extends Controller
{
    /**
     * The way in from the sidebar. Writes nothing.
     */
    public function index(): Response
    {
        // No club parameter: the page is always the current club's, and the
        // reportToBlsv gate answers for exactly that one.
        $club = currentClub();

        return Inertia::render('clubs/Blsv', [
            'clubId' => $club->id,
            'clubName' => $club->name,
            // Die Jahresmeldung ist zum 1. Januar, die Nachmeldung zum heutigen
            // Stand — beide Daten stehen auf der Seite, damit niemand raten muss.
            'statisticKeyDate' => formatDate(now()->startOfYear()),
            'statisticYear' => now()->year,
            'reportKeyDate' => formatDate(now()),
            'reportFormats' => array_values(array_filter(
                MemberExport::optionsFor('members'),
                fn (array $format): bool => MemberExport::from($format['id'])->isBlsv(),
            )),
        ]);
    }

    public function build(Club $club): Response
    {
        // The 585-member club walks its members once per section; the default
        // 30s is tight once every section is populated.
        set_time_limit(120);

        return Inertia::render('clubs/BlsvStatistic', [
            'clubName' => $club->name,
            // Der Stichtag ist der 1. Januar: Austritte zum 31.12. und
            // Eintritte zum 1.1. sind darin schon berücksichtigt.
            'keyDate' => formatDate(now()->startOfYear()),
            'downloads' => $club->getBLSVStatistic(),
        ]);
    }
}
