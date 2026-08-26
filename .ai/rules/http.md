---
paths:
  - 'app/Http/**'
---

# Http

## User management: club_user role is editable, users.admin is not
Two separate permission concepts, easy to conflate:
- `club_user.role` (ClubRole Basic/Advanced/Admin) is the per-club role. It IS editable through the user CRUD form.
- `users.admin` is the global superuser/root flag. It is deliberately ABSENT from UserValidationRules — letting a club admin submit it would be privilege escalation. UserPolicy also blocks a club admin from editing or deleting any account with `admin = true`.

Other settled points in UserController:
- Creating a user with an email that already exists anywhere attaches that account to the current club instead of creating a duplicate; name/phone/locale are left alone. UserStoreRequest::existingUser() drives both the rule set and that branch.
- New accounts get `Str::password(40)` plus `Password::sendResetLink()`. lsverein7 mailed a plaintext random password and wrote it to the log — do not reintroduce that.
- Deleting detaches from the current club; the account is only deleted when it belonged to no other club.
- `scopedUser()` 404s on a user outside the current club — route model binding alone does not scope by club.
- Impersonation (ImpersonationController) is root-only, never onto another root, and forgets the recaller cookie so the session guard can't silently revert the swap. `impersonate.destroy` sits outside the policy gate because the impersonated session is not root. HandleInertiaRequests shares the impersonator as `auth.impersonator` = `{id, name}|null` (resolved from the `impersonator_id` session key), not a boolean — ImpersonationBanner.vue names them in both the message and the way-back link.

## Section CRUD: shared rows, path-safe names, used sections are undeletable
Sections carry `ClubWithSharedScope`, so the index lists the club's own rows *and* the installation-wide ones (`club_id IS NULL`). Settled points in SectionController/SectionPolicy:

- Shared sections show up for everyone but only a root account (`users.admin`) may edit them; a club admin gets 403. The `(bool) $user->admin` in the policy IS load-bearing, but not for the reason an earlier version of this note gave: the column is `boolean NOT NULL DEFAULT 0` (verified against the live database), never nullable. The null comes from the *model*, not the column — `User::factory()->create()` without an explicit `admin` never reads the column default back, so the attribute is absent from the instance and `$user->admin` returns null until the row is re-read. Dropping the cast makes the policy fatal on a `: bool` return.
- `SectionPolicy::delete()` also requires `! $section->isUsed()`. `Club::getBLSVStatistic()` and member history reference the name, so a section any member was ever assigned to is kept. The Edit page's `deletable` prop mirrors this; the route's `->can('delete', 'section')` enforces it.
- The name regex (`/^[\pL\pN?()+,\- ]+$/u`) is load-bearing, not cosmetic: `Club::getBLSVStatistic()` writes one CSV per section named `BE{year}_{$section->name}.csv`, so a slash or dot in the name escapes the downloads directory.
- Name uniqueness is checked against the club's own rows *and* the shared ones, even though the DB unique key is only `(club_id, name)` — a duplicate would otherwise render twice in the same list.
- `blsv_id` is `Rule::prohibitedIf(! currentClub()->blsv_member)` and restricted to `array_keys(Section::BLSV_SECTIONS)`; the form hides the field entirely for non-BLSV clubs (`blsvSections` prop is null).
- `club_id` is never accepted from the request — it is set from `currentClubId()` on create, so a club admin cannot move a section elsewhere or turn it into a shared one.

## Event CRUD mirrors the Section CRUD, minus the path-safe name regex
EventController/EventPolicy/EventValidationRules are a deliberate copy of the Section equivalents: ClubWithSharedScope rows (`club_id IS NULL`) are listed for every club but only a root account may edit them, `club_id` is never accepted from the request (set from `currentClubId()` on create), name uniqueness is checked against the club's own rows *and* the shared ones, and `delete()` requires `! $event->isUsed()`.

