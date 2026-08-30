---
paths:
  - app/Http/Controllers/MemberExportController.php
  - app/Http/Controllers/BlsvStatisticController.php
  - app/Http/Controllers/DashboardController.php
  - app/Http/Controllers/MemberController.php
  - 'app/Http/Controllers/**'
---

# Controllers

## Exporte teilen die Auswahl mit der Liste — über SelectsMembers
Sechs Formate (`App\Enums\MemberExport`: `pdf`, `roles`, `csv`, `vcf`, `blsv-xlsx`, `blsv`), eine Route: `GET members/export/{format}`, aufgelöst per impliziter Enum-Bindung — ein unbekanntes Format ergibt 404 statt einer leeren Datei. Der Enum ersetzt `Member::EXPORT_FORMATS`, ein const-Array unübersetzter Labels, das nichts las und dem das Funktionen-PDF fehlte.

**`App\Concerns\SelectsMembers` ist der Kern.** Die Auswahl-Maschinerie (`selection()`, `applyFilter()`, `dynamicFilters()`, `filterLabel()`, `resolveYear()`) lag vorher privat im MemberController; sie ist herausgezogen, damit ein PDF oder CSV nie etwas anderes enthält als der Bildschirm, von dem aus es gestartet wurde. Insbesondere ruft `selection()` `Member::setKeyDate()` — der Export rechnet Alter und Mitgliedsjahre also gegen dasselbe Stichjahr wie die Liste.

`filterLabel()` schlägt dynamische Auswahlen in `dynamicFilters()` nach statt sie ein zweites Mal abzubilden, damit eine Überschrift eine Auswahl nicht anders benennt als das Dropdown, das sie erzeugt hat. Dieselbe Beschriftung wird per `Str::slug()` zum Dateinamen (`ex-mitglieder-2020.csv`).

Festlegungen:

- **Das CSV ist ISO-8859-1**, wie die BLSV-Dateien — das erwarten die Tabellenkalkulationen am anderen Ende seit lsverein7. Umgewandelt wird **einmal am Schluss**, nicht pro Feld wie in lsverein7: ein Name außerhalb von Latin-1 kann so nicht die Spaltenzahl verschieben.
- **Berechtigung ist `viewAny`,** wie die Liste. Die Exporte enthalten nichts, was die Liste nicht ohnehin zeigt — keine Bankdaten, keine Beiträge.
- Im Frontend sind die Menüeinträge **einfache `<a download>`**, keine Inertia-Links: sonst sucht die SPA nach einer Komponente. Gleiche Begründung wie bei den SEPA-Downloads.
- Wayfinder: `MemberExportController.url(format, { query })` — der Query gehört ins **zweite** Argument, sonst TS2353.

## BLSV: ein Sidebar-Eintrag, zwei Routen — die Index-Seite schreibt nichts
Einstieg ist seit 2026-08-27 der Sidebar-Eintrag „BLSV", nicht mehr ein Abschnitt weit unten in `clubs/Edit.vue` (dort ersatzlos entfernt, samt der Prop `blsvStatistic`). Sichtbar über `auth.canReportToBlsv` aus HandleInertiaRequests, aufgelöst über den Gate `reportToBlsv`.

**Zwei Routen, und die Trennung ist tragend:**

| Route | Aktion | schreibt? |
| --- | --- | --- |
| `GET blsv` (`blsv`) | `index()` → `clubs/Blsv.vue` | nein |
| `GET clubs/{club}/blsv-statistic` (`clubs.blsv-statistic`) | `build()` → `clubs/BlsvStatistic.vue` | **ja** |

