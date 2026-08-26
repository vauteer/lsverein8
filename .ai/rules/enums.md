---
paths:
  - 'app/Enums/**'
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

**`PaymentMethod`** (`k`/`r`/`n`) löst `Member::availablePaymentMethods()` ab — die hartkodierten deutschen Labels, die über die Außenstände-Tabelle unübersetzt durchschlugen. `members.payment_method` ist jetzt darauf gecastet. Zwei Stellen hängen daran: `Subscription::debit()` fragt `$member->payment_method->isCollectable()` statt `=== 'k'` und nimmt `->label()` statt eines Array-Lookups, und der `paymentMethods`-Scope nimmt **Enum-Fälle, kein rohes `'k'`** (ModelLayerTest pinnt das). Schreiben mit dem Backing-Wert geht weiter, `Member::factory()->create(['payment_method' => 'k'])` bleibt gültig.

**`MemberFilter`** ersetzt lsverein7s Integer 0..13 in URL und Controller-`match`. Ein Lesezeichen sagt jetzt `?filter=due_honours` statt `?filter=10`. `NoSubscription` ist admin-only (`isVisibleTo()`), `optionsFor()` filtert danach.

**`MemberSort`** ersetzt die Integer 1..6. **Jeder Zweig endet auf `surname, first_name, id`** — lsverein7 ließ die meisten Sortierungen unaufgelöst, wodurch ein Mitglied bei Gleichstand auf zwei Seiten oder auf keiner erscheinen konnte.

`Gender` hat jetzt ebenfalls `label()`/`options()`; die Case-Namen bleiben deutsch (`Frau`/`Mann`), weil sie aus den Bestandsdaten stammen, die Labels laufen über `Ms`/`Mr` in de.json.

## Gender: drei Fälle, aber die BLSV-Statistik kennt nur zwei
`Gender` hat seit 2026-08-26 drei Fälle: `Frau = 'f'`, `Mann = 'm'`, `Divers = 'd'`. Die Spalte ist `char(1)`, es brauchte also keine Migration. Die Case-Namen bleiben deutsch (Bestandsdaten), die Labels laufen über `Female`/`Male`/`Diverse` in de.json → „Weiblich"/„Männlich"/„Divers". Das Feldlabel heißt **„Geschlecht"** (`Gender`), nicht mehr „Anrede" — deshalb auch die Adjektive statt „Frau"/„Mann".

**Der Haken: `Club::getBLSVStatistic()` kann kein drittes Geschlecht.** Das CSV-Format gehört dem BLSV und trug immer nur `m` und `w`. Vorher stand an zwei Stellen `$member->gender->value === 'm' ? 'm' : 'w'` — ein diverses Mitglied wäre also stillschweigend als weiblich exportiert **und** in der Frauen-Spalte der Altersstatistik gezählt worden.

Das ist jetzt `Gender::blsvValue()`: gleiches Ergebnis, aber an **einer** Stelle und als bewusste Annahme dokumentiert statt als Nebenwirkung eines Ternärs. `tests/Feature/MemberManagementTest.php` pinnt alle drei Abbildungen.

**`Divers` ist deshalb geparkt, nicht aktiv.** `Gender::selectable()` gibt nur `Frau` und `Mann` zurück; `options()` baut darauf auf (Picker) und `MemberValidationRules` nutzt dieselbe Liste über `Rule::enum(Gender::class)->only(Gender::selectable())`. Bewusst **beides**: nur aus dem Picker nehmen würde einen von Hand geschickten Wert weiter durchlassen.

**Offen:** ob der BLSV einen dritten Wert annimmt (`d`? `x`? eigene Zeile?), ist ungeklärt. Vor dem nächsten Statistiklauf beim Verband nachfragen. Zum Einschalten danach: `selectable()` auf `self::cases()` und `blsvValue()` klären — sonst nichts. Der Test „the diverse gender is parked" wird dann rot und zeigt, was anzupassen ist.
