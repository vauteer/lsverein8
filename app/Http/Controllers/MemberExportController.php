<?php

namespace App\Http\Controllers;

use App\Concerns\SelectsMembers;
use App\Enums\MemberExport;
use App\Models\Member;
use App\Pdf\MemberPdf;
use App\Pdf\MemberRolesPdf;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

/**
 * Hands the member list out as a file.
 *
 * Every format exports the selection the user is looking at, read against the
 * same key date — the selection machinery is shared with MemberController
 * through SelectsMembers, so a PDF can never disagree with the screen it was
 * started from.
 */
class MemberExportController extends Controller
{
    use SelectsMembers;

    /**
     * The BLSV's column order, shared by both formats it accepts.
     *
     * @var list<string>
     */
    private const array BLSV_COLUMNS = [
        'Titel', 'Name', 'Vorname', 'Namenszusatz',
        'Geschlecht', 'Geburtsdatum', 'Spartenkennzeichen',
    ];

    public function __invoke(Request $request, MemberExport $format): Response
    {
        $selection = $this->selection($request);

        // The BLSV file is only meaningful for a reporting club and only for
        // the current membership; a hand-typed URL gets a 404, not a file that
        // looks submittable and is not.
        abort_unless($format->isAvailableFor($selection['filter']), 404);

        $members = $selection['query']
            // The PDFs and the CSV read sections and roles per member; without
            // this that is two queries per row.
            ->with(['memberships', 'sections', 'roles'])
            ->get();

        $content = match ($format) {
            MemberExport::Addresses => (new MemberPdf('P', 'mm', 'A4'))->getOutput(
                $members,
                currentClub()->name,
                $this->leftHeadline($selection, $members->count()),
                $this->rightHeadline(),
            ),
            MemberExport::Roles => (new MemberRolesPdf('P', 'mm', 'A4'))->getOutput(
                $members,
                currentClub()->name,
                $this->leftHeadline($selection, $members->count()),
                $this->rightHeadline(),
            ),
            MemberExport::Csv => $this->csv($members),
            MemberExport::Blsv => $this->blsvCsv($members),
            MemberExport::BlsvExcel => $this->blsvExcel($members),
            MemberExport::VCard => view('vcards', [
                'members' => $members,
                'clubName' => currentClub()->name,
                'country' => __('Germany'),
            ])->render(),
        };

        return response($content)
            ->header('Content-Type', $format->contentType())
            ->header('Content-Length', (string) strlen($content))
            ->header(
                'Content-Disposition',
                'attachment; filename="'.$this->filename($selection, $format).'"'
            );
    }

    /**
     * @param  Collection<int, Member>  $members
     */
    private function csv(Collection $members): string
    {
        $handle = fopen('php://memory', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Could not open a memory stream for the CSV export.');
        }

        fputcsv($handle, [
            __('No.'), __('First name'), __('Surname'), __('Street'), __('Postcode'),
            __('City'), __('Date of birth'), __('Age'), __('Gender'),
            __('Years of membership'), __('Honour'), __('Sections'), __('Roles'),
        ]);

        foreach ($members as $member) {
            fputcsv($handle, [
                $member->member_id,
                $member->first_name,
                $member->surname,
                $member->street,
                $member->zipcode,
                $member->city,
                formatDate($member->birthday),
                $member->age,
                $member->gender->value,
                $member->membershipYears(),
                $member->honorThisYear() ?: '',
                $member->currentSections(),
                $member->currentRoles(),
            ]);
        }

        rewind($handle);
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        // Latin-1 like the BLSV files, which is what the spreadsheets on the
        // other end of this have always been fed. Converted once at the end
        // rather than per field as lsverein7 did, so a name outside Latin-1
        // cannot desynchronise the column count.
        return (string) mb_convert_encoding($content, 'ISO-8859-1', 'UTF-8');
    }

    /**
     * The rows the BLSV wants, in its column order: Titel, Name, Vorname,
     * Namenszusatz, Geschlecht, Geburtsdatum, Spartenkennzeichen. Titel and
     * Namenszusatz are always blank and are left to the writers.
     *
     * Built once for both BLSV formats — the Excel file and the CSV must never
     * describe the club differently.
     *
     * The two BLSV exports and the yearly statistic answer different
     * questions, which is why they coexist. Club::getBLSVStatistic() is the
     * yearly report and is always read at 1 January; these are for a
     * Nachmeldung during the year, so they are read at the list's key date —
     * normally today. A Nachmeldung is not a delta: the club uploads its whole
     * membership, which is why the formats are offered for the "Mitglieder"
     * selection only.
     *
     * A member appears once per BLSV section rather than once per person: the
     * Spartenkennzeichen is what the association counts. Sections without a
     * `blsv_id` produce no row at all — there is nothing to report them under.
     * In production every current member of the reporting club sits in at
     * least one such section, so nobody is dropped.
     *
     * @param  Collection<int, Member>  $members
     * @return list<array{surname: string, first_name: string, gender: string, birthday: CarbonInterface, blsv_id: int}>
     */
    private function blsvRows(Collection $members): array
    {
        $keyDate = Member::getKeyDate();
        $rows = [];

        foreach ($members as $member) {
            foreach ($this->blsvSectionIds($member, $keyDate) as $blsvId) {
                $rows[] = [
                    'surname' => $member->surname,
                    'first_name' => $member->first_name,
                    'gender' => $member->gender->blsvValue(),
                    'birthday' => $member->birthday,
                    'blsv_id' => $blsvId,
                ];
            }
        }

        return $rows;
    }

