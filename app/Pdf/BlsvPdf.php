<?php

namespace App\Pdf;

use Carbon\CarbonInterface;

class BlsvPdf extends BasePdf
{
    /**
     * No producer in the footer, unlike the club's own lists: this sheet goes
     * to the association.
     */
    protected function footerLabel(): string
    {
        return '';
    }

    /** @var array<array-key, array{name: string, rows: array<int, array{m: int, w: int, d: int}>}> */
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

    public function Header(): void
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

    public function printCompressedStat(): void
    {
        $heads = ['bis 5 Jahre', '6 bis 13 Jahre', '14 bis 17 Jahre', '18-26 Jahre',
            '27-40 Jahre', '41 bis 60 Jahre', 'Ab 61 Jahre'];
        $labelWidth = 40;
        $columnWidth = 25;
        $cellHeight = 7;
        $rightMargin = 45;
        $sums = ['m' => 0, 'w' => 0, 'd' => 0];

        $this->useTableColors();
        $this->SetY(80);

        $this->setX($rightMargin);
        $this->Cell($labelWidth, $cellHeight, 'Altersgruppe');
        $this->Cell($columnWidth, $cellHeight, mb_convert_encoding('Männlich', 'ISO-8859-1', 'UTF-8'), 0, 0, 'R');
        $this->Cell($columnWidth, $cellHeight, 'Weiblich', 0, 0, 'R');
        if ($this->showsDiverse) {
            $this->Cell($columnWidth, $cellHeight, 'Divers', 0, 0, 'R');
        }
        $this->Cell($columnWidth, $cellHeight, 'Zusammen', 0, 1, 'R');
        $this->ruleLine();

        for ($i = 0; $i < 7; $i++) {
            if ($i % 2 == 0) {
                $tmp = $this->GetY() + 0.2;
                $this->Rect(10, $tmp, 190, $cellHeight - 0.2, 'F');
            }

            $row = $this->stats[-1]['rows'][$i];
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

        $this->ruleLine();
        $this->setX($rightMargin);
        $this->Cell($labelWidth, $cellHeight, 'Gesamt');
        $this->Cell($columnWidth, $cellHeight, $sums['m'], 0, 0, 'R');
        $this->Cell($columnWidth, $cellHeight, $sums['w'], 0, 0, 'R');
        if ($this->showsDiverse) {
            $this->Cell($columnWidth, $cellHeight, $sums['d'], 0, 0, 'R');
        }
        $this->Cell($columnWidth, $cellHeight, array_sum($sums), 0, 1, 'R');
    }

    public function printSectionStats(): void
    {
        $this->useTableColors();
        $cellHeight = 7;
        $reducedHeight = 4;
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

        $this->ruleLine();

        foreach ($this->stats as $key => $stat) {
            $total = 0;
            $this->stripeRow($cellHeight);
            $this->Cell(7, $cellHeight, $key);
            $this->Cell(23, $cellHeight, $stat['name']);

            for ($i = 0; $i < 7; $i++) {
                foreach ($genders as $gender => $head) {
                    $count = $stat['rows'][$i][$gender];
                    $total += $count;
                    $this->Cell($columnWidth, $cellHeight, $count ?: '', 0, 0, 'R');
                }
            }
            $this->Cell(18, $cellHeight, $total, 0, 1, 'R');
        }

        $this->ruleLine();
    }

    /**
     * Whether any age group of any section holds a diverse member.
     *
     * @param  array<array-key, array{name: string, rows: array<int, array{m: int, w: int, d: int}>}>  $stats
     */
    private function hasDiverseMember(array $stats): bool
    {
        foreach ($stats as $stat) {
            for ($i = 0; $i < 7; $i++) {
                if ($stat['rows'][$i]['d'] > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<array-key, array{name: string, rows: array<int, array{m: int, w: int, d: int}>}>  $stats  keyed by blsv_id, with -1 holding the club totals
     */
    public function getOutput(array $stats, CarbonInterface $keyDate, string $clubName): string
    {
        $this->stats = $stats;
        $this->keyDate = $keyDate;
        $this->clubName = $clubName;
        $this->showsDiverse = $this->hasDiverseMember($stats);

        $this->AliasNbPages();
        $this->AddPage();
        $this->SetFont('Arial', '', 9);

        $this->printCompressedStat();

        // The club totals are row -1, which page one has just printed. Drop
        // them before page two, which walks the same array and would otherwise
        // list them as a section of their own.
        unset($this->stats[-1]);

        $this->AddPage();

        $this->printSectionStats();

        return $this->render();
    }
}