Two differences from Sections, both intentional:
- No `regex:` on the name. Section names become BLSV CSV filenames (`BE{year}_{name}.csv`); event names are never used in a path, so any character is fine.
- `event_member.event_id` is ON DELETE RESTRICT (member_section is not), so a used event would be refused by the database anyway — the policy check is what turns that into a 403 instead of a 500.

Test trap: migration `2022_08_20_165538_insert_events_defaults` seeds seven installation-wide events ('25 Jahre' … 'Ehrenvorstand', ids 1–7, `club_id` null). They are present in every test database, so `Event::firstOrFail()` returns a seeded row rather than the one just created, and any fixture named '50 Jahre' collides on the unique rule. tests/Feature/EventManagementTest.php scopes with `where('club_id', 1)` and offers `withoutDefaultEvents()` for the assertions that need an exact listing size. Sections have no such defaults migration — that is the only reason SectionManagementTest can assert sizes directly.

## Club CRUD: root sieht alles, Club-Admin nur den aktuellen Verein
ClubPolicy trennt zwei Ebenen, anders als bei den ClubWithSharedScope-CRUDs:

- `viewAny`/`create`/`delete`: nur root (`users.admin`). Die Liste ist die ganze Installation, deshalb hat ein Club-Admin gar keinen Index — er kommt über einen eigenen Sidebar-Eintrag direkt auf `clubs.edit` seines aktuellen Vereins.
- `update`: root für jeden Verein; Club-Admin nur wenn `$club->id === currentClubId()`. Wer einen anderen seiner Vereine ändern will, wechselt erst — dadurch bleibt "welchen Verein ändere ich gerade?" allein aus der Sidebar beantwortbar.
- `delete`: zusätzlich `! $club->isUsed()` und niemals der aktuelle Verein. Neun Tabellen hängen mit ON DELETE CASCADE an `clubs`; ein Löschen von Verein 1 würde 585 Mitglieder samt Historie mitnehmen. Am 2026-08-25 so entschieden (Alternative "root darf alles löschen" wurde verworfen).
- `switchTo`: root in jeden Verein — das ist der einzige Weg, wie root einen fremden Verein überhaupt sieht, weil alle Scopes auf `users.club_id` hängen. Alle anderen nur in eigene Mitgliedschaften.

Fallstricke:

`Club::isUsed()` muss `withoutGlobalScope(ClubScope::class)` auf `subscriptions()` legen. Subscription trägt ClubScope, sonst prüft die Abfrage den *handelnden* Verein statt des geprüften und meldet fremde Beiträge als nicht vorhanden. `members()` wirft den Scope schon in der Relation ab.

Der Wechsel schreibt `users.club_id` (ClubSwitchController), nicht Session-State — wie lsverein7, überlebt Logout. In Tests ist das nicht über die Scopes beobachtbar: `currentClubId()` liefert auf der CLI immer 1. Tests müssen `users.club_id` direkt prüfen, und wer die "aktueller Verein"-Sperre testen will, hängt den root-Account an einen *zweiten* Verein, damit Verein 1 leer bleibt.

Logo-Upload (seit 2026-08-25): `ClubController::applyLogo()` speichert, ersetzt oder leert `clubs.logo` und ruft danach `Club::removeOrphanLogos()`. `remove_logo` gewinnt gegen eine gleichzeitig gesendete Datei.

Wichtig für Tests: der Sweep läuft bei **jedem** store und update, nicht nur beim Hochladen — siehe die Regel zur Speicher-Isolierung unter `tests/**`.

## Subscription CRUD: kein Shared-Zweig, Cascade macht die Policy zur einzigen Bremse
SubscriptionController/Policy/ValidationRules folgen dem Section-/Event-/Role-Muster, mit drei bewussten Abweichungen:

