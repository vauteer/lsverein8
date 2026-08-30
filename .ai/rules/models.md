---
paths:
  - 'app/Models/**'
  - app/Models/User.php
  - app/Models/Member.php
---

# Models

## Model layer ported from lsverein7 — club scoping, pivots, MySQL-only scopes
The 17 models and 7 pivots came over from lsverein7 with behaviour preserved. Conventions and traps:

- Club scoping is via `#[ScopedBy]` attribute classes, not `booted()` closures. **Every club-owned model carries the one `ClubScope`** (Item, Member, Subscription, Section, Event, Role), which restricts to `currentClubId()`. There was a second scope, `ClubWithSharedScope`, that also let `club_id IS NULL` rows through for the tables that had the column nullable; it was deleted on 2026-08-30 when `sections`, `events` and `roles` all became NOT NULL — Item had already left it on 2026-08-26, where the `IS NULL` disjunct never matched anyway. **Ein Scope bleibt zwingend**: without one, `Role::all()` returns every club's rows.
- `currentClubId()` (app/helpers.php, autoloaded via composer `autoload.files`) returns `1` on the CLI, so tests and artisan always read club 1. Tests must create the club with `['id' => 1]`.
- `Member::$keyDate` is static global state driving every age/membership calculation, private behind `setKeyDate()` / `getKeyDate()` (both copy, because Carbon is mutable). Reset it with `Member::setKeyDate(null)` in `beforeEach`/`afterEach` or tests bleed into each other.
- This app sets `Date::use(CarbonImmutable::class)`; lsverein7 did not. Type-hint `CarbonInterface`, never `Carbon`, or ported code fatals.
- `Member::dueHonor()` is BOTH an instance method and a scope. `Member::dueHonor()` calls the instance method; reach the scope with `Member::query()->dueHonor()`.
- These Member scopes use MySQL-only SQL (`YEAR`, `LEAST`, `FIND_IN_SET`) and cannot run on the SQLite test connection: `dueHonor`, `joined`, `left`, `dead`, `milestoneBirthdays` — plus `MemberSort::Birthday`, which orders by `date_format(birthday, '%m-%d')`. They are exactly the five member-list selections that cannot be exercised over HTTP in a test. Test them by asserting the prepared statement from the thrown `QueryException`, or against MariaDB.
- `app/Pdf` is excluded from phpstan (see phpstan.neon) because it subclasses the untyped fpdf/fpdf library. Everything else must stay clean at level 7. There is NO accepted error: `composer ci:check` runs `phpstan analyse` before `artisan test`, so a single error fails GitHub Actions and the test suite never runs. An earlier version of this note called the starter kit's `UserFactory::withTwoFactor()` stub acceptable — it was not, and CI was red on every push from the initial commit until 2026-08-25. Run `composer ci:check` (not just `artisan test`) before pushing; it is the exact pipeline CI runs.

## Models declare mass assignment with #[Fillable], never $guarded
Every model (pivots included) declares an explicit allowlist via `#[Fillable([...])]` above the class. Do not reintroduce `protected $guarded = []` — lsverein7 used it, which left `id` and the timestamps mass-assignable.

- The list is the table's real columns minus `id`, `created_at`, `updated_at`. When you add a migration column, add it to the model's `#[Fillable]` or writes silently drop it.
- `User` deliberately omits `remember_token` and `email_verified_at`: the framework writes those through `setRememberToken()` / `markEmailAsVerified()`, which bypass mass assignment. Fortify's `ResetUserPassword` uses `forceFill`, so `password` is not required either but is kept fillable for user-management screens.
- Hidden columns also use the attribute: `#[Hidden(['password', 'remember_token'])]` on `User`. No other model hides anything — `Member.iban/bic/bank/account_owner` are serialized to the frontend because the edit forms need them.
- Factories use `forceFill`, so `Model::factory()->create(['id' => 1])` still works despite `id` not being fillable. Tests rely on this to pin the club to id 1 (see `currentClubId()`).
- `club_id` is fillable on the scoped models, matching lsverein7. If request data is ever mass-assigned straight into these models, that lets a caller move a row to another club — validate it in the form request.

## No email verification — do not reintroduce it
Email verification was removed deliberately on 2026-08-22: it was inert (User never implemented `MustVerifyEmail`) but armed, so adding that interface would have locked out all 16 existing users at once, since none had a verification timestamp.

Removed: `Features::emailVerification()` from config/fortify.php, the `verified` middleware from routes/web.php and routes/settings.php, the `email_verified_at` cast, the factory's `unverified()` state, `resources/js/pages/auth/VerifyEmail.vue`, the `mustVerifyEmail` prop and unverified banner in the profile page, and the column itself (migration 2026_08_22_120145).

