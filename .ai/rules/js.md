---
paths:
  - 'resources/js/**'
---

# Js

## i18n runs through laravel-vue-i18n with lang/de.json
German is the UI language, but every user-facing string goes through translation, never hardcoded German. Backend uses `__()`, Vue uses `$t()` in templates and `trans()`/`wTrans()` in script setup. All keys live in lang/de.json keyed by the English source string.

Traps:
- app.ts reads the `locale` shared prop straight out of the page's JSON script tag at module scope and calls `I18n.getSharedInstance().loadLanguage()` synchronously BEFORE createInertiaApp(). Without that, `trans()` calls evaluated at module load (e.g. `defineOptions({ layout: { breadcrumbs } })`) capture an untranslated, non-reactive snapshot. Don't "simplify" this to a plain `app.use(i18nVue)`.
- Because of that DOM read, vite.config.ts sets `inertia({ ssr: false })`. Turning SSR back on breaks the boot.
- The Vue plugin is registered via Inertia v3's `withApp(app)` callback; there is no `setup()` in this app.
- `SetLocale` middleware (bootstrap/app.php, before HandleInertiaRequests) applies `users.locale`, falling back to config('app.locale'). Selectable languages come from `User::availableLocales()`.
- Es gibt **keine öffentliche Seite mehr**. `Welcome.vue` ist am 2026-08-27 gelöscht worden, samt des `case name === 'Welcome': return null` in app.ts, das sie als einzige Seite ohne Layout rendern ließ. `/` ist jetzt `HomeController`: ein Gast geht direkt auf den Login, ein angemeldeter Benutzer auf seine eingestellte Startseite (siehe die `LandingPage`-Regel — der Umweg über `/dashboard` würde die Einstellung aushebeln). Damit ist auch das letzte unübersetzte Starter-Kit-Englisch weg; jede verbliebene Seite läuft durch `$t()`/`trans()`.

## The whole app is translated; PHP lang files exist alongside de.json
Every auth page, the settings pages, the user menu and the CRUD screens go through `$t()`/`trans()`. Two things that are easy to miss:

- lang/de.json is no longer the whole story. `lang/de/auth.php`, `lang/de/passwords.php` and `lang/de/validation.php` hold the messages Laravel resolves by dotted key. Without those files it silently falls back to its built-in English lines, so a German login dialog answered a failed login in English. `validation.php` deliberately covers only the rules the translated screens can surface — the per-key fallback keeps everything else English until a screen needs it. Form requests that declare their own `messages()` (user and section CRUD) take precedence over `validation.php` and stay keyed by their English source string in de.json.
- `tests/Unit/TranslationKeyTest.php` scans resources/js for `$t()` / `trans()` / `wTrans()` and fails on any key missing from lang/de.json, so a new user-facing string must be added there in the same change. It is a plain-filesystem test (unit tests get no app boot, so no `base_path()` / `File::`), and its regex uses `(?<![\w$])` rather than `\b` — `$` is not a word character, so `\b` never matches in front of `$t` and the check was vacuous until that was fixed.

## NavItem.external renders an anchor — required for non-Inertia pages
`NavMain.vue` renders every sidebar entry with Inertia's `<Link>`. That only works for routes returning an Inertia response. The log viewer (opcodesio/log-viewer) is a Blade page, so an Inertia visit there gets HTML back without the `X-Inertia` header and the SPA cannot render it.

Set `external: true` on the `NavItem` for any such destination; `NavMain` then emits a plain `<a :href="toUrl(item.href)">` and the browser does a normal full-page navigation. The `Logs` entry in `AppSidebar.vue` is the current example.

The href still comes from Wayfinder (`import { index as logViewer } from '@/routes/log-viewer'`), not a hardcoded '/log-viewer' — Wayfinder generates helpers for vendor package routes too, so the link survives a change to `config('log-viewer.route_path')`. Re-run `php artisan wayfinder:generate --with-form` after installing a package that adds routes, or its helpers will be missing.

## "Role" means two different things — Funktion vs Rolle
Two unrelated concepts collide on the English word "role", and lang/de.json keeps them apart:

- `Role` → "Rolle" is `club_user.role` (ClubRole Basic/Advanced/Admin), the user's permission level. Used by the user CRUD only.
- `Roles`, `New role`, `Role name`, `Role created.` … → "Funktion(en)" is the `roles` table. Used by the roles CRUD only.

The two never appear on the same screen, so the neighbouring `"Role": "Rolle"` and `"Role created.": "Funktion hinzugefügt."` lines in de.json are correct even though they look inconsistent side by side.

"Funktionen" was questioned twice as clumsy and briefly changed to "Ämter" on 2026-08-25, then changed back the same day once the production data was actually counted. Do not relitigate it without re-reading that data. The `roles` table holds three different kinds of thing, and "Ämter" names the smallest of them:

| Art | Beispiele | Zuordnungen |
| --- | --- | --- |
| Ämter | 1./2. Kommandant, Vorstand, Kassier, Spartenleiter | 22 |
| Dienstgrade | Feuerwehrmann, Oberfeuerwehrmann, Löschmeister, Brandmeister | 134 |
| Qualifikationen (Lehrgänge) | Atemschutzgeräteträger, Sprechfunker, Truppmann/-führer, Maschinist | 171 |

Club 1 is a sports club and uses offices only; club 2 is a Feuerwehr and supplies nearly all the ranks and qualifications — one entry is literally named "Leiter einer Feuerwehr (Lehrgang)". "Ämter/Ränge" was considered and rejected: it is longer than "Ämter" and still misses the largest group. "Funktionen" is the only short German word that covers all three.