- Subscription trägt `ClubScope`, nicht `ClubWithSharedScope`. Es gibt also keine installationsweiten Zeilen (`club_id` nie null), keinen root-only-Zweig in der Policy, kein `shared`-Feld in der Resource und keinen `(bool) $user->admin`-Cast. Die Unique-Regel prüft nur `club_id = currentClubId()` und deckt sich damit exakt mit dem DB-Key `unique(club_id, name)`.
- `member_subscription.subscription_id` ist ON DELETE CASCADE (member_role ist RESTRICT, member_section gar nichts). Die Datenbank würde die Zuordnungen also klaglos mitlöschen — `SubscriptionPolicy::delete()` mit `! $subscription->isUsed()` ist die einzige Bremse, nicht bloß die Übersetzung eines DB-Fehlers in ein 403.
- `transfer_text` darf die globale `SEPA_REGEX` NICHT wiederverwenden: die verbietet `<` und `>`, der Verwendungszweck braucht sie aber für die Platzhalter `<AJ>/<VN>/<NN>`. Dafür steht die globale Konstante `TRANSFER_TEXT_REGEX` in app/helpers.php, neben `SEPA_REGEX` (bis 2026-08-26 lag sie als `protected const` in `SubscriptionValidationRules`; sie wurde herausgezogen, als das Lastschrift-CRUD dieselbe Regel brauchte). Unbedenklich, weil `Subscription::generateSepa()` die Platzhalter ersetzt, bevor der Text in die XML geht — übertragen wird also SEPA-sauberer Text.

`subscriptions.amount` ist decimal(8,2) und kommt aus MySQL als String, aus SQLite als Float zurück; deshalb hat das Model jetzt `casts()` mit `'amount' => 'float'`. Die Resource formatiert zusätzlich `amount_label` serverseitig (deutsches Dezimalkomma gehört nicht in ein Vue-Template, gleiche Begründung wie bei ClubDisplay).