Do not add `MustVerifyEmail`, the `verified` middleware, or `Features::emailVerification()` back without first restoring the column and backfilling it for existing users.

Unrelated trap worth knowing: `php artisan wayfinder:generate` must be run as `--with-form`. vite.config.ts sets `formVariants: true`, and a bare run silently strips the `.form` variants, which breaks `vue-tsc` across every auth and settings page.

## Query scopes use #[Scope] on protected methods
All 26 query scopes use the `#[Scope]` attribute on a `protected` method without the `scope` prefix (`#[Scope] protected function members(Builder $query, ...)`), not `public function scopeMembers()`.

Two traps:

- The method MUST be `protected`. Laravel only routes `Member::members()` through `__callStatic` when the method is inaccessible from the call site. A `public` scope method turns the same call into a static invocation of an instance method and fatals.
- For the same reason, calling a model's own scope from inside that class statically fails — `Debit::due(...)` resolves the accessible protected method directly. Use `Debit::query()->due(...)`. Calls from other classes (`Member::members()` inside Club or Subscription) are fine, because protected is inaccessible there.

The old `Member::dueHonor()` name clash is resolved: the accessor is `honorYearReached()` (renamed from `honorThisYear()` on 2026-08-30 — it returns a count of membership years read against the key date, so neither "this" nor "year" was true, and "due" would claim an award status it never checks) and `dueHonor` is the scope. This supersedes the earlier note saying the scope was only reachable via `query()` — that applies to every scope now, only for calls made from within the declaring model.

## No two-factor authentication — the scaffolding was removed
Two-factor authentication was removed on 2026-08-25, for the same reason email verification was: inert but armed. `Features::twoFactorAuthentication()` was never in config/fortify.php, no migration ever created the `two_factor_*` columns, and `User` does not use Fortify's `TwoFactorAuthenticatable` trait — so nothing worked, but four tests skipped themselves and pretended coverage.

Removed: `UserFactory::withTwoFactor()`, `App\Http\Requests\Settings\TwoFactorAuthenticationRequest` (SecurityController::edit() now takes no argument), the 2FA test in tests/Feature/Auth/AuthenticationTest.php and the three in tests/Feature/Settings/SecurityTest.php. `TestCase::skipUnlessFortifyHas()` stays — PasswordResetTest still guards on `Features::resetPasswords()`.

The empty `withTwoFactor(): static {}` stub was also the single phpstan error that had kept GitHub Actions red on every push since the initial commit. To bring 2FA back you need all of it: the Fortify migration, the trait on User, the feature in config/fortify.php, and the frontend — the starter kit's Vue pages for it were never ported. Related: the email-verification removal note above.

## users.admin is NOT NULL, but $user->admin is null on an unrefreshed model
`users.admin` is `boolean NOT NULL DEFAULT 0` — verified with SHOW COLUMNS against the live database. It is never null in the table, and `User` casts it to `boolean`.

The null comes from the model, not the column. `User::factory()->create()` (or any `create()`) without an explicit `admin` does not read the column default back into the instance, so the attribute is simply absent from `getAttributes()` and `$user->admin` returns null until the row is re-read. Anything typed `: bool` that returns `$user->admin` raw will fatal — that is why SectionPolicy, EventPolicy and the `viewLogViewer` gate all cast with `(bool)`.

Do not "simplify" those casts away, and do not repeat the older explanation that the column is nullable: it is not, and that wording caused the cast to be removed once and broke seven tests.

## debits hat kein club_id — MemberClubScope holt den Verein über das Mitglied
`debits` ist die einzige Datentabelle ohne eigene `club_id`; sie hängt allein an `member_id`. Deshalb trägt `Debit` `#[ScopedBy([MemberClubScope::class])]` — der Scope baut `whereIn('member_id', Member::query()->select('members.id'))`, und Members eigener ClubScope ist es, was die Unterabfrage einengt. Der Verein steht so nur an einer Stelle.

Das ist keine Kosmetik: lsverein7 hatte hier gar keinen Scope, `Debit::debit()` sammelte also die Lastschriften **aller** Vereine ein und löschte sie danach. Beide Abfragen in `Debit::debit()` laufen über `Debit::query()`, greifen den Scope also mit. `tests/Feature/DebitManagementTest.php` pinnt das ("the collection never reaches another club, not even to delete").

Folge für Route Model Binding: eine fremde Lastschrift ergibt 404, nicht 403 — wie bei `scopedUser()`. `DebitPolicy::update()` prüft trotzdem `hasAdminRights($debit->member->club_id)`, damit die Policy für sich genommen stimmt.

`DebitFactory` erzeugt per Default einen Member in einem *neuen* Verein — genau richtig, um in Tests eine „fremde" Lastschrift zu bekommen.

