<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Download, TriangleAlert } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { edit, index } from '@/routes/clubs';
import type { BreadcrumbItem, GeneratedDownload } from '@/types';

const props = defineProps<{
    clubId: number;
    clubName: string;
    keyDate: string;
    downloads: GeneratedDownload[];
}>();

const backHref = edit(props.clubId);

defineOptions({
    layout: {
        breadcrumbs: [
            // Static, like the club form's: defineOptions() is hoisted out
            // of setup and cannot see props, so the trail cannot name the
            // club it came from.
            { title: trans('Clubs'), href: index() } satisfies BreadcrumbItem,
            {
                title: trans('BLSV statistic'),
                href: index(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('BLSV statistic')" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4">
        <Heading
            :title="$t('BLSV statistic')"
            :description="
                $t(':name, as of :date', { name: clubName, date: keyDate })
            "
        />

        <div class="flex flex-col gap-2">
            <!-- Plain anchors, not Inertia links: these are file downloads,
            not pages, so a visit would leave the SPA looking for a component.
            Same reason as the SEPA files and the member exports. -->
            <a
                v-for="download in downloads"
                :key="download.href"
                :href="download.href"
                download
                class="flex items-center gap-3 rounded-xl border border-sidebar-border/70 p-4 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
                data-test="blsv-download-link"
            >
                <Download class="size-4 shrink-0 text-muted-foreground" />
                {{ download.name }}
            </a>
            <p class="text-sm text-muted-foreground">
                {{
                    $t(
                        'The files are rebuilt every time this page is opened and are not kept, so download them now.',
                    )
                }}
            </p>
        </div>

        <p
            class="flex items-start gap-2 text-sm text-muted-foreground"
            data-test="blsv-section-hint"
        >
            <TriangleAlert class="mt-0.5 size-4 shrink-0" />
            <span>
                {{
                    $t(
                        'Only members of a section that carries a BLSV section number are counted per section. The age statistic counts every current member.',
                    )
                }}
            </span>
        </p>

        <div>
            <Button variant="ghost" as-child>
                <Link :href="backHref">{{ $t('Back') }}</Link>
            </Button>
        </div>
    </div>
</template>
