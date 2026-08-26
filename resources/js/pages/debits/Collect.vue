<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/debits';
import type { BreadcrumbItem, GeneratedDownload } from '@/types';

defineProps<{
    downloads: GeneratedDownload[];
    collected: number;
    executionDate: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: trans('Debits'),
                href: index(),
            } satisfies BreadcrumbItem,
            {
                title: trans('Collect debits'),
                href: index(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('Collect debits')" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4">
        <Heading
            :title="$t('Collect debits')"
            :description="
                $t('Due on :date', {
                    date: executionDate,
                })
            "
        />

        <!-- Phrased so it does not inflect: $t() does not pluralise, and
        TranslationKeyTest only scans $t/trans/wTrans, so a trans_choice key
        could go missing from de.json unnoticed. -->
        <p class="text-sm text-muted-foreground">
            {{
                $t('Collected and removed from the list: :count', {
                    count: String(collected),
                })
            }}
        </p>

        <div class="flex flex-col gap-2">
            <h2 class="text-sm font-medium">
                {{ $t('Files for the direct debit') }}
            </h2>
            <!-- Plain anchors, not Inertia links: these are file downloads,
            not pages, so a visit would leave the SPA looking for a component. -->
            <a
                v-for="download in downloads"
                :key="download.href"
                :href="download.href"
                download
                class="flex items-center gap-3 rounded-xl border border-sidebar-border/70 p-4 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
            >
                <Download class="size-4 shrink-0 text-muted-foreground" />
                {{ download.name }}
            </a>
            <p class="text-sm text-muted-foreground">
                {{
                    $t(
                        'The debits are already gone, and the file is not kept — download it now.',
                    )
                }}
            </p>
        </div>

        <div>
            <Button variant="ghost" as-child>
                <Link :href="index()">{{ $t('Back') }}</Link>
            </Button>
        </div>
    </div>
</template>