## Member::isUsed() — club_member zählt bewusst nicht mit
Sieben Tabellen tragen ein `member_id`, **alle ON DELETE CASCADE** (an der Produktionsdatenbank geprüft, 2026-08-26): `club_member`, `member_section`, `member_role`, `event_member`, `member_subscription`, `item_member`, `debits`. Die Datenbank nimmt eine Löschung also klaglos samt kompletter Historie mit — `MemberPolicy::delete()` mit `! $member->isUsed()` ist die **einzige** Bremse, nicht die Übersetzung eines DB-Fehlers in ein 403. Gleiches Muster wie bei Subscription und Item.

`isUsed()` prüft sechs der sieben Tabellen. **`club_member` ist absichtlich ausgenommen**: `MemberController::store()` legt sie bei jedem neuen Mitglied an, ein Mitglied ohne Mitgliedschaft gibt es also gar nicht — würde sie mitzählen, wäre nie jemand löschbar und der Knopf wäre reine Dekoration.

Folge, und so gewollt: wer je im Verein war, hat Abteilungen, Funktionen oder Ehrungen und ist damit **nicht** löschbar. Für einen Austritt gibt es `resign()`. Löschen ist für eine Zeile, die es nie hätte geben dürfen — wer sie doch löschen will, räumt die Relationen erst auf der Mitgliederseite weg. Damit ist der Verlust eine bewusste Folge von Einzelschritten statt einer stillen Nebenwirkung von „Löschen".

`debits` gehört bewusst dazu: eine noch nicht eingezogene Lastschrift darf nicht mit dem Mitglied verschwinden.

Der Löschdialog auf `members/Edit.vue` sagt deshalb „Zu diesem Mitglied ist nichts erfasst, es ist also nichts weiter betroffen" — der frühere Text versprach das Gegenteil und listete auf, was alles mitgelöscht wird.

## members_count zählt aktuelle Mitglieder — und muss zur verlinkten Auswahl passen
Die Spalte „Mitglieder" auf den Listen von Abteilungen, Funktionen und Inventar ist ein **Link** auf die Mitgliederliste mit der passenden Auswahl (`section_X`, `role_X`, `item_X`). Damit die Zahl und das Ziel nicht auseinanderlaufen, zählt sie seit 2026-08-26 nur noch **aktuelle** Zuordnungen: `Section|Role|Item::withCurrentMemberCount()` statt `withCount('members')`.

Vorher zählte sie jede Pivot-Zeile, die je existierte. An den Produktionsdaten: Fussball zeigte **222**, die Auswahl liefert **103**; Tennis 139 → 68; „Ausgetreten" 3 → 0. Ein Klick auf die Zahl landete also auf der Hälfte der versprochenen Leute.

**Jeder Scope spiegelt genau den Filter, auf den er verlinkt** — das ist die tragende Eigenschaft, nicht die Formulierung:

| Scope | spiegelt | Operatoren |
| --- | --- | --- |
| `Section::withCurrentMemberCount()` | `Member::inSections()` | `from <= keyDate`, `to >= keyDate` |
| `Role::withCurrentMemberCount()` | `Member::hasRole()` | `from < keyDate`, `to > keyDate` |
| `Item::withCurrentMemberCount()` | `Member::hasItem()` | `from < keyDate`, `to > keyDate` |

Die **unterschiedlichen Operatoren sind Absicht**: Abteilungen sind an beiden Enden einschließend, Funktionen und Inventar strikt. Das kommt aus lsverein7 und wird gespiegelt, **nicht** vereinheitlicht — wer nur den Zähler anpasst, bringt ihn wieder aus dem Tritt mit der Auswahl. Eine Vereinheitlichung müsste die drei Member-Scopes ändern und damit, was die Auswahlen liefern.

Alle drei hängen zusätzlich an `Member::memberIds()` (lebend, offene Mitgliedschaft) — dieselbe Menge, die `members()` liefert, weil die Filter `$query->members()->inSections(...)` lauten.

`count(*)` über die Pivot-Zeile, nicht `count(distinct member_id)`: zwei gleichzeitig offene Zeiträume für dasselbe Paar gibt es nicht (2026-08-26 in Produktion geprüft).

`isUsed()` ist davon **unberührt** und zählt weiter jede Zeile — für „darf gelöscht werden" ist die Historie die richtige Frage.

Testfalle: `insert_roles_defaults` sät sieben installationsweite Funktionen (u. a. „Kassier"), `insert_events_defaults` sieben Ehrungen. Eine Fixture mit so einem Namen kollidiert oder sortiert sich davor — in `RoleManagementTest` deshalb „Platzwart" plus `search`.
