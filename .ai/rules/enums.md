---
paths:
  - 'app/Enums/**'
  - app/Enums/AgeBracket.php
  - app/Enums/LandingPage.php
  - app/Enums/PaymentMethod.php
---

# Enums

## ClubDisplay: warum Logo und Name nicht immer beide erscheinen
`clubs.display` ist auf `App\Enums\ClubDisplay` gecastet (LogoAndName=1, LogoOnly=2, NameOnly=3). Der Grund für die Einstellung ist inhaltlich, nicht kosmetisch: viele Vereinslogos sind Wortmarken, die den Vereinsnamen bereits enthalten — daneben nochmal den Namen zu setzen, druckt ihn doppelt.

Die Auswertung passiert serverseitig in `HandleInertiaRequests`, das `currentClub.show_logo` und `currentClub.show_name` aus `showsLogo()`/`showsName()` teilt. Im Frontend rendert `ClubIdentity.vue` danach; `ClubSwitcher` nutzt sie in beiden Zweigen (statisch und Dropdown), damit die Bedingung nur an einer Stelle steht. Den rohen Enum-Wert absichtlich nicht ins Frontend geben — sonst liegt die Bedeutung von "2" in einem Vue-Template.

Randfall, der bewusst so ist: bei `show_logo` ohne hochgeladene Datei zeigt der Avatar den Anfangsbuchstaben. Bei `LogoOnly` und fehlendem Logo bleibt die Seitenleiste damit lesbar statt leer.

Die Vereinsliste (`clubs/Index.vue`) ignoriert die Einstellung absichtlich und zeigt immer Logo und Name — das ist eine Verwaltungsansicht, in der man Vereine identifizieren können muss. lsverein7 hat sie ebenfalls nur im Kopfbereich ausgewertet.

Vorher war das `Club::displayStyles()`, ein Array mit hartkodierten deutschen Strings — die einzigen Labels der App, die nicht durch `__()` liefen. Enum-Labels folgen jetzt `ClubRole::label()`. `Club::languages()` ist noch so ein Array, dort sind "Deutsch"/"English" aber Endonyme und als solche richtig.

## Locale: der Verein setzt die Sprache, der Benutzer weicht nur ab
`App\Enums\Locale` (string-backed, 'de'/'en') deckt beide Spalten ab. Die Hierarchie ist:

`users.locale` (nullable) → `clubs.locale` (required) → `config('app.locale')`

`User::effectiveLocale()` löst die ersten zwei auf, `SetLocale` hängt den Config-Wert für Gäste und für Benutzer ohne Verein an. **Null auf dem Benutzer heißt „folgt dem Verein"**, nicht „keine Sprache" — wer den Vereinswechsel oder die Vereinssprache ändert, verschiebt damit alle erbenden Benutzer mit.

Migration `2026_08_25_154025_users_locale_nullable`: macht die Spalte nullable und leert sie dort, wo sie exakt der Vereinssprache entsprach. Das betraf alle 16 Bestandsbenutzer — die Vereinssprache existierte als Konzept noch nicht, als diese Zeilen entstanden, niemand hatte also bewusst abweichend gewählt. Verhalten heute unverändert, aber ab jetzt folgen sie dem Verein. `down()` schreibt vor dem NOT NULL wieder einen konkreten Wert zurück.

In `SetLocale` steht bewusst `?->effectiveLocale()->value ?? …` mit nur *einem* Nullsafe: `??` unterdrückt den Property-Zugriff links, und phpstan verwirft den zweiten als tot.

Vorher gab es zwei konkurrierende Listen: `User::availableLocales()` mit `__()`-Labels und `Club::languages()` mit hartkodierten Endonymen ('Deutsch'/'English'). Beide sind ersetzt durch `Locale::options()`.

Im Benutzerformular trägt die Auswahl „(Vereinssprache)" den Sentinel `'inherit'`, den ein Hidden-Input zu `''` macht — reka-ui kann keinen leeren Wert halten, gleiches Muster wie `blsv_id` in SectionFormFields.