Die Sammel-Abbuchung ist seit 2026-08-26 portiert — `SubscriptionController::debit()`, `subscriptions/Debit.vue` und `SubscriptionDebitDialog.vue`; wie sie aussieht und was daran hängt, steht im nächsten Abschnitt. (Ein früherer Stand dieser Notiz sagte „nicht portiert, es fehlt nur die UI"; das galt nur bis zu diesem Datum.) Nicht mitgekommen ist `Subscription::VAR_DESCRIPTION` (hartkodiertes deutsches 'Variablen: <AJ> Jahr, …') — der Hinweis läuft jetzt über `$t()`/de.json.

## Sammel-Abbuchung und der /downloads-Ausgang
Die Beitragsliste ist nach `amount`, dann `name` sortiert (nicht nach Name wie die Geschwister-CRUDs) — so wie lsverein7. `SubscriptionController::pageOf()` spiegelt genau diese Reihenfolge; `(club_id, name)` ist unique, deshalb bricht `name` einen Betrags-Gleichstand vollständig und es braucht keine id-Ebene.

Die Sammel-Abbuchung ist eine eigene Aktion mit Dialog (`SubscriptionDebitDialog.vue`), **keine** Auswahlspalte in der Tabelle. Die erste Fassung hatte Checkboxen pro Zeile plus eine Werkzeugleiste; am 2026-08-26 verworfen, weil nicht erkennbar war, wie man überhaupt abbucht. Nicht zurückbauen.

Zwei Dinge, die daran hängen:
- Der Dialog bekommt `debitable` aus `SubscriptionController::debitOptions()` — **alle** Beiträge des Vereins, ungefiltert und unpaginiert. Was abgebucht wird, darf nicht davon abhängen, auf welcher Seite oder in welcher Suche der Benutzer gerade steht.
- Beiträge mit `amount = 0` (Ehrenmitglieder) tauchen dort nicht auf, und `SubscriptionDebitRequest` lehnt sie zusätzlich mit `->where('amount', '>', 0)` ab. In der Tabelle bleiben sie sichtbar. `freeCount` sagt dem Dialog nur, ob er die Lücke erklären soll.

Sammel-Abbuchung (`POST subscriptions/debit` → `subscriptions/Debit`):
- `SubscriptionPolicy::debit()` = `hasAdminRights()`, weil die Aktion eine SEPA-Datei für den ganzen Verein schreibt.
- `SubscriptionDebitRequest` muss `Rule::exists()` **von Hand** auf `club_id = currentClubId()` einschränken: `exists` setzt eine einfache Query ab und erbt den ClubScope des Models nicht. Ohne das könnte ein Club-Admin fremde Beiträge einziehen.
- Die Ids gehen als `array_values(array_map(...))` weiter — `Subscription::debit()` erwartet `list<int>`, und `array_map` allein behält Lücken aus einem `subscriptions[3]`-Payload.
- Der POST rendert eine Seite (kein Redirect), wie in lsverein7. Ein Reload der Ergebnisseite bucht also erneut ab.

`formatAmount()` in app/helpers.php ist die einzige Stelle, die einen Betrag formatiert; `Subscription::amountLabel()` und `Debit::amountLabel()` reichen nur durch (Resource, Dialog-Liste, `__toString()`). Der vierte Parameter von `number_format()` ist dort nicht optional: er steht per Default ebenfalls auf ',', wodurch 1234.5 als "1,234,56" herauskam. Deutsche Dezimalkommas gehören serverseitig gesetzt, nicht ins Vue-Template.

Bei UI-Texten mit Zähler aufpassen: `$t()` pluralisiert nicht. ":count Beiträge" liest sich bei 1 falsch — entweder eine Formulierung wählen, die nicht flektiert (":selected von :total ausgewählt"), oder `trans_choice`.

`GET downloads/{filename}` (DownloadController, Gate `downloadGeneratedFiles` = hasAdminRights) ist der einzige Ausgang für `storage/downloads`. Die Dateien liegen mit `{club_id}_`-Präfix, die URL trägt nur den nackten Namen — der Controller setzt den Präfix des *aufrufenden* Vereins davor, damit eine URL nie die Datei eines anderen Vereins benennen kann. Das ist auch der Weg für die BLSV-Statistik, die seit 2026-08-26 einen Bildschirm hat (BlsvStatisticController — siehe die Regel unter app/Http/Controllers/BlsvStatisticController.php).

`Subscription::generateSepa()` legt `storage/downloads` jetzt selbst an (`File::ensureDirectoryExists`) — das Verzeichnis ist gitignored und nach einem Deploy weg, `file_put_contents` wäre sonst beim ersten Einzug fatal.

Fallstrick für Tests: `storage/downloads` ist bewusst nicht gefakt (siehe Regel unter `tests/**`). Eine Fixture-Datei für einen „anderen Verein" deshalb nie unter einer echten Club-Id ablegen — SubscriptionManagementTest nutzt 999, damit sie nichts überschreibt, was Verein 2 wirklich erzeugt hat.

Erledigt am 2026-08-26 mit dem Mitglieder-CRUD: `Member::availablePaymentMethods()` ist weg, `members.payment_method` ist auf `App\Enums\PaymentMethod` gecastet, und die Außenstände-Tabelle bekommt `->label()`. Siehe die Enum-Regel.

## Item-CRUD: Inventar ist pro Verein abschaltbar, und items.club_id ist NOT NULL
Zwei Dinge, in denen das Inventar von Abteilungen/Ehrungen/Funktionen abweicht — beide an der echten Datenbank geprüft (2026-08-26):

**`items.club_id` ist NOT NULL.** `sections`, `events` und `roles` haben die Spalte nullable und darüber ihre installationsweiten Zeilen; `items` (und `subscriptions`) nicht. Es kann also keine vereinsübergreifenden Gegenstände geben. Folge: ItemPolicy hat **keinen** root-only-Zweig, ItemResource kein `shared`-Feld, und die Unique-Regel prüft nur `club_id = currentClubId()` ohne `orWhereNull`. Das entspricht lsverein7, dessen ItemPolicy ebenfalls keinen Null-Zweig hatte — ein Helm gehört einer Feuerwehr, nicht der Installation.

`Item` trug beim Portieren des Model-Layers `ClubWithSharedScope`, obwohl der `club_id IS NULL`-Zweig bei einer NOT-NULL-Spalte nie greifen kann. Am 2026-08-26 auf `ClubScope` umgestellt — dieselbe erzeugte SQL (`where club_id = 1`), aber die Deklaration behauptet nichts mehr, was das Schema nicht hergibt. **Ein Scope bleibt zwingend**: ohne ihn liefert `Item::all()` das Inventar aller Vereine.

Wer geteilte Gegenstände einführen will, braucht beides: eine Migration, die `items.club_id` nullable macht, **und** die Rückkehr zu `ClubWithSharedScope` samt Shared-Zweig in Policy, Resource und Unique-Regel. `tests/Feature/ItemManagementTest.php` pinnt die NOT-NULL-Zusage mit einem Test, der beim Ändern der Spalte rot wird.

**Das Inventar ist pro Verein abschaltbar (`clubs.use_items`).** `ItemPolicy::viewAny()` gibt `currentClub()->use_items` zurück, und `create`/`update`/`delete` hängen daran — alle sechs Routen antworten also mit 403, wenn der Verein kein Inventar führt, nicht nur der Sidebar-Eintrag fehlt. Das weicht bewusst von lsverein7 ab, das nur den Navigationseintrag versteckte (`visible: club.value.useItems`) und /items per Adresszeile offen ließ. Vorbild ist `blsv_id`, das ebenfalls versteckt **und** `prohibitedIf` ist.

Root ist nicht ausgenommen: ein root-Account arbeitet immer innerhalb eines Vereins (alle Scopes hängen an `users.club_id`), sieht das Inventar also dort, wo eines geführt wird, und wechselt sonst den Verein.

Der Sidebar-Eintrag hängt an `currentClub.uses_items` aus HandleInertiaRequests — neu geteilt, damit Eintrag und Policy nicht auseinanderlaufen können. Produktionsstand: Verein 1 (Sportverein) `use_items = false`, Verein 2 (Feuerwehr) `true` mit „Jacke Bayern 2000" und „Helm 1950".

UI-Wortwahl: die Navigation heißt „Inventar" (wie lsverein7 und wie das Vereinsformular-Label „Inventar verwenden"), die einzelne Zeile ist ein „Gegenstand" — gleiches Muster wie Funktionen/Ehrungen. `item_member` trägt `from`/`to`, ein Gegenstand wird also für einen Zeitraum ausgegeben, nicht dauerhaft zugeordnet; die Texte sagen deshalb „ausgegeben".

