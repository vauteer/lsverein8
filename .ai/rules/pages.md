---
paths:
  - resources/js/pages/About.vue
  - 'resources/js/pages/*/Index.vue'
---

# Pages

## Die Über-Seite steht hinter `auth` und ihre Credits sind Handarbeit
Aus lswatter4 übernommen (2026-08-27), mit zwei Abweichungen:

- Die Route liegt in der `auth`-Gruppe, nicht öffentlich wie in lswatter4. Es gibt hier keine öffentliche Seite (siehe die Welcome.vue-Notiz in .ai/rules/js.md); Credits und Kontaktadresse sagen nichts über einen Verein, deshalb aber auch keine Policy — jeder angemeldete Account darf sie lesen.
- `CreditIcon.vue` unterscheidet Lucide (Funktionskomponente) von simple-icons (schlichtes Objekt mit `path`/`hex`) über `typeof icon === 'function'`. Markenzeichen werden unverändert und in ihrer eigenen Farbe gezeichnet — sie zeigen auf das Projekt, sie dekorieren nicht. Dafür hängt `simple-icons` in package.json; es tree-shaked, nur die benutzten Icons landen im Bundle.

`creditGroups` ist eine Handliste und wird von nichts geprüft: wer eine Abhängigkeit hinzufügt oder wirft, muss sie hier nachziehen. Laravel- und PHP-Version kommen dagegen aus dem Controller und sind bewusst gekürzt (Laravel nur Major, PHP Major.Minor) — die Seite nennt die Version, für die gebaut wurde, nicht den Patchstand der Maschine.

## Header-Aktionen dürfen auf dem Handy nicht verschwinden
Der Anlegen-Knopf (und die Sammel-/Export-Knöpfe daneben) war auf fast jeder Index-Seite mit `hidden md:inline-flex` bzw. einem Wrapper `hidden items-center gap-2 md:flex` unterhalb von md ausgeblendet. Damit konnte man auf dem Handy eine Liste sehen, aber nichts anlegen. Am 2026-08-28 umgestellt: Der Knopf bleibt immer da, nur die Beschriftung fällt weg — `<span class="max-md:hidden">…</span>` um den Text, dazu ein `:aria-label` am Link/Button, weil `display:none` den Screenreader-Namen sonst mitnimmt. Übrig bleibt das Plus-Icon; `buttonVariants` hat `has-[>svg]:px-3` und `shrink-0`, das ergibt von allein einen kompakten Icon-Knopf, es braucht keine Breiten-Overrides. Der Wrapper der mehrteiligen Leisten (members, subscriptions, debits) ist jetzt `flex shrink-0 items-center gap-2`.

Ausnahme backups: legt über ein `<Form>` an, dessen Beschriftung zwischen "Neue Sicherung" und "Wird erstellt …" wechselt und damit die einzige Fortschrittsanzeige ist — behält den Text in jeder Breite; das Form hat `shrink-0` bekommen.

Nicht betroffen und bewusst weiter desktop-only: `hidden md:table-cell` an Tabellenspalten und der Club-Wechsel-Knopf (`hidden md:block`) in clubs/Index.vue.

`tests/Unit/MobileIndexActionTest.php` pinnt beides (kein `hidden md:inline-flex`, kein `hidden … md:flex` in einer Index-Seite; jede Anlegen-Seite außer backups hat aria-label + max-md:hidden-Span).
