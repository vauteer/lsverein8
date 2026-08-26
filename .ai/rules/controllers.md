---
paths:
  - app/Http/Controllers/MemberExportController.php
  - app/Http/Controllers/BlsvStatisticController.php
---

# Controllers

## Exporte teilen die Auswahl mit der Liste — über SelectsMembers
Vier Formate (`App\Enums\MemberExport`: `pdf`, `roles`, `csv`, `vcf`), eine Route: `GET members/export/{format}`, aufgelöst per impliziter Enum-Bindung — ein unbekanntes Format ergibt 404 statt einer leeren Datei. Der Enum ersetzt `Member::EXPORT_FORMATS`, ein const-Array unübersetzter Labels, das nichts las und dem das Funktionen-PDF fehlte.

**`App\Concerns\SelectsMembers` ist der Kern.** Die Auswahl-Maschinerie (`selection()`, `applyFilter()`, `dynamicFilters()`, `filterLabel()`, `resolveYear()`) lag vorher privat im MemberController; sie ist herausgezogen, damit ein PDF oder CSV nie etwas anderes enthält als der Bildschirm, von dem aus es gestartet wurde. Insbesondere setzt `selection()` `Member::$_keyDate` — der Export rechnet Alter und Mitgliedsjahre also gegen dasselbe Stichjahr wie die Liste.

`filterLabel()` schlägt dynamische Auswahlen in `dynamicFilters()` nach statt sie ein zweites Mal abzubilden, damit eine Überschrift eine Auswahl nicht anders benennt als das Dropdown, das sie erzeugt hat. Dieselbe Beschriftung wird per `Str::slug()` zum Dateinamen (`ex-mitglieder-2020.csv`).

Festlegungen:

- **Das CSV ist ISO-8859-1**, wie die BLSV-Dateien — das erwarten die Tabellenkalkulationen am anderen Ende seit lsverein7. Umgewandelt wird **einmal am Schluss**, nicht pro Feld wie in lsverein7: ein Name außerhalb von Latin-1 kann so nicht die Spaltenzahl verschieben.
- **Berechtigung ist `viewAny`,** wie die Liste. Die Exporte enthalten nichts, was die Liste nicht ohnehin zeigt — keine Bankdaten, keine Beiträge.
- Im Frontend sind die Menüeinträge **einfache `<a download>`**, keine Inertia-Links: sonst sucht die SPA nach einer Komponente. Gleiche Begründung wie bei den SEPA-Downloads.
- Wayfinder: `MemberExportController.url(format, { query })` — der Query gehört ins **zweite** Argument, sonst TS2353.

## BLSV-Statistik: immer der aktuelle Verein, GET baut sie neu
`GET clubs/{club}/blsv-statistic` (BlsvStatisticController, `can('blsvStatistic', 'club')`) erzeugt die Jahresmeldung und listet die Dateien auf `clubs/BlsvStatistic.vue`.

**Der Vereinsparameter ist reine Kosmetik — gerechnet wird immer der aktuelle Verein.** `Member` und `Section` tragen ClubScope, `Club::getBLSVStatistic()` liest also die Mitglieder des Vereins, in dem der Benutzer *arbeitet*, benennt die Dateien aber nach `$this`. Deshalb verlangt `ClubPolicy::blsvStatistic()` ausdrücklich `$club->id === currentClubId()` und delegiert **nicht** an `update()` — sonst könnte root von der Seite eines anderen Vereins aus die Mitglieder des eigenen unter fremdem Namen ablegen. Root wechselt vorher, so wie es die Vereinsliste beim Mitgliederzähler ohnehin verlangt. Dazu `blsv_member` und `hasAdminRights()` (gleiche Schranke wie der Gate `downloadGeneratedFiles` — wer die Dateien nicht laden darf, soll sie nicht erzeugen).

Ein GET, der schreibt, ist Absicht (wie lsverein7): gespeichert wird nur nach storage/downloads, und die Zahlen sollen den Stand im Moment des Aufrufs zeigen. Ein Reload baut also neu, statt Altes zu zeigen.

`Club::writeDownload()` bildet den href mit `route('downloads.show', $filename, absolute: false)`, nicht mit `"/downloads/{$name}"` wie `Subscription::generateSepa()`: die CSV heißt `BE{Jahr}_{Spartenname}.csv`, und ein Spartenname darf laut SectionValidationRules Leerzeichen und Umlaute tragen. Nur `route()` kodiert die.

Reihenfolge der Downloads: Alters-Statistik (PDF), Alle Sparten (CSV), dann die Sparten in BLSV-Nummernfolge. lsverein7 lieferte durch ein `array_reverse()` die Sparten absteigend — das war ein Nebeneffekt, nicht gewollt. Leere Sparten bekommen gar keine Datei.

Einstieg ist die Vereinsseite (`clubs/Edit.vue`, Abschnitt neben dem Vereinsexport), sichtbar über die Prop `blsvStatistic`. Im Template ist der Wayfinder-Helper deshalb als `blsvStatisticRoute` importiert — Prop und Helper hießen sonst gleich.