`build()` erzeugt die Dateien beim GET. Hinter einem Knopf ist das richtig, hinter einem Sidebar-Eintrag wäre es falsch: jeder neugierige Klick liefe durch alle Sparten eines 585-Mitglieder-Vereins und schriebe neun Dateien. Deshalb die Index-Seite davor. Ein Test pinnt das („the index names both reports and writes nothing").

**Die Index-Route trägt bewusst keinen Vereinsparameter.** Auf der Seite gibt es nichts, was eine fremde Vereins-Id auswählen könnte — der Name kommt aus `currentClub()` —, und ein Parameter, den die Policy ohnehin auf den aktuellen Verein festnagelt, ist Dekoration. Der Gate `reportToBlsv` (AppServiceProvider, neben `manageBackups` und `viewTelescope`) beantwortet ihn für den Verein, in dem der Benutzer arbeitet, und delegiert an `ClubPolicy::blsvStatistic()`. Er darf **nicht** `currentClub()` aufrufen: das ist auf `: Club` typisiert und würde bei einem Konto ohne `club_id` fatal. Deshalb `Club::find(currentClubId())`.

Nebeneffekt, der die Umstellung ausgelöst hat: `defineOptions({ layout: { breadcrumbs } })` wird beim Modul-Laden ausgewertet und sieht keine Props, kann eine Vereins-Id also gar nicht einsetzen. Vorher zeigte die erste Brotkrume von `BlsvStatistic.vue` deshalb auf `/clubs` — für einen Vereins-Admin ein 403. Mit einer parameterlosen Route stimmt der Pfad.

Die Seite bietet beide Meldungen nebeneinander an: Jahresmeldung (Stichtag 1.1., hinter dem Knopf) und Nachmeldung (Stand heute, als direkte Downloads). Die Nachmeldung ist auf `filter=members` festgenagelt und hängt damit gar nicht am Zustand der Mitgliederliste — sie liefert von hier dasselbe wie von dort, weshalb sie an beiden Stellen stehen darf.

## BLSV-Statistik: immer der aktuelle Verein, GET baut sie neu
`build()` erzeugt die Jahresmeldung und listet die Dateien auf `clubs/BlsvStatistic.vue`.

**Der Vereinsparameter ist reine Kosmetik — gerechnet wird immer der aktuelle Verein.** `Member` und `Section` tragen ClubScope, `Club::getBLSVStatistic()` liest also die Mitglieder des Vereins, in dem der Benutzer *arbeitet*, benennt die Dateien aber nach `$this`. Deshalb verlangt `ClubPolicy::blsvStatistic()` ausdrücklich `$club->id === currentClubId()` und delegiert **nicht** an `update()` — sonst könnte root von der Seite eines anderen Vereins aus die Mitglieder des eigenen unter fremdem Namen ablegen. Root wechselt vorher, so wie es die Vereinsliste beim Mitgliederzähler ohnehin verlangt. Dazu `blsv_member` und `hasAdminRights()` (gleiche Schranke wie der Gate `downloadGeneratedFiles` — wer die Dateien nicht laden darf, soll sie nicht erzeugen).

Ein GET, der schreibt, ist Absicht (wie lsverein7): gespeichert wird nur nach storage/downloads, und die Zahlen sollen den Stand im Moment des Aufrufs zeigen. Ein Reload baut also neu, statt Altes zu zeigen.

`Club::writeDownload()` bildet den href mit `route('downloads.show', $filename, absolute: false)`, nicht mit `"/downloads/{$name}"` wie `Subscription::generateSepa()`: die CSV heißt `BE{Jahr}_{Spartenname}.csv`, und ein Spartenname darf laut SectionValidationRules Leerzeichen und Umlaute tragen. Nur `route()` kodiert die.

Reihenfolge der Downloads: Altersstatistik (PDF), **Mitgliedermeldung (Excel)**, Mitgliedermeldung (CSV), dann die Sparten in BLSV-Nummernfolge. Die Excel-Datei steht direkt hinter der Statistik, weil sie die ist, die der Verein tatsächlich hochlädt — der BLSV will Excel. lsverein7 lieferte durch ein `array_reverse()` die Sparten absteigend — das war ein Nebeneffekt, nicht gewollt. Leere Sparten bekommen gar keine Datei.

Jeder Eintrag trägt seit 2026-08-27 ein `description` neben dem `name` (`GeneratedDownload.description` ist optional — die SEPA-Seiten teilen den Typ und liefern keines). „Alters-Statistik" und „Alle Sparten" sagten nicht, wofür die Datei da ist; jetzt heißt es „Mitgliedermeldung (Excel)" mit der Unterzeile „Alle Sparten in einer Datei — diese lädt der Verein beim BLSV hoch".

**`App\BlsvMemberReport` rendert alle Mitgliederlisten, die an den BLSV gehen** — die Jahresmeldung hier und die Nachmeldung im MemberExportController. Welche Zeilen hineingehen, entscheidet der Aufrufer (hier nach Sparte sortiert und zum 1.1. gelesen, dort nach Mitglied und zum Stichtag der Liste); geteilt wird nur die Darstellung: Spalten, Trennzeichen, ISO-8859-1, die Excel-Typen. `csv($rows, withHeader: false)` schreibt die Sparten-Dateien, die anders als die beiden Gesamt-Dateien keine Kopfzeile tragen — so war es in lsverein7 und so bleibt es.

## Dashboard: jede Zahl ist die Auswahl, auf die sie verlinkt — und keine MySQL-only-Scopes
`GET dashboard` (DashboardController, nur `auth`) zeigt Kennzahlen, Altersstruktur, Vereinszugehörigkeit, 10-Jahres-Entwicklung, Abteilungen und (nur Admin) Beiträge.

**Funktionen sind bewusst nicht dabei** (am 2026-08-27 wieder entfernt): der Sportverein pflegt 11, die Feuerwehr 29, davon die meisten mit ein bis zwei Trägern — als Balkendiagramm ist das eine Liste, keine Aussage. Wer sie sehen will, geht auf `/roles`, wo die Zahl ohnehin steht.

**Die Kachel heißt „Ehrungen", nicht „Fällige Ehrungen".** Die Zahl ist weiter der `due_honours`-Wert und verlinkt auch dorthin, aber eine Ehrung, die dieses Jahr ansteht, kann längst verliehen sein — „fällig" behauptet mehr, als die Zahl weiß.

**Tragende Eigenschaft, gleiche wie bei `members_count`:** jede angezeigte Zahl wird von genau der Auswahl erzeugt, auf die sie verlinkt — Abteilungen/Beiträge über `AssignedMemberCount`, die Altersgruppen über `AgeBracket::apply()` (= `members()->ageRange()`), die Jahreszahlen über dieselben `club_member`-Bedingungen wie `joined`/`retired`. Eine Zahl, die das nicht halten kann, wird **nicht** als Link gerendert (Vereinszugehörigkeit hat keine Auswahl und ist deshalb reiner Text). Am 2026-08-27 an Produktionsdaten geprüft: alle sieben Altersgruppen stimmen exakt mit ihrer Auswahl überein.

**Kein MySQL-only-Scope auf diesem Bildschirm.** `dueHonor`, `joined`, `retired`, `dead`, `milestoneBirthdays` nutzen `YEAR`/`LEAST`/`FIND_IN_SET` und würden die ganze Seite auf der SQLite-Testverbindung unausführbar machen. Deshalb:
- Ein-/Austritte über `whereBetween('from'|'to', [1.1., 31.12.])` statt `YEAR(...)` — gleiche Menge, portabel.
- Fällige Ehrungen über `Member::membershipYears()` in PHP statt über den `dueHonor`-Scope. Beide Wege liefern in Produktion dieselbe Zahl (23, geprüft); sie können nur bei jemandem auseinanderlaufen, der im selben Jahr wieder eingetreten ist und ältere Mitgliedschaften hat — `membershipYears()` gibt dann 0 zurück, das SQL summiert. Das ist eine bestehende Abweichung der App, nicht des Dashboards.
- `honor_years` einmal im Controller auflösen, nicht `Member::honorThisYear()` pro Zeile: das ruft `currentClub()` und damit ein `Club::find()` je Mitglied.

Die Mitglieder werden **einmal** geladen (`members()->with('memberships')`) und in PHP in Alters- und Zugehörigkeitsbänder sortiert — wie `Club::getBLSVStatistic()`. Eine Abfrage je Gruppe wäre eine Abfrage je Gruppe **und** Geschlecht.

Die Beiträge-Karte ist admin-only (`hasAdminRights()`), gleiche Begründung wie bei `MemberResource` und `MemberFilter::NoSubscription`.

## BLSV-Export: nur meldende Vereine, nur die Auswahl „Mitglieder", eine Zeile je Sparte
Zwei Formate, beide „Für die Mitgliedernachmeldung": `MemberExport::BlsvExcel` ('blsv-xlsx', „BLSV (Excel)") und `MemberExport::Blsv` ('blsv', „BLSV (CSV)"). Spalten in beiden: `Titel | Name | Vorname | Namenszusatz | Geschlecht | Geburtsdatum | Spartenkennzeichen`, Titel und Namenszusatz immer leer.

**Gerendert wird in `App\BlsvMemberReport`, nicht hier** — dieselbe Klasse, die auch die Jahresmeldung schreibt (siehe den BLSV-Abschnitt weiter unten). `blsvRows()` im Controller sammelt nur die Zeilen der Nachmeldung; Spalten, Typen und Kodierung liegen an einer Stelle, damit Excel-Datei und CSV den Verein nie verschieden beschreiben. Ein Test baut das CSV aus dem Sheet nach und vergleicht beide wörtlich.

**Der BLSV will eine Excel-Datei.** Bis dahin hat Gerald das CSV von Hand in die BLSV-Vorlage kopiert; genau dieses Einfügen ging schief. Deshalb sind in der .xlsx die zwei Spalten, die *kein* Text sind, echte Typen: Geburtsdatum als Datums-Serial mit dem eingebauten Kurzdatumsformat (numFmtId 14, in OpenSpout als `'mm-dd-yy'` zu schreiben — deutsches Excel zeigt TT.MM.JJJJ), Spartenkennzeichen als Zahl. Titel und Namenszusatz sind `EmptyCell`, also gar keine `<c>`-Zelle, wie in der Vorlage.

Geschrieben mit **openspout/openspout** (2026-08-27 aufgenommen, einziges neues Paket, hängt nur an PHP-Extensions). Der Schreiber kann nur in einen echten Pfad schreiben (er baut ein Zip), deshalb `tempnam()` und zurücklesen.

Das CSV bleibt bewusst daneben stehen, als Ausweichpfad falls der Verband die .xlsx beanstandet. Struktur unverändert wie `BE{Jahr}_Gesamt.csv` aus `Club::getBLSVStatistic()`: Semikolon, CRLF, gequotetes `d.m.y`, ISO-8859-1 (einmal am Schluss konvertiert, nicht pro Feld).

Am 2026-08-27 gegen die echte BLSV-Vorlage `BE2026_08_Mitgliederimport.xlsx` geprüft: Kopfzeile identisch, A/D durchgängig leer, gleiche Spartenmenge, 269 von 275 Zeilen deckungsgleich. Die Abweichungen sind alle echte Datenänderungen seit der Vorlage (Austritte, ein gelöschtes Mitglied, Neueintritte), keine Formatfehler.

**Die beiden Dateien beantworten verschiedene Fragen — deshalb gibt es beide.** Die Jahresstatistik (`BlsvStatisticController`) ist die Jahresmeldung und wird **immer** zum 1.1. gelesen. Dieser Export ist für die **Nachmeldung während des Jahres** und liest deshalb zum Stichtag der Liste, im Normalfall heute. Beim Stichtag also nicht „vereinheitlichen".

Eine Nachmeldung ist **kein Delta**: der Verein lädt seinen *gesamten* Mitgliederbestand hoch. Genau darum ist das Format nur für die Auswahl „Mitglieder" da — eine Teilmenge sähe einreichbar aus und würde den Verein untermelden.

`MemberExport::isAvailableFor()` sperrt **beide** doppelt (`isBlsv()` fasst sie zusammen): `currentClub()->blsv_member` **und** Filter `members`. Der Enum filtert damit `optionsFor()` (Menü), und `MemberExportController` ruft `abort_unless()` — eine getippte URL bekommt 404. Deshalb heißt die Fabrik-Methode `optionsFor(string $filter)`, nicht mehr `options()`.

**Ein Mitglied ergibt eine Zeile je BLSV-Sparte, nicht je Person** — das Spartenkennzeichen ist, was der Verband zählt. Nach `blsv_id` dedupliziert (es gibt Mitglieder mit zwei Pivot-Zeilen für dieselbe Sparte) und aufsteigend sortiert. Sparten ohne `blsv_id` ergeben gar keine Zeile.

An den Produktionsdaten geprüft (2026-08-27): Verein 1 (der einzige mit `blsv_member`), Auswahl „Mitglieder" → 254 Mitglieder, 278 Zeilen, **0 ohne Sparte**. Alle sieben Sparten von Verein 1 haben eine `blsv_id`. Ein Sonderfall „Mitglied ohne Spartenkennzeichen" existiert dort also nicht — nicht dafür umbauen, ohne das neu zu zählen.

Dateiname `BE{Jahr}_Nachmeldung_{TTMM}.{ext}` für beide (z. B. `BE2026_Nachmeldung_2708.xlsx`), statt des generischen `filename()`. Generisch hieße die Datei „mitglieder-2026.csv", also genau wie der einfache CSV-Export; und ein Verein meldet mehrmals im Jahr nach, das Datum hält die Dateien im Download-Ordner auseinander. Jahr **und** Tag kommen aus `Member::getKeyDate()`, nicht das Jahr aus `$selection['year']` — so können sie nicht auseinanderlaufen (ein vergangenes Jahr wird zum 31.12. gelesen).

Die Spaltenüberschrift heißt seit 2026-08-27 `Spartenkennzeichen`, vorher `Spartennummer` — in **allen** Dateien (beide Exporte und `Club::getBLSVStatistic()`), damit sie nicht auseinanderlaufen.

Testhilfen `xlsxParts()` / `xlsxRows()` in tests/Pest.php lesen eine erzeugte .xlsx als Gitter zurück (Inline-Strings, kein sharedStrings). `numFmts count="0"` in styles.xml ist die Zusage „kein eigenes Format registriert" — OpenSpout schreibt das leere Element immer, `not->toContain('<numFmts')` schlägt also fehl.

## Doppelte Mitglieder: Anlegen landet auf der Mitgliederseite, Warnung statt Sperre
Am 2026-08-27 gebaut, gegen einen echten Vorfall: ein Benutzer hat Lena Matt mit Eintritt 01.09.2026 angelegt, sie danach in der Standardauswahl „Mitglieder" nicht gefunden (zu Recht — sie tritt erst ein), das Speichern für gescheitert gehalten und sie ein zweites Mal eingegeben. In Produktion gab es **vier** solcher Gruppen.

**`store()` leitet auf `members.show`, nicht auf `members.index`.** Die Begründung stand schon im Code, bei `resign()`: eine Auswahl, die das gerade Bearbeitete nicht enthält, liest sich wie ein Fehlschlag. `store()` folgte ihr nur nicht. Nicht zurückdrehen.

**Duplikatswarnung: `findDuplicate()` sucht Nachname + Vorname + Geburtstag im selben Verein** (ClubScope). Kein harter Block — zwei Hans Bauer in einem Dorfverein sind normal —, sondern ein Fehler auf `confirm_duplicate`, den eine Checkbox im Formular aufhebt.

**Die Prüfung liegt im Controller, nicht in `MemberStoreRequest`.** Die Seite braucht das gefundene Mitglied selbst (Nummer, Mitgliedschaftsdaten, Link); ein FormRequest kann nur einen String zurückgeben. Übergeben wird es per `back()->withErrors()->with('duplicate', …)`, `create()` liest es aus der Session in die Prop.

**Der teure Fall ist der Wiedereintritt, und die Meldung sagt dort etwas anderes.** Bauer Hans und Scherm Hannes wurden nach Jahren Pause als *neues* Mitglied angelegt statt als zweite `club_member`-Zeile. `membershipYears()` summiert die Zeilen **eines** Datensatzes — Scherm verliert dadurch 14 Jahre, seine 25-Jahre-Ehrung rutscht von 2035 auf 2050. Bei einem ausgetretenen Treffer lautet der Text deshalb „Mitgliedschaft dort wieder aufnehmen", nicht „bestätigen".

**`entry_date` ist `before_or_equal:` heute + 3 Monate**, vorher `today`. Ein Verein trägt jemanden zum 1. September ein; wer das nicht darf, schreibt „heute" hin und verfälscht die Mitgliedsjahre. Die Schranke fängt nur das vertippte Jahr. Eigene Meldung `entry_date.before_or_equal`, weil die generische Zeile („darf nicht in der Zukunft liegen") für Geburts- und Sterbedatum weiter gilt.

