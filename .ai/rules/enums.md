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
