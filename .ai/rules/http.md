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