Die Vermischung ist kein Fehler und war nie geplant: der Sportverein pflegt nur Ämter, die Feuerwehr hat Dienstgrade und Lehrgänge nach und nach dazugelegt. Am 2026-08-25 bewusst so belassen — nicht "aufräumen".

If the three kinds ever need to be told apart in the UI, that is a `type` column on `roles` plus a backfill of the existing rows — a data-model change, not a wording change, and deferred on purpose. Beim Planen beachten: `roles` hat `unique(club_id, name)`, was eine `type`-Spalte allein nicht löst — ein Name, der in zwei Arten vorkommen soll (etwa Gruppenführer als Lehrgang und als Einsatzfunktion), braucht dann einen anderen Namen oder ein Unique über `(club_id, name, type)`.

Sidebar order follows lsverein7: Abteilungen, Ereignisse, Funktionen.

## events heißt im UI "Ehrungen", nicht "Ereignisse"
Tabelle, Model und Routen heißen weiter `events`/`Event` — das ist der technische Name aus lsverein7 und bleibt. Das deutsche UI-Wort ist aber "Ehrung"/"Ehrungen" (feminin: die Ehrung), nicht lsverein7s "Ereignisse".

Grund, am 2026-08-25 an den Produktionsdaten geprüft: keine der 20 Zeilen ist ein Ereignis im Alltagssinn — kein Vereinsfest, keine Versammlung. Jede ist etwas, das einem Mitglied an einem Datum verliehen wird.

| Art | Beispiele | Zuordnungen |
| --- | --- | --- |
| Jubiläums-/Dienstzeitehrungen | 25–70 Jahre, 25/40 Jahre aktive Dienstzeit | 403 |
| Abzeichen / Leistungsnachweise | Leistungsabzeichen Bronze–Gold, Wissenstest 1–4, Jugendleistungsspange | 14 |

Der Code sprach ohnehin schon von Ehrungen: `Club::honor_years` (in lsverein7 als "Ehrungen Mitgliedsjahre" beschriftet), `Member::honorYearReached()`, der `dueHonor`-Scope, lsverein7s Filter "Fällige Ehrungen" und die CSV-Spalte `Ehrung`. "Ereignisse" war nur der Tabellenname, der ins UI durchgeschlagen ist.

Die Beschreibung auf der Index-Seite nennt beide Arten ausdrücklich ("Ehrungen und Auszeichnungen, die ein Mitglied erhält oder erwirbt"), damit die Feuerwehr ihre Leistungsabzeichen hier vermutet. Das "erhält oder erwirbt" ist bewusst so und nicht "verliehen": Jubiläumsehrungen bekommt man für Zeit, Leistungsabzeichen und Wissenstest muss man sich erarbeiten, und "verliehen" stellt das Mitglied passiv. Gleiches Muster wie bei Funktionen — siehe die Notiz zu "Role".

## Die Relationen gehören auf die Mitgliederseite, nicht ins Bearbeitungsformular
`members/{id}` (Show.vue) **ist** die Mitgliederseite: Kopf, Stammdaten, dann die sechs Relationen mit [+] und ✎/🗑 je Zeile. `members/{id}/edit` ist nur noch das Formular für die Personendaten. Am 2026-08-26 bewusst so entschieden, gegen lsverein7.

**Warum nicht wie lsverein7:** dort lagen die sechs Tabellen *innerhalb* des `<form>`, das die Stammdaten speichert, und jeder „Neu"/Stift war ein `router.get(...)` auf eine eigene Seite. Wer den Nachnamen korrigierte und dann eine Funktion hinzufügen wollte, verlor die ungespeicherte Korrektur wortlos. Dazu kamen zwei Speichermodelle auf einem Bildschirm (Formular mit Speichern-Knopf vs. sofort schreibende Zeilenaktionen) und dieselben sechs Listen doppelt gepflegt, weil Show sie ohnehin schon anzeigte.

Aufbau:

- `MemberRelationSection.vue` — Titel, Zeilen, [+], je Zeile ✎ und 🗑, plus **eine** Lösch-Bestätigung pro Abschnitt (auf die jeweilige Zeile gerichtet). Rein darstellend, meldet `add`/`edit` nach oben.
- `MemberRelationDialog.vue` — gemeinsame Hülle: optionale Auswahl, `<slot>` für die formspezifischen Felder (Zeitraum / Datum / nichts), Notiz, Fußzeile. `formKey` erzwingt ein Remount, wenn der Dialog auf eine andere Zeile gerichtet wird — sonst stehen die Werte der vorher bearbeiteten drin.
- Show.vue hält den Dialogzustand (`openKind` + `editingId`) und rendert die sechs Aufrufe.

Sichtbarkeit: `modifiable` blendet alle Knöpfe aus (Liste bleibt für jeden lesbar), `showsFinances` den ganzen Beitrags-Abschnitt, `usesItems` den Inventar-Abschnitt. `options` ist `null` für einen Nur-Lese-Account — es gibt nichts zu wählen.

**Wayfinder-Falle:** eine Route mit zwei Parametern nimmt ein Tupel, keine Positionsargumente — `MemberSectionController.destroy.form([member.id, row])`, nicht `.form(member.id, row)`. Sonst schlägt `vue-tsc` mit TS2345 zu.

Bewusst nicht hier: die Jahresend-Massenaktion („alle mit 25 Jahren bekommen die Ehrung"). Das ist eine Aktion auf der Liste, nicht auf einem Mitglied; lsverein7 hatte sie auch nicht.