**`MemberFilter::PossibleDuplicates` / `Member::possibleDuplicates()`** listet **beide** Hälften eines Paars, aktiv oder nicht — deshalb kein `members()` davor und kein Stichtag: die teure Hälfte ist die ausgetretene. Bewusst als gruppierte Abfrage plus OR-Kette geschrieben, nicht als korrelierte Subquery, damit sie anders als `dueHonor`/`joined` auch auf der SQLite-Testverbindung läuft. An Produktionsdaten geprüft: findet in Verein 1 genau die 6 Zeilen der drei Gruppen.

Nicht abgedeckt: `MembershipController` erlaubt für `from` weiter jedes Datum. Dort entsteht die Verwirrung aber nicht — die Aktion antwortet mit `back()` auf die Mitgliederseite, die Zeile ist also sofort sichtbar.

## Wiedereintritt ist die Umkehrung von resign() — und schließt die Invarianten-Lücke
`PUT members/{member}/rejoin` (MemberController::rejoin, `MemberRejoinRequest`, Policy `rejoin` = `update`), seit 2026-08-28. Der Knopf steht auf der **Mitgliederseite** neben „Bearbeiten", nicht im Bearbeitungsformular wie „Mitgliedschaft beenden": ein Wiedereintritt schreibt in drei Relationen, und die werden dort gepflegt.