    /**
     * The BLSV numbers of the sections the member is in on the key date, in
     * ascending order.
     *
     * Deduplicated: a member may hold two rows for the same section with
     * different periods (four such cases in production), and the report must
     * not count them twice.
     *
     * @return list<int>
     */
    private function blsvSectionIds(Member $member, ?CarbonInterface $keyDate): array
    {
        $ids = [];

        foreach ($member->sections as $section) {
            if ($section->blsv_id !== null && inRange($keyDate, $section->pivot->from, $section->pivot->to)) {
                $ids[$section->blsv_id] = true;
            }
        }

        ksort($ids);

        return array_keys($ids);
    }

    /**
     * The same columns, separator, quoting and encoding as the
     * `BE{year}_Gesamt.csv` that Club::getBLSVStatistic() writes.
     *
     * @param  Collection<int, Member>  $members
     */
    private function blsvCsv(Collection $members): string
    {
        $csv = implode(';', self::BLSV_COLUMNS)."\r\n";

        foreach ($this->blsvRows($members) as $row) {
            $csv .= ';'.$row['surname'].';'.$row['first_name'].';;'.
                $row['gender'].';'.
                '"'.$row['birthday']->format('d.m.y').'";'.
                $row['blsv_id']."\r\n";
        }

        // Converted once at the end rather than per field as
        // Club::getBLSVStatistic() does, for the reason given in csv().
        return (string) mb_convert_encoding($csv, 'ISO-8859-1', 'UTF-8');
    }

    /**
     * The same rows as an .xlsx, laid out like the BLSV's own
     * `BE{year}_{month}_Mitgliederimport.xlsx` template.
     *
     * This is what the association actually wants; until now the club pasted
     * the CSV into that template by hand. The paste is exactly where it went
     * wrong, so the two columns that are *not* text carry real types here:
     * Geburtsdatum is a date serial with the built-in short-date format (id 14
     * — 'mm-dd-yy' is the OOXML spelling, which German Excel renders as
     * TT.MM.JJJJ), and Spartenkennzeichen is a number. Titel and Namenszusatz
     * stay empty cells, as in the template.
     *
     * The writer only writes to a real path (it builds a zip), so the file is
     * assembled in the system temp directory and read back — it is handed
     * straight to the browser and never belongs in storage/downloads.
     *
     * @param  Collection<int, Member>  $members
     */
    private function blsvExcel(Collection $members): string
    {
        // The built-in format, so styles.xml carries a plain numFmtId="14"
        // with no <numFmts> of its own, exactly like the BLSV template.
        $dateStyle = new Style(format: 'mm-dd-yy');

        $rows = [Row::fromValues(self::BLSV_COLUMNS)];

        foreach ($this->blsvRows($members) as $row) {
            $rows[] = new Row([
                0 => new Cell\EmptyCell(null),
                1 => new Cell\StringCell($row['surname']),
                2 => new Cell\StringCell($row['first_name']),
                3 => new Cell\EmptyCell(null),
                4 => new Cell\StringCell($row['gender']),
                5 => new Cell\DateTimeCell($row['birthday'], $dateStyle),
                6 => new Cell\NumericCell($row['blsv_id']),
            ]);
        }

        $path = tempnam(sys_get_temp_dir(), 'blsv-');

        if ($path === false) {
            throw new RuntimeException('Could not open a temporary file for the BLSV Excel export.');
        }

        try {
            // Calibri 11, the template's font — the association ignores it,
            // but a file that opens looking like the one before it is one
            // fewer thing for the user to wonder about.
            $writer = new Writer(new Options(new Style(fontSize: 11, fontName: 'Calibri')));
            $writer->openToFile($path);
            $writer->getCurrentSheet()->setName('Tabelle1');
            $writer->addRows($rows);
            $writer->close();

            $content = file_get_contents($path);
        } finally {
            @unlink($path);
        }

        if ($content === false) {
            throw new RuntimeException('Could not read back the BLSV Excel export.');
        }

        return $content;
    }

    /**
     * "Mitglieder 2026 (585 Personen)" — what the list currently shows.
     *
     * @param  array{filter: string, year: int, ...}  $selection
     */
    private function leftHeadline(array $selection, int $count): string
    {
        return $this->filterLabel($selection['filter'])
            .' '.$selection['year']
            .' ('.trans_choice('{1} :count person|[2,*] :count people', $count, ['count' => $count]).')';
    }

    private function rightHeadline(): string
    {
        return __('As of :date', ['date' => formatDate(Member::getKeyDate())]);
    }

    /**
     * A filename the user can tell apart in a downloads folder, built from the
     * selection rather than a fixed name.
     *
     * @param  array{filter: string, year: int, ...}  $selection
     */
    private function filename(array $selection, MemberExport $format): string
    {
        // "BE2026_Nachmeldung_2708.xlsx" — the association's BE{year} prefix,
        // then the day the report describes. The generic name below would be
        // "mitglieder-2026.csv", which is already the plain CSV export's, and
        // a club may hand in several Nachmeldungen in one year, so the date
        // is what tells them apart in a downloads folder.
        //
        // Year and day both come from the key date rather than the year alone,
        // so they can never disagree: a past year is read at its 31 December.
        if ($format->isBlsv()) {
            $keyDate = Member::getKeyDate();

            return 'BE'.$keyDate->format('Y').'_Nachmeldung_'.$keyDate->format('dm').'.'.$format->extension();
        }

        $name = Str::slug($this->filterLabel($selection['filter']).' '.$selection['year']);

        // Str::slug already strips anything that could escape a directory, but
        // an empty slug would leave a bare ".csv".
        return ($name !== '' ? $name : 'export').'.'.$format->extension();
    }
}
