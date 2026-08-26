---
paths:
  - app/Http/Controllers/MemberExportController.php
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