## PaymentMethod, MemberFilter und MemberSort ersetzen lsverein7s Magic Values
Drei Enums, alle nach dem Muster von ClubDisplay/Locale (`label()` über `__()`, `options()` als `{id, name}` fürs Frontend):

**`PaymentMethod`** löst `Member::availablePaymentMethods()` ab — die hartkodierten deutschen Labels, die über die Außenstände-Tabelle unübersetzt durchschlugen. `Subscription::debit()` fragt `$member->payment_method->isCollectable()` statt `=== 'k'` und nimmt `->label()` statt eines Array-Lookups; der `paymentMethods`-Scope nimmt **Enum-Fälle, kein rohes `'k'`** (ModelLayerTest pinnt das). **Der Rest dieses Absatzes ist überholt:** die Spalte `members.payment_method` gibt es seit 2026-08-28 nicht mehr, der Wert wird aus den Bankdaten abgeleitet und `Member::factory()->create(['payment_method' => 'k'])` ist wirkungslos. Siehe den eigenen Abschnitt weiter unten.

**`MemberFilter`** ersetzt lsverein7s Integer 0..13 in URL und Controller-`match`. Ein Lesezeichen sagt jetzt `?filter=due_honours` statt `?filter=10`. `NoSubscription` ist admin-only (`isVisibleTo()`), `optionsFor()` filtert danach.

`All` heißt seit 2026-08-27 schlicht **„Alle"**, vorher „Mit Ehemaligen". Der Zweig ist `$query` — er schränkt außer ClubScope nichts ein und enthält damit auch Verstorbene und künftige Mitglieder. In Verein 1 benannte der alte Name 155 von 186 zusätzlichen Zeilen und verschwieg 30 Verstorbene und 1 Eintritt in der Zukunft. Nicht durch einen aufzählenden Namen ersetzen: der ist schon zweimal falsch geworden. `filterLabel()` landet in der Export-Überschrift und im Dateinamen, aus `mit-ehemaligen-2026.csv` wurde damit `alle-2026.csv`.

**`MemberSort`** ersetzt die Integer 1..6. **Jeder Zweig endet auf `surname, first_name, id`** — lsverein7 ließ die meisten Sortierungen unaufgelöst, wodurch ein Mitglied bei Gleichstand auf zwei Seiten oder auf keiner erscheinen konnte.

`Gender` hat jetzt ebenfalls `label()`/`options()`; die Case-Namen bleiben deutsch (`Frau`/`Mann`), weil sie aus den Bestandsdaten stammen, die Labels laufen über `Ms`/`Mr` in de.json.

## Gender: drei Fälle, und die BLSV-Statistik trägt sie seit 2026-08-29 alle
`Gender` hat seit 2026-08-26 drei Fälle: `Frau = 'f'`, `Mann = 'm'`, `Divers = 'd'`. Die Spalte ist `char(1)`, es brauchte also keine Migration. Die Case-Namen bleiben deutsch (Bestandsdaten), die Labels laufen über `Female`/`Male`/`Diverse` in de.json → „Weiblich"/„Männlich"/„Divers". Das Feldlabel heißt **„Geschlecht"** (`Gender`), nicht mehr „Anrede" — deshalb auch die Adjektive statt „Frau"/„Mann".

**Die Abbildung steht an genau einer Stelle: `Gender::blsvValue()`.** Vorher stand an zwei Stellen `$member->gender->value === 'm' ? 'm' : 'w'` — ein diverses Mitglied wäre stillschweigend als weiblich exportiert **und** in der Frauen-Spalte der Altersstatistik gezählt worden. `tests/Feature/MemberManagementTest.php` pinnt alle drei Abbildungen.

**Seit 2026-08-29 ist `Divers` frei.** Der BLSV nimmt einen dritten Wert `d`; `blsvValue()` bildet ihn darauf ab, statt ihn als `w` zu melden. `Gender::selectable()` ist ersatzlos entfallen — `options()` läuft über `self::cases()` und `MemberValidationRules` nutzt wieder das schlichte `Rule::enum(Gender::class)`.

