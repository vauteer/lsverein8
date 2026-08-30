<?php

namespace App\Pdf;

use App\Models\Member;
use Illuminate\Database\Eloquent\Collection;

class MemberRolesPdf extends BasePdf
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

        // 8 + 40 + 15 + 63.5 + 63.5 = 190, the width the rules and the shaded
        // rows use. It was 193, so the last column ran past the rule.
        $this->Cell(8, $cellHeight, '#');
        $this->Cell(40, $cellHeight, 'Name');
        $this->Cell(15, $cellHeight, 'Alter', align: 'C');
        $this->Cell(63.5, $cellHeight, 'Funktionen');
        $this->Cell(63.5, $cellHeight, 'Sparten', 0, 1);

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

            // member_id, not id: the club's own running number, the one the
            // CSV prints under the same heading.
            $this->Cell(8, $cellHeight, $member->member_id, 0, 0, 'R');
            $this->ClippedCell(40, $cellHeight, $this->latin1($member->surname.' '.$member->first_name));
            $this->Cell(15, $cellHeight, $member->age, 0, 0, 'C');
            $this->ClippedCell(63.5, $cellHeight, $this->latin1($member->currentRoles()));
            $this->ClippedCell(63.5, $cellHeight, $this->latin1($member->currentSections()), 0, 1);

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
