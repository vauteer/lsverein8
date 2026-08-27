---
paths:
  - 'app/Http/Controllers/Members/**'
---

# Members

## Die sechs Relationen: Pivot-Id adressiert die Zeile, nicht die Fremd-Id
Die sechs Relationen eines Mitglieds werden **auf der Mitgliederseite (`members/{id}`) bearbeitet, nicht im Bearbeitungsformular** — siehe die UI-Regel unter `resources/js/**`.

Drei Formen, ein Controller je Relation unter `app/Http/Controllers/Members/`, jeweils nur store/update/destroy (Liste und Formular liefert die Seite):

| Form | Relationen | Felder |
| --- | --- | --- |
| Zeitraum | Mitgliedschaften, Abteilungen, Funktionen, Inventar | `from`, `to` (offen = läuft noch), `memo` |
| Datum | Ehrungen | `date`, `memo` |
| nur Notiz | Beiträge | `memo` |

**Die Route adressiert die Pivot-Zeile über ihre eigene `id`, nie über die Fremd-Id.** In Produktion gibt es je 4 Fälle, in denen dasselbe Mitglied dieselbe Abteilung bzw. dieselbe Funktion zweimal mit verschiedenen Zeiträumen hat — `section_id` identifiziert eine Zeile also nicht. Alle Pivots haben `public $incrementing = true`.

**Route Model Binding ist für die Pivot-Zeile bewusst nicht benutzt**: es löst allein über den Primärschlüssel auf, damit wäre die Zeile eines *anderen* Mitglieds auffindbar und änderbar. Stattdessen `$pivot::query()->where('member_id', $member->id)->findOrFail($row)` in jedem Controller. Test pinnt das („a row belonging to another member cannot be edited through this one" → 404).

Weitere Festlegungen:

- **Mitgliedschaften haben keine Vereinsauswahl.** Alle 593 `club_member`-Zeilen zeigen auf den eigenen Verein des Mitglieds, und eine fremde wäre hinter dem ClubScope ohnehin unsichtbar. Der Controller setzt `currentClubId()`. Mehrere Zeiträume sind aber nötig: 8 Mitglieder sind ausgetreten und wieder eingetreten, `Member::membershipYears()` summiert.
- **`belongsToClubRule()` (in `MemberRelationRules`) klubt `exists` von Hand.** `exists` erbt keinen Model-Scope. `shared: true` für Abteilungen/Funktionen/Ehrungen, deren `club_id` nullable ist; Beiträge und Inventar sind NOT NULL und bekommen es nicht.
- **Die Inventar-Routen tragen zusätzlich `can('viewAny', Item::class)`,** damit ein Verein ohne Inventar auch nichts ausgeben kann — nicht nur der Abschnitt fehlt.
- Alle Aktionen antworten mit `back()`, nicht mit einer benannten Route: Aufrufer ist die Mitgliederseite, die so ihren Listenzustand behält.
- Store und Update teilen sich ein Request je Relation — es gibt hier keine Unique-Regel, die sich unterscheiden würde.

## BLSV-Verein: die letzte laufende Sparte lässt sich nicht entfernen
Seit 2026-08-27: In einem Verein mit `blsv_member` muss ein Mitglied, dessen Mitgliedschaft noch offen ist, in mindestens einer laufenden Sparte sein. `MemberSectionController::isLastActiveSection()` sperrt `update` (Schließen über `to`) und `destroy` der letzten solchen Zeile.

**Warum überhaupt:** `Club::getBLSVStatistic()` baut die Meldung sparteweise. Wer in keiner Sparte ist, taucht in der Datei gar nicht auf — er fehlt dem Verband, ohne dass irgendwo etwas rot wird. Deshalb bei der Eingabe verhindert, nicht hinterher gesucht.

Drei Bedingungen müssen alle zutreffen, sonst greift die Sperre nicht:
1. `currentClub()->blsv_member` — die Feuerwehr führt Sparten, meldet aber niemandem.
2. Die Mitgliedschaft ist offen (`club_member.to IS NULL OR >= heute`). Nach `resign()` ist sie zu, und dann muss man die zurückgelassenen Sparten-Zeilen auch aufräumen können.
3. Die bearbeitete Zeile ist selbst noch aktiv und es gibt keine zweite aktive.

**„Aktiv" ist `to IS NULL OR to >= heute`** — dieselbe Lesart wie `inRange()` und die Statistik. Eine Zeile, die heute endet, zählt heute noch. Nicht abweichend definieren, sonst widerspricht sich die App.

Zwei verschiedene Rückmeldungen, mit Absicht: `update` wirft eine `ValidationException` auf `to` (der Dialog hat ein Feld dafür), `destroy` flasht einen `error`-Toast und gibt `back()` zurück (die Lösch-Bestätigung hat kein Feld).

**Bewusst offen gelassen:** die Gegenrichtung. `MembershipController` kann eine Mitgliedschaft (wieder) öffnen, ohne dass eine Sparte existiert — etwa beim Wiedereintritt. Dort zu sperren würde die natürliche Eingabereihenfolge (erst Mitgliedschaft, dann Sparte) blockieren. Wer die Invariante lückenlos will, braucht dafür eine andere Lösung als eine Sperre, etwa einen Hinweis auf der Mitgliederseite.

Der Anlege-Weg ist dicht: `entryRules()` verlangt `section_id`.