**Die dritte Spalte in `blsv_stat.pdf` erscheint nur, wenn der Verein wirklich ein diverses Mitglied hat** (`BlsvPdf::$showsDiverse`, gesetzt aus `hasDiverseMember()`). Grund: der Verein reicht diesen Bogen seit Jahren zweispaltig ein, eine immer leere Spalte änderte das Formular für nichts. Auf der Abteilungsseite ist das nicht nur Kosmetik — sieben Altersgruppen à drei Spalten passen bei 10 mm nicht mehr auf A4, die Spalten schrumpfen dafür auf 6,7 mm (`$columnWidth`). Wer dort eine Spalte ergänzt, muss die Breite neu rechnen: 190 mm nutzbar, davon 48 mm für Nummer, Abteilung und Gesamt.

Dasselbe Muster steht schon im Dashboard: `AgeStructureCard.vue` blendet den dritten Balken per `v-if="totals.other > 0"` aus. `tests/Feature/SepaGenerationTest.php` pinnt beide Zweige der PDF-Entscheidung; `pdfText()` in tests/Pest.php bläst die FPDF-Streams dafür auf, weil die Texte sonst komprimiert und ungreppbar sind.

## AgeBracket: die BLSV-Altersgrenzen stehen an genau einer Stelle
`App\Enums\AgeBracket` hält die sieben Altersgruppen (0-5, 6-13, 14-17, 18-26, 27-40, 41-60, 61+). **Die Grenzen gehören dem BLSV, nicht uns** — `Club::getStatIndex()` delegiert hierher und `BlsvPdf` druckt genau diese sieben Zeilen, eine verschobene Grenze ändert also, was der Verein an den Verband meldet. Vorher stand die Staffelung als `match (true)` in `Club::getStatIndex()`; sie liegt hier, damit Jahresmeldung, Dashboard-Chart und die Mitglieder-Auswahl dahinter nicht drei verschiedene Linien ziehen können.

`minAge()`/`maxAge()`, **nicht** `from()`/`to()`: `BackedEnum::from()` ist belegt, phpstan bricht sonst mit `enum.methodRedeclaration`.

Der Backing-Wert ist der URL-Teil der Auswahl (`?filter=age_18-26`, gebaut von `filter()`) und liegt damit in Lesezeichen — stabil halten. `apply()` ist die einzige Stelle, an der eine Gruppe zu SQL wird (`members()->ageRange()`), aufgerufen aus `SelectsMembers::applyAgeFilter()`; `AgeBracket::options()` hängt die sieben Einträge an `dynamicFilters()`.

`MemberFilter::Children/Youths/Adults` bleiben daneben bestehen: das sind die drei groben Gruppen, in denen ein Verein sich sonst liest.

**Im UI wird der BLSV bei den Altersgruppen nicht genannt** (2026-08-27): die Grenzen stammen von dort, aber nur ein Teil der Vereine ist Mitglied — die Feuerwehr hat `blsv_member = 0` und der Name sagt ihr nichts. Die Dashboard-Karte heißt deshalb schlicht „Altersstruktur / Wie alt die aktuellen Mitglieder sind". Der Verbandsbezug gehört in den Code (siehe oben), nicht auf den Bildschirm.

## Startseite pro Benutzer: warum `/` nicht über eine geschützte Seite umleitet
`users.landing_page` (NOT NULL, default `dashboard`, gecastet auf `App\Enums\LandingPage`) sagt, wo ein Benutzer nach dem Anmelden landet — Dashboard oder Mitgliederliste, einstellbar unter Einstellungen → Profil, neben der Sprache.

