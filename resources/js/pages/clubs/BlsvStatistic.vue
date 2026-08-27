<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Download, TriangleAlert } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { blsv } from '@/routes';
import type { BreadcrumbItem, GeneratedDownload } from '@/types';

defineProps<{
    clubName: string;
    keyDate: string;
    downloads: GeneratedDownload[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: trans('BLSV'), href: blsv() } satisfies BreadcrumbItem,
            {
                title: trans('Yearly report'),
                href: blsv(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('Yearly report')" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4">
        <Heading
            :title="$t('Yearly report')"
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
                class="flex items-start gap-3 rounded-xl border border-sidebar-border/70 p-4 hover:bg-muted dark:border-sidebar-border"
                data-test="blsv-download-link"
            >
                <Download
                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                />
                <span class="flex flex-col gap-0.5">
                    <span class="text-sm font-medium">{{ download.name }}</span>
                    <span class="text-xs text-muted-foreground">
                        {{ download.description }}
                    </span>
                </span>
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
                <Link :href="blsv()">{{ $t('Back') }}</Link>
            </Button>
        </div>
    </div>
</template>
