<?php

namespace App\Pdf;

use App\Models\Member;
use Illuminate\Database\Eloquent\Collection;

class MemberPdf extends BasePdf
{
    /** @var Collection<int, Member> */
    private Collection $members;

    private string $leftHeadline;

    private string $rightHeadline;

    private string $clubName;

    public function Header(): void
    {
        $cellHeight = 7;

        $this->SetFont('Arial', 'I', 14);
        $this->Cell(0, 7, $this->clubName, 0, 1, 'C');
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(140, $cellHeight, $this->leftHeadline);
        $this->Cell(50, $cellHeight, $this->rightHeadline, 0, 1, 'R');
        $this->SetFont('Arial', 'I', 9);

        // The same widths the rows below use, so the headings sit over their
        // own columns and the last one ends on the rule rather than 8mm past
        // it: 8 + 40 + (18+7) + (18+7) + 60 + 32 = 190.
        $this->Cell(8, $cellHeight, '#');
        $this->Cell(40, $cellHeight, 'Name');
        $this->Cell(25, $cellHeight, 'geboren');
        $this->Cell(25, $cellHeight, 'Eintritt');
        $this->Cell(60, $cellHeight, 'Adresse');
        $this->Cell(32, $cellHeight, 'Sparten', 0, 1);

        $this->useTableColors();
        $this->ruleLine();
        $this->SetY($this->GetY() + 0.2);
    }

    public function printEntities(): void
    {
        $this->useTableColors();
        $cellHeight = 7;

        foreach ($this->members as $member) {
            $this->stripeRow($cellHeight);

            // member_id, not id: the club's own running number, which is what
            // the CSV prints under the same heading and what the club knows a
            // member by. They differ for 238 of the 580 live members.
            $this->Cell(8, $cellHeight, $member->member_id, 0, 0, 'R');
            $this->ClippedCell(40, $cellHeight, $this->latin1($member->surname.' '.$member->first_name));
            $this->Cell(18, $cellHeight, formatDate($member->birthday));
            $this->Cell(7, $cellHeight, $member->age, 0, 0, 'R');
            $this->Cell(18, $cellHeight, formatDate($member->entry()));
            $this->Cell(7, $cellHeight, $member->membershipYears(), 0, 0, 'R');
            $this->ClippedCell(60, $cellHeight,
                $this->latin1($member->zipcode.' '.$member->city.' '.$member->street));
            $this->ClippedCell(32, $cellHeight, $this->latin1($member->currentSections()), 0, 1);

            $this->ruleLine();
        }
    }

    /**
     * @param  Collection<int, Member>  $members
     */
    public function getOutput(Collection $members, string $clubName, string $leftHeadline, string $rightHeadline): string
    {
        $this->members = $members;
        $this->leftHeadline = $leftHeadline;
        $this->rightHeadline = $rightHeadline;
        $this->clubName = $clubName;

        $this->AliasNbPages();
        $this->AddPage();
        $this->SetFont('Arial', '', 9);

        $this->printEntities();

        return $this->render();
    }
}