**Er verlangt Datum, Sparte und Beitrag zusammen.** Genau das ist der Zweck: ein aktuelles Mitglied muss in einer Sparte sein (BLSV) und einen Beitrag halten. Beide Regeln waren bisher nur beim Anlegen und beim Entfernen dicht — `MembershipController` konnte eine Mitgliedschaft wieder öffnen und damit jemanden erzeugen, den der Rest der App für ungültig hält. Diese Lücke stand vorher ausdrücklich als „bewusst offen" in den Sparten- und Beitragsregeln; sie ist damit geschlossen. **`MembershipController` bleibt unangetastet** — dort zu sperren würde die natürliche Eingabereihenfolge brechen, der Wiedereintritt ist stattdessen der bequemere Weg.

Festlegungen:
- **Ein zweiter Zeitraum, kein wiedergeöffneter.** `Member::membershipYears()` summiert die Zeiträume; die Jahre außerhalb des Vereins dürfen nicht mitzählen.
- `Member::lastMembershipEnd()` ist die Untergrenze (null, solange ein Zeitraum offen ist). Das Datum muss **strikt danach** liegen, sonst überlappen zwei Zeiträume und ein Jahr zählt doppelt. Die Seite teilt `earliestRejoining` = dieser Tag + 1, damit Picker-Grenze und Regel dasselbe Datum sagen.
- **`rejoinable` = `modifiable && alive() && ! isMember()`.** Verstorbene bekommen den Knopf nicht: `isMember()` ist bei ihnen ohnehin false, `alive()` muss also ausdrücklich dazu.
- Der Beitrag wird nur angehängt, wenn das Mitglied ihn nicht schon hält — `member_subscription` trägt keine Daten, die Zeile stünde sonst doppelt. Bei Sparten ist ein zweiter Zeitraum dagegen richtig und gewollt.
- `MemberValidationRules::joiningRules()` hält Sparte und Beitrag an **einer** Stelle; `entryRules()` (Anlegen) und `MemberRejoinRequest` teilen sie. Wer eine dritte Bedingung fürs Beitreten einführt, gehört dorthin.