## Lastschrift-CRUD: Admin-only, Abbuchung ist datumsgesteuert und löscht
DebitController folgt dem Beitrags-CRUD, mit vier bewussten Abweichungen:

- **Alles admin-only**, auch `viewAny` (Beiträge sind für jeden im Verein sichtbar). Eine Zeile nennt ein Mitglied und das Geld, das dessen Konto verlässt. Der Sidebar-Eintrag hängt an `auth.canManageDebits` aus HandleInertiaRequests, über die Policy aufgelöst.
- **Die Abbuchung hat keine Auswahl.** `POST debits/collect` nimmt nur ein Datum: `Debit::debit()` sammelt alles bis dahin Fällige und **löscht** es. Deshalb kein Dialog mit Checkboxen wie bei den Beiträgen, nur ein Datumsfeld (`DebitCollectDialog.vue`). Der POST rendert eine Seite (`debits/Collect`) statt umzuleiten, wie bei den Beiträgen; ein Reload ist hier ungefährlich, weil die Zeilen dann weg sind.
- **Der Controller weist eine leere Abbuchung ab** (`$collected === 0` → Toast + Redirect). Ohne das schriebe `generateSepa()` eine SEPA-Datei ohne Zahlungen und böte sie zum Download an. lsverein7 tat genau das.
- **`memberOptions()` filtert nicht auf aktive Mitglieder**, anders als lsverein7 (`Member::members()->hasAccount()`), sondern nur auf `hasAccount()`. Grund: eine Lastschrift ist gerade für jemanden nützlich, der eben ausgetreten ist, und ein Picker, der das Mitglied einer bestehenden Lastschrift wegfallen lässt, macht die Zeile unspeicherbar. `DebitValidationRules` prüft exakt dieselbe Menge (Club + `iban <> ''`), von Hand geklubt, weil `exists` den ClubScope nicht erbt. `DebitFormFields.vue` hängt zusätzlich das Mitglied der bearbeiteten Zeile in den Picker, falls dessen IBAN inzwischen gelöscht wurde. Das Label zeigt die **volle** IBAN (`normalizeIban()`, in Vierergruppen), nicht `Member::accountNumber()` — die Kurzform aus lsverein7 reicht zum Gegenprüfen nicht; am 2026-08-26 so geändert.

