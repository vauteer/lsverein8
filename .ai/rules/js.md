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
- Starter-kit auth/settings pages are still untranslated English; translate them as they get touched.