Getestet in `MemberManagementTest`: „a former member is taken back in…", „rejoining is refused where it would overlap or make no sense", „a dead member is never offered a rejoining", „rejoining does not duplicate a subscription the member still holds".

## Login-Karte: root-only, und in PHP gebucketet statt in SQL gruppiert
`logins` ist die einzige Dashboard-Kachel, die an `users.admin` hängt statt an `hasAdminRights()`. Grund: `tracings` trägt keinen ClubScope, die Zeilen umfassen also die ganze Installation — ein Club-Admin läse mit, wer sich in anderen Vereinen anmeldet. Wie `subscriptions` wird `null` geliefert, nicht eine leere Karte; `Dashboard.vue` rendert per `v-if`.

**Nicht in SQL gruppieren.** Die Monatsbuckets entstehen in PHP, weil `DATE_FORMAT` MySQL-only ist und der ganze Bildschirm laut Klassen-Docblock auf der SQLite-Testverbindung lauffähig bleiben muss. Bei ~330 Logins im Jahr ist das gratis.

Das Fenster sind **Kalendermonate** (`startOfMonth()->subMonths(11)`), nicht `subMonths(12)` — deshalb liegt die Gesamtzahl leicht unter einem rollierenden Jahr, und der erste Bucket ist ein voller Monat statt eines angebrochenen.