`amount` ist `between:0.01,...` — anders als ein 0-€-Beitrag für Ehrenmitglieder ist eine Lastschrift über 0 eine Anweisung, nichts zu bewegen.

## Mitglieder-CRUD: Stichdatum, Auswahl-Fallback und was der Nicht-Admin nicht sieht
Das Mitglieder-CRUD ist bewusst in drei Etappen geschnitten. **Diese Etappe: nur das CRUD selbst** — Index (Suche, feste + dynamische Auswahlen, Sortierung, Stichjahr), Create mit Eintrittsdaten, Edit, Show, Delete, Resign. **Noch nicht portiert:** die sechs Pivot-Unter-CRUDs auf der Bearbeitungsseite (Vereine, Abteilungen, Beiträge, Funktionen, Ehrungen, Inventar) und die vier Exporte (Mitglieder-PDF, Funktionen-PDF, CSV, vCard). `MemberPdf`/`MemberRolesPdf` liegen schon in app/Pdf, `resources/views/vcards.blade.php` fehlt noch.

Fünf Dinge, die man beim Weiterbauen wissen muss:

- **`Member::$_keyDate` in `MemberController::selection()` zu setzen ist der tragende Seiteneffekt.** Jede Alters-, Mitgliedschafts- und Ehrungsrechnung liest ihn, auch die in `MemberResource`. Das Stichjahr aus der URL ist also nicht bloß ein Filter, sondern verschiebt, wie die ganze Liste gelesen wird. `resolveYear()` klemmt statt zu 404en.
- **Eine unbekannte oder für den Benutzer gesperrte Auswahl fällt auf die Standardauswahl zurück, kein 403/404.** Filter leben in Lesezeichen und im Zurück-Knopf. Gleiches gilt für eine unbekannte Sortierung.
- **`MemberResource` verschweigt einem Nicht-Admin `subscriptions` und `last_event` (null),** und die Bankdaten stehen gar nicht drin — die gehen nur ans Bearbeitungsformular, das ohnehin admin-only ist. lsverein7 schickte alles und blendete es im Template mit `v-if="clubAdmin"` aus.
- **Der Index muss `memberships`, `sections`, `roles`, `subscriptions`, `events` eager-laden.** `MemberResource` rechnet alles daraus; ohne das ist die Liste eine Abfrage pro Zeile und Relation.
- **`members.member_id` (die Vereinsnummer, nicht der PK) vergibt der Controller,** `max('member_id') + 1` unter dem ClubScope. Nie aus dem Formular — sonst vergeben zwei Admins gleichzeitig dieselbe.

`resign` beendet zu einem Datum alle offenen Mitgliedschaften und Abteilungen und ist der **normale** Austritt; `destroy` löscht wirklich und `members` kaskadiert in alle sechs Pivots. Der Löschdialog sagt das und verweist auf „Mitgliedschaft beenden".

Fallstrick beim Testen: fünf Auswahlen (`milestone_birthdays`, `deaths`, `joined`, `retired`, `due_honours`) hängen an MySQL-only-Scopes, die SQLite nicht ausführt. `MemberManagementTest` prüft sie über die `QueryException` des Prepared Statements — nicht versuchen, sie über HTTP grün zu bekommen.
