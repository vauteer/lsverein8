---
paths:
  - 'app/Pdf/**'
---

# Pdf

## Fpdf::Output — immer 'S', und auf die Argumentreihenfolge achten
`Fpdf::Output($dest, $name)` **vertauscht die Argumente**, wenn `strlen($name) == 1 && strlen($dest) != 1`. Deshalb liefern `Output('SEPA-Einzug', 'S')` (SepaPdf) und `Output('', 'S')` (BlsvPdf) beide korrekt einen String zurück — dort greift der Tausch.

`MemberPdf` und `MemberRolesPdf` riefen aus lsverein7 `Output('I', 'Liste.pdf')` auf. Da `$name` neun Zeichen hat, greift der Tausch **nicht**, `$dest` bleibt `'I'`: Fpdf gibt das PDF per `echo` auf die Standardausgabe, setzt eigene `Content-Type`- und `Content-Disposition`-Header und gibt `''` zurück. In lsverein7 fiel das nicht auf, weil die Bytes über den Output-Buffer trotzdem beim Browser landeten. Unter Inertia/Laravel wäre das ein leerer Response-Body plus PDF-Bytes daneben gewesen.

Seit 2026-08-30 ruft **keine Klasse mehr `Output()` selbst**: `BasePdf::render()` macht das eine `Output('S')` für alle vier, ohne zweites Argument, damit der Tausch gar nicht erst greifen kann. `getOutput()` endet überall mit `return $this->render();`. Nie `'I'` oder `'D'` — die Auslieferung macht der Controller über `response($content)`, nicht Fpdf.

`tests/Feature/MemberExportTest.php` prüft, dass beide PDFs mit `%PDF-` beginnen; das wird rot, wenn jemand auf `'I'` zurückgeht.

`app/Pdf` **war** von phpstan ausgenommen; seit 2026-08-30 nicht mehr. Alle vier Klassen sind vollständig typisiert, die Ausnahme in `phpstan.neon` ist weg, und so etwas fällt hier jetzt statisch auf.

## Zwei Fehler, die hier lange standen (2026-08-30)
**`dd($payment)` im catch von `SepaPdf::printEntities()`.** Der Pfad läuft *nachdem* `Debit::debit()` die eingezogenen Zeilen gelöscht hat: ein Dump-and-die verlor den ganzen Einzug und ließ keine Datei zurück. Jetzt eine `RuntimeException`, die die Zahlung benennt und die ursprüngliche Exception als `previous` mitführt.

**`MemberPdf` und `MemberRolesPdf` druckten `$member->id`** unter der Spalte `#`, also den Primärschlüssel. Der CSV-Export druckt an derselben Stelle `member_id`, die eigene laufende Nummer des Vereins — und die beiden laufen bei 238 der 580 produktiven Mitglieder auseinander. `MemberExportTest` pinnt es jetzt mit einem Mitglied, dessen `member_id` 4242 ist.

Die Summe des SEPA-Deckblatts stand in `Footer()` und war deshalb auf jeder Seite außer der letzten ein Zwischenstand. Sie steht jetzt einmal unter der letzten Zeile (`printTotal()`), im Zahlenformat der App — aber mit `EUR` statt `€`, weil das Blatt in ISO-8859-1 geschrieben wird und das Eurozeichen darin nicht existiert.

## BasePdf hält, was alle vier gemeinsam haben (2026-08-30)
`Footer()`, die Zebra-Streifen (`stripeRow()`), die Trennlinie (`ruleLine()`), die Tabellenfarben (`useTableColors()`), `latin1()` und `render()` liegen dort, statt drei- bis viermal kopiert zu sein. `footerLabel()` ist die einzige Abweichung: `config('app.name')` bei den Listen des Vereins, leer bei der BLSV-Meldung, die an den Verband geht. Bis 2026-08-30 stand dort das Literal „LS-Verein ", während `APP_NAME` „LSVerein 8" sagte — die App nannte sich also zweierlei.

**`latin1()` ist Pflicht für jeden Text aus der Datenbank.** Fpdfs Kernschriften können nur ISO-8859-1. Was ISO-8859-1 *nicht* kann: das Eurozeichen — das steht in ISO-8859-15. Deshalb steht auf dem SEPA-Deckblatt „EUR" und nicht „€", und `formatAmount()` ist dort nicht benutzbar.

**Die Spaltenbreiten müssen 190 ergeben** (10 bis 200, die Breite der Linien und der grauen Bänder). Die Kopfzeilen von `MemberPdf` (198) und `MemberRolesPdf` (193) taten das nicht und liefen über die Linie hinaus; jetzt 8+40+25+25+60+32 bzw. 8+40+15+63,5+63,5.

**Die Statistik-Struktur trägt Name und Zeilen getrennt** (`['name' => …, 'rows' => …]`). Vorher war der Name per `$stat + ['name' => …]` unter die sieben Altersgruppen gemischt — für phpstan (und für Menschen) nicht mehr unterscheidbar von einer Zeile. `Gender::blsvValue()` ist deshalb auf `'m'|'w'|'d'` verengt: es sind die Schlüssel jeder Zeile.
