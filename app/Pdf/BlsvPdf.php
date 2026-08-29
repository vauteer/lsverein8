<?php

namespace App\Pdf;

use Carbon\CarbonInterface;

class BlsvPdf extends BasePdf
{
    private array $stats;

    private CarbonInterface $keyDate;

    private string $clubName;

    /**
     * Whether any age group holds a diverse member.
     *
     * The BLSV sheet carried two gender columns for as long as the club has
     * been submitting it. A third one is only cut in when it has something to
     * show, so a club without a diverse member keeps the sheet it knows.
     */
    private bool $showsDiverse = false;

    public function Header()
    {
        $cellHeight = 7;

        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, $cellHeight, mb_convert_encoding('Jahresstatistik für den Landessportverband', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');

        $this->SetFont('Arial', '', 12);
        $this->Cell(0, $cellHeight, 'Statistik zum '.formatDate($this->keyDate), 0, 1, 'C');

        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, $cellHeight, mb_convert_encoding($this->clubName, 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');

        $this->SetFont('Arial', '', 12);
        if ($this->PageNo() == 1) {
            $this->Cell(0, $cellHeight, 'Komprimierte Altersstatistik', 0, 1, 'C');
        } else {
            $this->Cell(0, $cellHeight, 'Altersstatistik nach Abteilungen', 0, 1, 'C');

        }

        $this->SetDrawColor(150, 150, 150);
    }

    public function Footer()
    {
        $this->SetY(-15);
        $tmp = $this->GetY();
        $this->Line(10, $tmp, 200, $tmp);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 7, date('d.m.Y H:i').' Seite '.$this->PageNo().'/{nb}', 0, 0, 'C');
    }

    public function printCompressedStat()
    {
        $heads = ['bis 5 Jahre', '6 bis 13 Jahre', '14 bis 17 Jahre', '18-26 Jahre',
            '27-40 Jahre', '41 bis 60 Jahre', 'Ab 61 Jahre'];
        $labelWidth = 40;
        $columnWidth = 25;
        $cellHeight = 7;
        $rightMargin = 45;
        $sums = ['m' => 0, 'w' => 0, 'd' => 0];

        $this->SetDrawColor(150, 150, 150);
        $this->SetFillColor(240, 240, 240);
        $this->SetY(80);

        $this->setX($rightMargin);
        $this->Cell($labelWidth, $cellHeight, 'Altersgruppe');
        $this->Cell($columnWidth, $cellHeight, mb_convert_encoding('Männlich', 'ISO-8859-1', 'UTF-8'), 0, 0, 'R');
        $this->Cell($columnWidth, $cellHeight, 'Weiblich', 0, 0, 'R');
        if ($this->showsDiverse) {
            $this->Cell($columnWidth, $cellHeight, 'Divers', 0, 0, 'R');
        }
        $this->Cell($columnWidth, $cellHeight, 'Zusammen', 0, 1, 'R');
        $tmp = $this->GetY();
        $this->Line(10, $tmp, 200, $tmp);

        for ($i = 0; $i < 7; $i++) {
            if ($i % 2 == 0) {
                $tmp = $this->GetY() + 0.2;
                $this->Rect(10, $tmp, 190, $cellHeight - 0.2, 'F');
            }

            $row = $this->stats[-1][$i];
            foreach ($sums as $gender => $sum) {
                $sums[$gender] = $sum + $row[$gender];
            }

            $this->setX($rightMargin);
            $this->Cell($labelWidth, $cellHeight, $heads[$i]);
            $this->Cell($columnWidth, $cellHeight, $row['m'], 0, 0, 'R');
            $this->Cell($columnWidth, $cellHeight, $row['w'], 0, 0, 'R');
            if ($this->showsDiverse) {
                $this->Cell($columnWidth, $cellHeight, $row['d'], 0, 0, 'R');
            }
            $this->Cell($columnWidth, $cellHeight, array_sum($row), 0, 1, 'R');
        }

        $tmp = $this->GetY();
        $this->Line(10, $tmp, 200, $tmp);
        $this->setX($rightMargin);
        $this->Cell($labelWidth, $cellHeight, 'Gesamt');
        $this->Cell($columnWidth, $cellHeight, $sums['m'], 0, 0, 'R');
        $this->Cell($columnWidth, $cellHeight, $sums['w'], 0, 0, 'R');
        if ($this->showsDiverse) {
            $this->Cell($columnWidth, $cellHeight, $sums['d'], 0, 0, 'R');
        }
        $this->Cell($columnWidth, $cellHeight, array_sum($sums), 0, 1, 'R');

    }

    public function printSectionStats()
    {
        $this->SetDrawColor(150, 150, 150);
        $this->SetFillColor(240, 240, 240);
        $cellHeight = 7;
        $reducedHeight = 4;
        $even = false;
        $this->SetY(70);

        $genders = $this->showsDiverse ? ['m' => 'M', 'w' => 'W', 'd' => 'D'] : ['m' => 'M', 'w' => 'W'];
        // Seven age groups have to fit between the section name and the total,
        // so the third column is paid for by narrowing all of them rather than
        // by running off the page.
        $columnWidth = $this->showsDiverse ? 6.7 : 10;
        $groupWidth = $columnWidth * count($genders);

        $this->Cell(5, $reducedHeight, '');
        $this->Cell(23, $reducedHeight, '');
        foreach (['bis 5', '6-13', '14-17', '18-26', '27-40', '41-60', 'ab 61'] as $index => $head) {
            $this->Cell($groupWidth, $reducedHeight, $head, 0, $index === 6 ? 1 : 0, 'C');
        }

        $this->Cell(7, $cellHeight, '');
        $this->Cell(23, $cellHeight, 'Abteilung');
        for ($i = 0; $i < 7; $i++) {
            foreach ($genders as $head) {
                $this->Cell($columnWidth, $cellHeight, $head, 0, 0, 'R');
            }
        }
        $this->Cell(18, $cellHeight, 'Gesamt', 0, 1, 'R');

        $tmp = $this->GetY();
        $this->Line(10, $tmp, 200, $tmp);

        foreach ($this->stats as $key => $stat) {
            $total = 0;
            $even = ! $even;
            if ($even) {
                $tmp = $this->GetY() + 0.2;
                $this->Rect(10, $tmp, 190, $cellHeight - 0.2, 'F');
            }
            $this->Cell(7, $cellHeight, $key);
            $this->Cell(23, $cellHeight, $stat['name']);

            for ($i = 0; $i < 7; $i++) {
                foreach ($genders as $gender => $head) {
                    $count = $stat[$i][$gender];
                    $total += $count;
                    $this->Cell($columnWidth, $cellHeight, $count ?: '', 0, 0, 'R');
                }
            }
            $this->Cell(18, $cellHeight, $total, 0, 1, 'R');

        }

        $tmp = $this->GetY();
        $this->Line(10, $tmp, 200, $tmp);
    }

    /**
     * Whether any age group of any section holds a diverse member.
     */
    private function hasDiverseMember(array $stats): bool
    {
        foreach ($stats as $stat) {
            for ($i = 0; $i < 7; $i++) {
                if (($stat[$i]['d'] ?? 0) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getOutput($stats, $year, $clubName)
    {
        $this->stats = $stats;
        $this->keyDate = $year;
        $this->clubName = $clubName;
        $this->showsDiverse = $this->hasDiverseMember($stats);

        $this->AliasNbPages();
        $this->AddPage();
        $this->SetFont('Arial', '', 9);

        $this->printCompressedStat();

        unset($this->stats[-1]);

        $this->AddPage();

        $this->printSectionStats();

        return $this->Output('', 'S');
    }
}
