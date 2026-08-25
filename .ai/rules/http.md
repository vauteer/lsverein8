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
Sections carry `SharedClubScope`, so the index lists the club's own rows *and* the installation-wide ones (`club_id IS NULL`). Settled points in SectionController/SectionPolicy:

- Shared sections show up for everyone but only a root account (`users.admin`) may edit them; a club admin gets 403. `(bool) $user->admin` in the policy — the column is nullable, so the boolean cast still yields null for accounts that never had the flag.
- `SectionPolicy::delete()` also requires `! $section->isUsed()`. `Club::getBLSVStatistic()` and member history reference the name, so a section any member was ever assigned to is kept. The Edit page's `deletable` prop mirrors this; the route's `->can('delete', 'section')` enforces it.
- The name regex (`/^[\pL\pN?()+,\- ]+$/u`) is load-bearing, not cosmetic: `Club::getBLSVStatistic()` writes one CSV per section named `BE{year}_{$section->name}.csv`, so a slash or dot in the name escapes the downloads directory.
- Name uniqueness is checked against the club's own rows *and* the shared ones, even though the DB unique key is only `(club_id, name)` — a duplicate would otherwise render twice in the same list.
- `blsv_id` is `Rule::prohibitedIf(! currentClub()->blsv_member)` and restricted to `array_keys(Section::BLSV_SECTIONS)`; the form hides the field entirely for non-BLSV clubs (`blsvSections` prop is null).
- `club_id` is never accepted from the request — it is set from `currentClubId()` on create, so a club admin cannot move a section elsewhere or turn it into a shared one.
