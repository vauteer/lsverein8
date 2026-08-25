---
paths:
  - 'resources/js/**'
---

# Js

## i18n runs through laravel-vue-i18n with lang/de.json
German is the UI language, but every user-facing string goes through translation, never hardcoded German. Backend uses `__()`, Vue uses `$t()` in templates and `trans()`/`wTrans()` in script setup. All keys live in lang/de.json keyed by the English source string.

Traps:
- app.ts reads the `locale` shared prop straight out of the page's JSON script tag at module scope and calls `I18n.getSharedInstance().loadLanguage()` synchronously BEFORE createInertiaApp(). Without that, `trans()` calls evaluated at module load (e.g. `defineOptions({ layout: { breadcrumbs } })`) capture an untranslated, non-reactive snapshot. Don't "simplify" this to a plain `app.use(i18nVue)`.
- Because of that DOM read, vite.config.ts sets `inertia({ ssr: false })`. Turning SSR back on breaks the boot.
- The Vue plugin is registered via Inertia v3's `withApp(app)` callback; there is no `setup()` in this app.
- `SetLocale` middleware (bootstrap/app.php, before HandleInertiaRequests) applies `users.locale`, falling back to config('app.locale'). Selectable languages come from `User::availableLocales()`.
- `resources/js/pages/Welcome.vue` is the only screen still carrying untranslated starter-kit English; translate it if it ever stops being the throwaway landing page.

## The whole app is translated; PHP lang files exist alongside de.json
Every auth page, the settings pages, the user menu and the CRUD screens go through `$t()`/`trans()`. Two things that are easy to miss:

- lang/de.json is no longer the whole story. `lang/de/auth.php`, `lang/de/passwords.php` and `lang/de/validation.php` hold the messages Laravel resolves by dotted key. Without those files it silently falls back to its built-in English lines, so a German login dialog answered a failed login in English. `validation.php` deliberately covers only the rules the translated screens can surface — the per-key fallback keeps everything else English until a screen needs it. Form requests that declare their own `messages()` (user and section CRUD) take precedence over `validation.php` and stay keyed by their English source string in de.json.
- `tests/Unit/TranslationKeyTest.php` scans resources/js for `$t()` / `trans()` / `wTrans()` and fails on any key missing from lang/de.json, so a new user-facing string must be added there in the same change. It is a plain-filesystem test (unit tests get no app boot, so no `base_path()` / `File::`), and its regex uses `(?<![\w$])` rather than `\b` — `$` is not a word character, so `\b` never matches in front of `$t` and the check was vacuous until that was fixed.

## NavItem.external renders an anchor — required for non-Inertia pages
`NavMain.vue` renders every sidebar entry with Inertia's `<Link>`. That only works for routes returning an Inertia response. The log viewer (opcodesio/log-viewer) is a Blade page, so an Inertia visit there gets HTML back without the `X-Inertia` header and the SPA cannot render it.

Set `external: true` on the `NavItem` for any such destination; `NavMain` then emits a plain `<a :href="toUrl(item.href)">` and the browser does a normal full-page navigation. The `Logs` entry in `AppSidebar.vue` is the current example.

The href still comes from Wayfinder (`import { index as logViewer } from '@/routes/log-viewer'`), not a hardcoded '/log-viewer' — Wayfinder generates helpers for vendor package routes too, so the link survives a change to `config('log-viewer.route_path')`. Re-run `php artisan wayfinder:generate --with-form` after installing a package that adds routes, or its helpers will be missing.
