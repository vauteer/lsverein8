<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Bell,
    Blocks,
    Bug,
    CloudUpload,
    ExternalLink,
    FlaskConical,
    Languages,
    Mail,
    Printer,
    Radar,
    Rocket,
    ScrollText,
    ShieldCheck,
    Signpost,
    Sparkles,
    Telescope,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import {
    siClaude,
    siInertia,
    siLaravel,
    siLucide,
    siPhp,
    siTailwindcss,
    siVite,
    siVuedotjs,
    siVueuse,
} from 'simple-icons';
import type { SimpleIcon } from 'simple-icons';
import CreditIcon from '@/components/CreditIcon.vue';
import Heading from '@/components/Heading.vue';
import { about } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type Credit = {
    name: string;
    description: string;
    href: string;
    icon: LucideIcon | SimpleIcon;
};

type CreditGroup = {
    title: string;
    items: Credit[];
};

const props = defineProps<{
    appName: string;
    laravelVersion: string;
    phpVersion: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: trans('About'),
                href: about(),
            } satisfies BreadcrumbItem,
        ],
    },
});

const author = {
    name: 'Gerald Lindner',
    email: 'gerald@modehaus-lindner.de',
};

const creditGroups: CreditGroup[] = [
    {
        title: trans('Backend'),
        items: [
            {
                name: `Laravel ${props.laravelVersion}`,
                description: trans(
                    'The PHP framework this application is built on',
                ),
                href: 'https://laravel.com',
                icon: siLaravel,
            },
            {
                name: `PHP ${props.phpVersion}`,
                description: trans('The language the server side runs on'),
                href: 'https://www.php.net',
                icon: siPhp,
            },
            {
                name: 'Inertia.js',
                description: trans(
                    'Connects the Laravel backend to the Vue frontend',
                ),
                href: 'https://inertiajs.com',
                icon: siInertia,
            },
            {
                name: 'Laravel Fortify',
                description: trans(
                    'Login, password reset and password confirmation',
                ),
                href: 'https://laravel.com/docs/fortify',
                icon: ShieldCheck,
            },
            {
                name: 'Laravel Wayfinder',
                description: trans('Type-safe route helpers for the frontend'),
                href: 'https://github.com/laravel/wayfinder',
                icon: Signpost,
            },
            {
                name: 'FPDF',
                description: trans(
                    'Generates the member lists, the SEPA mandates and the BLSV report',
                ),
                href: 'https://www.fpdf.org',
                icon: Printer,
            },
            {
                name: 'Flysystem',
                description: trans('Stores the database backups off-site'),
                href: 'https://flysystem.thephpleague.com',
                icon: CloudUpload,
            },
        ],
    },
    {
        title: trans('Frontend'),
        items: [
            {
                name: 'Vue',
                description: trans(
                    'The component framework behind the user interface',
                ),
                href: 'https://vuejs.org',
                icon: siVuedotjs,
            },
            {
                name: 'Tailwind CSS',
                description: trans('The styling of every page'),
                href: 'https://tailwindcss.com',
                icon: siTailwindcss,
            },
            {
                name: 'Reka UI',
                description: trans(
                    'Accessible building blocks for menus, dialogs and more',
                ),
                href: 'https://reka-ui.com',
                icon: Blocks,
            },
            {
                name: 'Lucide',
                description: trans('The icon set'),
                href: 'https://lucide.dev',
                icon: siLucide,
            },
            {
                name: 'Vue Sonner',
                description: trans('The toast notifications'),
                href: 'https://vue-sonner.vercel.app',
                icon: Bell,
            },
            {
                name: 'laravel-vue-i18n',
                description: trans(
                    'Brings the German translations to the frontend',
                ),
                href: 'https://github.com/xiCO2k/laravel-vue-i18n',
                icon: Languages,
            },
            {
                name: 'VueUse',
                description: trans('Utilities for Vue components'),
                href: 'https://vueuse.org',
                icon: siVueuse,
            },
            {
                name: 'Vite',
                description: trans('Bundles the frontend assets'),
                href: 'https://vite.dev',
                icon: siVite,
            },
        ],
    },
    {
        title: trans('Development'),
        items: [
            {
                name: 'Laravel Vue Starter Kit',
                description: trans(
                    'The starting point this application grew from',
                ),
                href: 'https://github.com/laravel/vue-starter-kit',
                icon: Rocket,
            },
            {
                name: 'Pest',
                description: trans('Runs the automated tests'),
                href: 'https://pestphp.com',
                icon: FlaskConical,
            },
            {
                name: 'Laravel Pint, ESLint & Prettier',
                description: trans('Keep the code formatted consistently'),
                href: 'https://laravel.com/docs/pint',
                icon: Sparkles,
            },
            {
                name: 'Larastan',
                description: trans('Checks the PHP code without running it'),
                href: 'https://github.com/larastan/larastan',
                icon: Radar,
            },
            {
                name: 'Laravel Telescope',
                description: trans('Insight into requests, queries and jobs'),
                href: 'https://laravel.com/docs/telescope',
                icon: Telescope,
            },
            {
                name: 'Laravel Debugbar',
                description: trans('Shows what a page did while it was built'),
                href: 'https://github.com/barryvdh/laravel-debugbar',
                icon: Bug,
            },
            {
                name: 'Log Viewer',
                description: trans('Reads the application log in the browser'),
                href: 'https://log-viewer.opcodes.io',
                icon: ScrollText,
            },
            {
                name: 'Claude Code',
                description: trans('Wrote a good part of this code'),
                href: 'https://claude.com/claude-code',
                icon: siClaude,
            },
        ],
    },
];
</script>

<template>
    <Head :title="$t('About')" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            :title="$t('About')"
            :description="
                $t('Who made :name, and what it is built with', {
                    name: appName,
                })
            "
        />

        <div class="grid gap-4 md:grid-cols-2">
            <div
                class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <Heading :title="$t('The application')" variant="small" />

                <p class="text-sm text-muted-foreground">
                    {{
                        $t(
                            'Club administration: keep the members with their sections, roles and honours, collect the subscriptions by direct debit, and file the yearly BLSV report.',
                        )
                    }}
                </p>
            </div>

            <div
                class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <Heading :title="$t('Contact')" variant="small" />

                <p class="font-medium">{{ author.name }}</p>

                <a
                    class="flex w-fit items-center gap-2 text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                    :href="`mailto:${author.email}`"
                >
                    <Mail class="size-4 shrink-0" />
                    {{ author.email }}
                </a>
            </div>
        </div>

        <section
            v-for="group in creditGroups"
            :key="group.title"
            class="flex flex-col gap-3"
        >
            <Heading :title="group.title" variant="small" />

            <ul class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                <li
                    v-for="credit in group.items"
                    :key="credit.name"
                    class="flex items-start gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <CreditIcon
                        :icon="credit.icon"
                        class="mt-0.5 size-5 shrink-0"
                    />

                    <div>
                        <a
                            class="flex w-fit items-center gap-1.5 font-medium underline-offset-4 hover:underline"
                            :href="credit.href"
                            target="_blank"
                            rel="noopener"
                        >
                            {{ credit.name }}
                            <ExternalLink class="size-3.5 shrink-0" />
                        </a>

                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ credit.description }}
                        </p>
                    </div>
                </li>
            </ul>
        </section>
    </div>
</template>
