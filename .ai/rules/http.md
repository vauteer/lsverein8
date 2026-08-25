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

- Shared sections show up for everyone but only a root account (`users.admin`) may edit them; a club admin gets 403. `(bool) $user->admin` in the policy — the column is nullable, so the boolean cast still yields null for accounts that never had the flag.
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
