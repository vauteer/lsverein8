<?php

namespace App;

use Carbon\CarbonInterface;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

/**
 * One member list in the layout the BLSV accepts, as Excel or as CSV.
 *
 * Two screens produce such a list, and they are not the same list: the yearly
 * Meldung (Club::buildBlsvStatistic(), read at 1 January and ordered by section)
 * and a Nachmeldung during the year (MemberExportController, read at the
 * member list's key date and ordered by member). Which rows go in is therefore
 * the caller's business; what they must never differ in is the layout, so only
 * the rendering lives here.
 *
 * @phpstan-type BlsvRow array{surname: string, first_name: string, gender: string, birthday: CarbonInterface, blsv_id: int}
 */
final class BlsvMemberReport
{
    /**
     * The association's column order. Titel and Namenszusatz are always blank
     * — the app holds neither — but the columns have to be there.
     *
     * @var list<string>
     */
    public const array COLUMNS = [
        'Titel', 'Name', 'Vorname', 'Namenszusatz',
        'Geschlecht', 'Geburtsdatum', 'Spartenkennzeichen',
    ];

    /**
     * Semicolons, CRLF, a quoted two-digit year and ISO-8859-1 — what the
     * BLSV files have looked like since lsverein7.
     *
     * The per-section files carry no header, only the ones covering the whole
     * club do; hence the flag.
     *
     * @param  list<BlsvRow>  $rows
     */
    public static function csv(array $rows, bool $withHeader = true): string
    {
        $csv = $withHeader ? implode(';', self::COLUMNS)."\r\n" : '';

        foreach ($rows as $row) {
            $csv .= ';'.$row['surname'].';'.$row['first_name'].';;'.
                $row['gender'].';'.
                '"'.$row['birthday']->format('d.m.y').'";'.
                $row['blsv_id']."\r\n";
        }

        // Converted once at the end rather than per field as lsverein7 did, so
        // a name outside Latin-1 cannot desynchronise the column count.
        return (string) mb_convert_encoding($csv, 'ISO-8859-1', 'UTF-8');
    }

    /**
     * The same rows laid out like the BLSV's own Mitgliederimport template.
     *
     * This is what the association actually wants; the club used to paste the
     * CSV into that template by hand, and the paste is exactly where it went
     * wrong. So the two columns that are *not* text carry real types here:
     * Geburtsdatum is a date serial with the built-in short-date format (id 14
     * — 'mm-dd-yy' is the OOXML spelling, which German Excel renders as
     * TT.MM.JJJJ), and Spartenkennzeichen is a number. Titel and Namenszusatz
     * stay empty cells, as in the template.
     *
     * @param  list<BlsvRow>  $rows
     */
    public static function xlsx(array $rows): string
    {
        // The built-in format, so styles.xml carries a plain numFmtId="14"
        // with no <numFmts> of its own, exactly like the BLSV template.
        $dateStyle = new Style(format: 'mm-dd-yy');

        $sheet = [Row::fromValues(self::COLUMNS)];

        foreach ($rows as $row) {
            $sheet[] = new Row([
                0 => new Cell\EmptyCell(null),
                1 => new Cell\StringCell($row['surname']),
                2 => new Cell\StringCell($row['first_name']),
                3 => new Cell\EmptyCell(null),
                4 => new Cell\StringCell($row['gender']),
                5 => new Cell\DateTimeCell($row['birthday'], $dateStyle),
                6 => new Cell\NumericCell($row['blsv_id']),
            ]);
        }

        // The writer only writes to a real path — it builds a zip — so the
        // file is assembled in the system temp directory and read back. Both
        // callers hand it straight on rather than keeping it.
        $path = tempnam(sys_get_temp_dir(), 'blsv-');

        if ($path === false) {
            throw new RuntimeException('Could not open a temporary file for the BLSV Excel report.');
        }

        try {
            // Calibri 11, the template's font — the association ignores it,
            // but a file that opens looking like the one before it is one
            // fewer thing for the user to wonder about.
            $writer = new Writer(new Options(new Style(fontSize: 11, fontName: 'Calibri')));
            $writer->openToFile($path);
            $writer->getCurrentSheet()->setName('Tabelle1');
            $writer->addRows($sheet);
            $writer->close();

            $content = file_get_contents($path);
        } finally {
            @unlink($path);
        }

        if ($content === false) {
            throw new RuntimeException('Could not read back the BLSV Excel report.');
        }

        return $content;
    }
}
