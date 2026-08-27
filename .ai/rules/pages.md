---
paths:
  - resources/js/pages/About.vue
---

# Pages

## Die Über-Seite steht hinter `auth` und ihre Credits sind Handarbeit
Aus lswatter4 übernommen (2026-08-27), mit zwei Abweichungen:

- Die Route liegt in der `auth`-Gruppe, nicht öffentlich wie in lswatter4. Es gibt hier keine öffentliche Seite (siehe die Welcome.vue-Notiz in .ai/rules/js.md); Credits und Kontaktadresse sagen nichts über einen Verein, deshalb aber auch keine Policy — jeder angemeldete Account darf sie lesen.
- `CreditIcon.vue` unterscheidet Lucide (Funktionskomponente) von simple-icons (schlichtes Objekt mit `path`/`hex`) über `typeof icon === 'function'`. Markenzeichen werden unverändert und in ihrer eigenen Farbe gezeichnet — sie zeigen auf das Projekt, sie dekorieren nicht. Dafür hängt `simple-icons` in package.json; es tree-shaked, nur die benutzten Icons landen im Bundle.

`creditGroups` ist eine Handliste und wird von nichts geprüft: wer eine Abhängigkeit hinzufügt oder wirft, muss sie hier nachziehen. Laravel- und PHP-Version kommen dagegen aus dem Controller und sind bewusst gekürzt (Laravel nur Major, PHP Major.Minor) — die Seite nennt die Version, für die gebaut wurde, nicht den Patchstand der Maschine.
