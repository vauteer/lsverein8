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
