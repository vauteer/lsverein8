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