**Drei Teile, und der dritte ist der, den man kaputtmacht:**
1. `App\Http\Responses\LoginResponse` ersetzt Fortifys eigene (gebunden in `FortifyServiceProvider::configureResponses()`, in **boot()**, weil Fortify seine in register() bindet und die Reihenfolge nicht garantiert ist). Sie macht `redirect()->intended($user->landingPage()->url())`. `config('fortify.home')` bleibt stehen, ist aber für den Login nicht mehr maßgeblich.
2. `intended()` schlägt die Einstellung bewusst — wer einem Link auf ein Mitglied gefolgt ist und sich anmelden musste, will dieses Mitglied.
3. **Deshalb schickt `HomeController` (`/`) einen Gast direkt auf den Login und nicht auf `/dashboard`.** Der Umweg über eine geschützte Seite würde funktionieren, hinterlässt aber `/dashboard` als intended URL — und die gewinnt dann gegen die Einstellung, für jeden, der bei `/` anfängt, also für fast alle. Ein früherer Stand (`Route::redirect('/', '/dashboard')`, 2026-08-27, bevor es die Einstellung gab) tat genau das. Nicht zurückbauen.

`User::landingPage()` statt des rohen Attributs benutzen: die Spalte ist NOT NULL, aber ein per `create()` ohne Wert angelegtes Model liest den Default nicht zurück — dieselbe Falle wie bei `users.admin`. `UserFactory` setzt den Wert deshalb ausdrücklich.

In den Enum gehören nur Bildschirme, die **jeder** Account öffnen darf, sonst landet jemand direkt nach dem Login auf einem 403. Dashboard (nur `auth`) und Mitgliederliste (`MemberPolicy::viewAny()` = true) erfüllen das; etwa die Lastschriften (admin-only) nicht.

`landing_page` ist in `ProfileUpdateRequest` `required` (nicht `nullable` wie `locale`): es gibt keine Vereinseinstellung zum Erben, ein leerer Wert hieße also nichts. Folge fürs Testen: **jede** `profile.update`-Nutzlast muss das Feld mitschicken, sonst schlägt die Validierung fehl.

## PaymentMethod ist abgeleitet, nicht gespeichert — und hat keinen dritten Fall
Seit 2026-08-28 ist `members.payment_method` gelöscht (Migration `2026_08_28_101622`). `Member::payment_method` ist ein Accessor: IBAN hinterlegt → `Account`, sonst `Invoice`. Er spiegelt den `hasAccount()`-Scope — beide zusammen halten, nicht einzeln ändern.

**Der dritte Fall `NonPayer` ('n') ist ersatzlos weg.** Er hieß „Nichtzahler" und meinte jemanden, dessen Beitrag eine Familie schon mitbezahlt. Das ist jetzt ein 0-€-Beitrag („Familienmitglied", „Beitragsfrei") — der nennt den Grund, statt nur die Zahlung zu verneinen. Wer nicht zahlen soll, bekommt einen 0-€-Beitrag; **keinen Schalter „trotz Bankdaten nicht einziehen"** wieder einführen, das wäre die Spalte durch die Hintertür.

Die Backing-Werte 'k'/'r' bleiben: sie sind der URL-Teil der Auswahl `?filter=payment_k` und liegen in Lesezeichen.

Vor dem Löschen an Produktion geprüft: alte Regel (`payment_method = 'k'`) und neue (IBAN vorhanden) wählen für alle aktuellen Mitglieder beider Vereine dieselben Mitglied/Beitrag-Paare — 231 Posten / 10.780 € (Verein 1), 131 / 2.111 € (Verein 2). Von 47 abweichenden Zeilen hatte **keine** einen zahlenden Beitrag. Die Spalte widersprach sich ohnehin schon (4× `k` ohne IBAN, 4× `n` mit).

Folgen im Code: `PaymentMethod::options()` speist nur noch die Listen-Auswahl, **nicht** das Mitgliederformular (kein Picker mehr). Die Bankdaten sind dort `required_with` untereinander — alle vier oder keins, weil `generateSepa()` Kontoinhaber, IBAN und BIC zusammen in die XML schreibt. `MemberFactory::payingByAccount()` setzt die Bankdaten, nie eine Zahlungsart.
