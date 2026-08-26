<?php

namespace App\Http\Controllers;

use App\Concerns\SelectsMembers;
use App\Enums\MemberExport;
use App\Models\Member;
use App\Pdf\MemberPdf;
use App\Pdf\MemberRolesPdf;
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
        $name = Str::slug($this->filterLabel($selection['filter']).' '.$selection['year']);

        // Str::slug already strips anything that could escape a directory, but
        // an empty slug would leave a bare ".csv".
        return ($name !== '' ? $name : 'export').'.'.$format->extension();
    }
}