Konten ohne Login werden gezählt (`dormant`), nicht gelistet. Monatsbeschriftungen über `translatedFormat('M y')`, nicht `format()` — sonst steht im deutschen UI „Dec" statt „Dez".

Fallstrick phpstan: `->values()->all()` auf einer Collection beweist die `list<>`-Form nicht, `array_values(...->all())` schon.

## SEPA-Vorlaufzeit steht in clubs.sepa_lead_days, nicht im Controller
Seit 2026-08-30: `clubs.sepa_lead_days` (unsigned tinyint, Default 8) hält, wie viele Tage die beiden Einzugs-Dialoge auf heute addieren, wenn sie ein Ausführungsdatum vorschlagen. Vorher stand dieselbe `private const int SEPA_LEAD_DAYS = 8` doppelt in `DebitController` und `SubscriptionController` — zwei Stellen, die auseinanderlaufen konnten.

Beide lesen jetzt `currentClub()->sepa_lead_days`; editierbar im Vereinsformular (`ClubFormFields.vue`), Regel `required|integer|between:0,60`.

**Bewusst nur ein Vorschlag, keine Sperre:** `DebitCollectRequest` und die Beitrags-Einziehung validieren das Datum weiterhin nur als `date`. Die Vorlaufzeit ist die der Bank; ein Kassier muss gelegentlich ein Datum buchen, das die Regel nicht zuließe. Wer sie erzwingen will, muss das bewusst tun.
