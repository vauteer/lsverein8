<?php

namespace App\Pdf;

use RuntimeException;
use Throwable;

class SepaPdf extends BasePdf
{
    /** @var list<array<string, mixed>> */
    private array $payments;

    private string $description;

    private string $clubName;

    private float $total = 0;

    /**
     * Not "€": the sheet is written in ISO-8859-1, which has no euro sign —
     * that one lives in ISO-8859-15.
     */
    private string $currency = ' EUR';

    public function Header(): void
    {
        $cellHeight = 7;

        $this->SetFont('Arial', 'I', 14);
        $this->Cell(0, 7, $this->clubName, 0, 1, 'C');
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(0, $cellHeight, $this->description, 0, 1);
        $this->SetFont('Arial', 'I', 9);
        $this->Cell(40, $cellHeight, 'Kontoinhaber');
        $this->Cell(20, $cellHeight, 'Mandat');
        $this->Cell(20, $cellHeight, 'Datum');
        $this->Cell(80, $cellHeight, 'Zweck');
        $this->Cell(28, $cellHeight, 'Betrag', 0, 1, 'R');

        $this->useTableColors();
        $this->ruleLine();
        $this->SetY($this->GetY() + 0.2);
    }

    public function printEntities(): void
    {
        $this->useTableColors();
        $cellHeight = 7;

        foreach ($this->payments as $payment) {
            $this->stripeRow($cellHeight);

            try {
                $this->ClippedCell(40, $cellHeight, $this->latin1($payment['nm']));
                $this->ClippedCell(20, $cellHeight, $payment['mndtId']);
                $this->Cell(20, $cellHeight, formatDate($payment['dtOfSgntr']));
                $this->ClippedCell(80, $cellHeight, $this->latin1($payment['ustrd']));
                $this->ClippedCell(28, $cellHeight, $payment['instdAmt'].$this->currency, 0, 1, 'R');

                $this->ruleLine();

                $this->total += $payment['amount'];
            } catch (Throwable $throwable) {
                // Was `dd($payment)`, which is the worst possible answer here:
                // Debit::debit() deletes the collected rows *before* this runs,
                // so a dump-and-die loses the collection and leaves no file.
                // Naming the payment and rethrowing keeps what the dump was
                // for and lets the failure be logged like any other.
                throw new RuntimeException(
                    "Could not render the SEPA cover sheet for {$payment['nm']} ({$payment['mndtId']}).",
                    previous: $throwable
                );
            }
        }

        $this->printTotal();
    }

    /**
     * The sum of the collection, once, under the last row.
     *
     * It used to sit in Footer(), which Fpdf renders at every page break while
     * printEntities() is still adding to it — so on a collection running to
     * two pages the first page showed a part of the sum, looking like the
     * whole. Formatted the way the rest of the app formats money, except for
     * the currency: the sheet is written in ISO-8859-1, which has no € sign.
     */
    private function printTotal(): void
    {
        $cellHeight = 7;

        $this->SetFont('Arial', 'B', 9);
        $this->Cell(160, $cellHeight, 'Summe', 0, 0, 'R');
        $this->Cell(28, $cellHeight, number_format($this->total, 2, ',', '.').$this->currency, 0, 1, 'R');
    }

    /**
     * @param  list<array<string, mixed>>  $payments
     */
    public function getOutput(array $payments, string $description, string $clubName): string
    {
        $this->payments = $payments;
        $this->description = $description;
        $this->clubName = $clubName;

        $this->AliasNbPages();
        $this->AddPage();
        $this->SetFont('Arial', '', 9);

        $this->printEntities();

        return $this->render();
    }
}
