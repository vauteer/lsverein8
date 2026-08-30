<?php

namespace App\Http\Controllers;

use App\BlsvMemberReport;
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
            MemberExport::Blsv => BlsvMemberReport::csv($this->blsvRows($members)),
            MemberExport::BlsvExcel => BlsvMemberReport::xlsx($this->blsvRows($members)),
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
                $member->honorYearReached() ?: '',
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
     * The rows of a Nachmeldung: every member of the selection, once per BLSV
     * section they are in on the key date. BlsvMemberReport renders them.
     *
     * The Nachmeldung and the yearly statistic answer different questions,
     * which is why both exist. Club::getBLSVStatistic() is the yearly report
     * and is always read at 1 January; this one describes the club today, so
     * it uses the list's key date. A Nachmeldung is not a delta — the club
     * uploads its whole membership — which is why the formats are offered for
     * the "Mitglieder" selection only.
     *
     * A member appears once per section rather than once per person: the
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
