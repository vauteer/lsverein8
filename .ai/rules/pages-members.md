---
paths:
  - 'resources/js/pages/members/**'
---

# Pages Members

## Die Listenauswahl muss durch jedes Formular, nicht nur durch Abbrechen
Der Zustand der Mitgliederliste (`page`, `search`, `filter`, `sort`, `year`) lebt allein in der URL. `MemberController::backQuery()` liest ihn aus der **aktuellen Anfrage** — steht er nicht in der Query, ist er weg. Show und Edit reichen ihn als `backQuery`-Prop weiter, Index hängt ihn als `rowQuery` an die Zeilen-Links.

**Deshalb muss jedes `.form()` die Query mitgeben, nicht nur die Links.** Bis 2026-08-28 taten das nur die Links (`cancelHref`, der Bearbeiten-Link auf Show); `MemberController.update.form(id)`, `resign.form()`, `destroy.form()` und `store.form()` standen ohne. Wirkung: „Abbrechen" behielt die Auswahl, **„Speichern" verlor sie** — im Browser nachgewiesen, das Formular trug nur `_method`.

Der Fehler ist schlimmer als er aussieht, weil er sich fortpflanzt: nach dem Speichern steht man auf `/members` ohne Query, und von da an hat auch jede folgende Mitgliederseite nichts mehr weiterzureichen. Das erklärt das „manchmal fehlt der Filter".

Richtig ist `MemberController.update.form(id, { query: backQuery })`. Der Wayfinder-Helfer mischt das mit dem `_method`-Spoofing zusammen, beides bleibt erhalten.

Die sechs Relations-Controller auf der Mitgliederseite brauchen das **nicht** — sie antworten mit `back()` und landen damit auf der Show-URL samt Query.

`tests/Feature/MemberManagementTest.php` („an action carries the list selection back with it", „a deleted member returns to the list selection too") pinnt den Server-Vertrag: update und destroy leiten auf die Liste mit Auswahl, resign auf die Mitgliederseite mit Auswahl. Die Reihenfolge der Parameter in der erwarteten URL ist die von `backQuery()` (page, search, filter, sort, year) — `assertRedirect` vergleicht Strings.
