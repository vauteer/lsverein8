<?php

namespace App\Pdf;

use Fpdf\Fpdf;

/**
 * What every generated document here shares: how it is handed back, and the
 * table furniture the four of them draw the same way.
 *
 * The clipping helpers below come from lsverein7 and are what ClippedCell()
 * needs; Fpdf's own Cell() lets long text run over its neighbour.
 */
class BasePdf extends Fpdf
{
    /**
     * Which side of the zebra the next row is on.
     */
    protected bool $even = false;

    /**
     * The finished document as a string, which is the only way this app hands
     * one out: the controller wraps it in `response($content)`.
     *
     * Always through here, never `Output()` directly. `Fpdf::Output($dest,
     * $name)` *swaps its arguments* when `$name` is one character and `$dest`
     * is not, so `Output('', 'S')` and `Output('SEPA-Einzug', 'S')` both
     * happened to work while reading as though they did something else. Get it
     * wrong the other way — `Output('I', 'Liste.pdf')`, as lsverein7 did —
     * and Fpdf echoes the bytes to stdout, sends its own headers and returns
     * an empty string. One argument cannot be swapped with anything.
     */
    public function render(): string
    {
        return $this->Output('S');
    }

    /**
     * What the footer names as the producer.
     *
     * `config('app.name')`, not a literal: it read "LS-Verein " here while
     * APP_NAME said "LSVerein 8", so the app called itself two things. A
     * method rather than a property because a property initialiser cannot
     * call anything.
     */
    protected function footerLabel(): string
    {
        return $this->latin1(config('app.name'));
    }

    /**
     * The rule above the page number, then the stamp. Identical in all four
     * documents but for the label.
     */
    public function Footer(): void
    {
        $stamp = date('d.m.Y H:i').' Seite '.$this->PageNo().'/{nb}';
        $label = $this->footerLabel();

        $this->SetY(-15);
        $this->ruleLine();
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 7, $label === '' ? $stamp : $label.' '.$stamp, 0, 0, 'C');
    }

    /**
     * Text as Fpdf's core fonts want it.
     *
     * Arial and the rest are ISO-8859-1 only, so anything with an umlaut has
     * to be converted or it renders as mojibake. Every string that reaches a
     * cell from the database goes through here. Note what ISO-8859-1 cannot
     * carry: the € sign is in ISO-8859-15, which is why the amounts on the
     * SEPA sheet are suffixed "EUR".
     */
    protected function latin1(?string $text): string
    {
        return mb_convert_encoding((string) $text, 'ISO-8859-1', 'UTF-8');
    }

    /**
     * The grey a table's rules and its shaded rows are drawn in.
     */
    protected function useTableColors(): void
    {
        $this->SetDrawColor(150, 150, 150);
        $this->SetFillColor(240, 240, 240);
    }

    /**
     * Shade every other row, starting with the second.
     *
     * Call before writing the row's cells: it fills the band the cells are
     * then drawn over.
     */
    protected function stripeRow(float $cellHeight): void
    {
        $this->even = ! $this->even;

        if ($this->even) {
            $this->Rect(10, $this->GetY() + 0.2, 190, $cellHeight - 0.2, 'F');
        }
    }

    /**
     * A hairline across the printable width at the current position.
     */
    protected function ruleLine(): void
    {
        $y = $this->GetY();

        $this->Line(10, $y, 200, $y);
    }

    public function ClippingText(float $x, float $y, string $txt, bool $outline = false): void
    {
        $op = $outline ? 5 : 7;
        $this->_out(sprintf('q BT %.2f %.2f Td %d Tr (%s) Tj 0 Tr ET',
            $x * $this->k,
            ($this->h - $y) * $this->k,
            $op,
            $this->_escape($txt)));
    }

    public function ClippingRect(float $x, float $y, float $w, float $h, bool $outline = false): void
    {
        $op = $outline ? 'S' : 'n';
        $this->_out(sprintf('q %.2f %.2f %.2f %.2f re W %s',
            $x * $this->k,
            ($this->h - $y) * $this->k,
            $w * $this->k, -$h * $this->k,
            $op));
    }

    public function ClippingEllipse(float $x, float $y, float $rx, float $ry, bool $outline = false): void
    {
        $op = $outline ? 'S' : 'n';
        $lx = 4 / 3 * (M_SQRT2 - 1) * $rx;
        $ly = 4 / 3 * (M_SQRT2 - 1) * $ry;
        $k = $this->k;
        $h = $this->h;
        $this->_out(sprintf('q %.2f %.2f m %.2f %.2f %.2f %.2f %.2f %.2f c',
            ($x + $rx) * $k, ($h - $y) * $k,
            ($x + $rx) * $k, ($h - ($y - $ly)) * $k,
            ($x + $lx) * $k, ($h - ($y - $ry)) * $k,
            $x * $k, ($h - ($y - $ry)) * $k));
        $this->_out(sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c',
            ($x - $lx) * $k, ($h - ($y - $ry)) * $k,
            ($x - $rx) * $k, ($h - ($y - $ly)) * $k,
            ($x - $rx) * $k, ($h - $y) * $k));
        $this->_out(sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c',
            ($x - $rx) * $k, ($h - ($y + $ly)) * $k,
            ($x - $lx) * $k, ($h - ($y + $ry)) * $k,
            $x * $k, ($h - ($y + $ry)) * $k));
        $this->_out(sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c W %s',
            ($x + $lx) * $k, ($h - ($y + $ry)) * $k,
            ($x + $rx) * $k, ($h - ($y + $ly)) * $k,
            ($x + $rx) * $k, ($h - $y) * $k,
            $op));
    }

    public function UnsetClipping(): void
    {
        $this->_out('Q');
    }

    /**
     * A cell whose text is clipped to its own width, so a long name cannot
     * run over the column beside it.
     */
    public function ClippedCell(float $w, float $h = 0, string $txt = '', int|string $border = 0, int $ln = 0, string $align = '', bool|int $fill = 0, string $link = ''): void
    {
        if ($border || $fill || $this->y + $h > $this->PageBreakTrigger) {
            $this->Cell($w, $h, '', $border, 0, '', $fill);
            $this->x -= $w;
        }
        $this->ClippingRect($this->x, $this->y, $w, $h, false);
        $this->Cell($w, $h, $txt, '', $ln, $align, 0, $link);
        $this->UnsetClipping();
    }
}
