---
paths:
  - 'app/Models/**'
  - app/Models/User.php
---

# Models

## Model layer ported from lsverein7 — club scoping, pivots, MySQL-only scopes
The 17 models and 7 pivots came over from lsverein7 with behaviour preserved. Conventions and traps:

- Club scoping is via `#[ScopedBy]` attribute classes, not `booted()` closures. `ClubScope` (Member, Subscription) restricts to `currentClubId()`; `SharedClubScope` (Event, Item, Role, Section) also lets `club_id IS NULL` rows through. Both wrap the condition in a nested closure — lsverein7 did not, so its `orWhere` leaked past other conditions and returned other clubs' rows.
- `currentClubId()` (app/helpers.php, autoloaded via composer `autoload.files`) returns `1` on the CLI, so tests and artisan always read club 1. Tests must create the club with `['id' => 1]`.
- `Member::$_keyDate` is static global state driving every age/membership calculation. Reset it in `beforeEach`/`afterEach` or tests bleed into each other.
- This app sets `Date::use(CarbonImmutable::class)`; lsverein7 did not. Type-hint `CarbonInterface`, never `Carbon`, or ported code fatals.
- `Member::dueHonor()` is BOTH an instance method and a scope. `Member::dueHonor()` calls the instance method; reach the scope with `Member::query()->dueHonor()`.
- These Member scopes use MySQL-only SQL (`YEAR`, `LEAST`, `FIND_IN_SET`) and cannot run on the SQLite test connection: `dueHonor`, `joined`, `retired`, `dead`, `milestoneBirthdays`. Test them by asserting the prepared statement from the thrown `QueryException`, or against MariaDB.
- `app/Pdf` is excluded from phpstan (see phpstan.neon) because it subclasses the untyped fpdf/fpdf library. Everything else must stay clean at level 7; the only accepted error is the starter kit's `UserFactory::withTwoFactor()` stub.

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
