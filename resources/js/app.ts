import { createInertiaApp, router } from '@inertiajs/vue3';
import {
    currentLocale,
    I18n,
    i18nVue,
    loadLanguageAsync,
} from 'laravel-vue-i18n';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import deTranslations from '../../lang/de.json';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const resolveTranslations = (lang: string) =>
    lang === 'de' ? deTranslations : {};

// The `locale` shared Inertia prop is already embedded in the page's initial
// JSON payload. Reading it directly from the DOM - the same script tag
// Inertia itself parses on boot - lets us know the correct language before
// createInertiaApp() (and therefore before any page module) ever runs,
// instead of defaulting to one language and correcting later.
function resolveInitialLocale(): string {
    const pageScript = document.querySelector(
        'script[data-page="app"][type="application/json"]',
    );

    try {
        return (
            JSON.parse(pageScript?.textContent ?? '{}').props?.locale ?? 'de'
        );
    } catch {
        return 'de';
    }
}

const initialLocale = resolveInitialLocale();

// laravel-vue-i18n always loads languages asynchronously in the browser (at
// least one microtask tick), even when resolve() returns data that's already
// in memory. That's normally fine, but trans() calls made at module-evaluation
// time - e.g. defineOptions({ layout: { breadcrumbs } }) on a page component -
// capture a plain string snapshot with no reactivity, so if the page module
// happens to evaluate before that microtask resolves, the breadcrumb is
// permanently stuck untranslated. Populating the shared instance's messages
// synchronously here, before createInertiaApp() even runs, closes that race.
//
// I18n.loadLanguage() (unlike loadLanguageAsync()) resolves synchronously when
// given a synchronous resolve(). Its resolve() option is only typed for the
// dynamic-import-flavoured async path (Promise<{ default: ... }>), so the cast
// below asserts something the type declaration doesn't account for, rather
// than silencing a real runtime mismatch.
type I18nResolve = NonNullable<
    NonNullable<Parameters<typeof I18n.getSharedInstance>[0]>['resolve']
>;
const syncResolve = resolveTranslations as unknown as I18nResolve;
I18n.getSharedInstance({
    lang: initialLocale,
    resolve: syncResolve,
}).loadLanguage(initialLocale);

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
    withApp(app) {
        app.use(i18nVue, { lang: initialLocale, resolve: resolveTranslations });
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();

// The active locale can change mid-session (e.g. an admin switching their own
// language). Keep the reactive $t()/wTrans() UI in sync on every subsequent
// navigation. Already-evaluated page modules' non-reactive defineOptions()
// breadcrumbs (see the comment above) won't retroactively update - only a hard
// refresh re-runs those - but that's an acceptable, narrow edge case.
router.on('navigate', (event) => {
    const locale = (event as CustomEvent).detail?.page?.props?.locale as
        string | undefined;

    if (locale && locale !== currentLocale.value) {
        loadLanguageAsync(locale);
    }
});
