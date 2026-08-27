<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChartColumn, Download } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import MemberExportController from '@/actions/App/Http/Controllers/MemberExportController';
import Heading from '@/components/Heading.vue';
import { Button, buttonVariants } from '@/components/ui/button';
import { blsv } from '@/routes';
import { blsvStatistic } from '@/routes/clubs';
import type { BreadcrumbItem, MemberExportFormat } from '@/types';

const props = defineProps<{
    clubId: number;
    clubName: string;
    statisticKeyDate: string;
    statisticYear: number;
    reportKeyDate: string;
    reportFormats: MemberExportFormat[];
}>();

const statisticHref = blsvStatistic(props.clubId);

// No filter in the query: the Nachmeldung is locked to the Mitglieder
// selection anyway (MemberExport::isAvailableFor), which is what the member
// list defaults to, so it reads the same here as it does from there.
const reportHref = (format: string) => MemberExportController.url(format);

defineOptions({
    layout: {
        breadcrumbs: [
            // Static, like the club form's: defineOptions() is hoisted out of
            // setup and cannot see props.
            { title: trans('BLSV'), href: blsv() } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('BLSV')" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4">
        <Heading :title="$t('BLSV')" :description="clubName" />

        <section
            class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <div>
                <h2 class="text-sm font-medium">{{ $t('Yearly report') }}</h2>
                <p class="text-sm text-muted-foreground">
                    {{
                        $t('As of 1 January :year', {
                            year: String(statisticYear),
                        })
                    }}
                </p>
            </div>
            <p class="text-sm text-muted-foreground">
                {{
                    $t(
                        'The age statistic as a PDF, the whole membership as Excel and CSV, and one file per BLSV section. Leavers at 31 December and joiners at 1 January are already counted in.',
                    )
                }}
            </p>
            <div>
                <!-- An Inertia link, not a download: this opens a page that
                builds the files and then lists them. It is behind a button
                because opening it rebuilds every one of them. -->
                <Button variant="outline" as-child>
                    <Link :href="statisticHref" data-test="blsv-statistic-link">
                        <ChartColumn class="size-4" />
                        {{ $t('Build yearly report') }}
                    </Link>
                </Button>
            </div>
        </section>

        <section
            class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <div>
                <h2 class="text-sm font-medium">
                    {{ $t('Supplementary report') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ $t('As of :date', { date: reportKeyDate }) }}
                </p>
            </div>
            <p class="text-sm text-muted-foreground">
                {{
                    $t(
                        'For reporting members during the year. It is not a difference: it carries every current member, the same as the yearly report does.',
                    )
                }}
            </p>
            <div class="flex flex-wrap gap-2">
                <!-- Plain anchors, not Inertia links: these are downloads, so
                a visit would leave the SPA looking for a component. Same
                reason as the member exports they come from. -->
                <a
                    v-for="format in reportFormats"
                    :key="format.id"
                    :href="reportHref(format.id)"
                    download
                    :class="buttonVariants({ variant: 'outline' })"
                    data-test="blsv-report-link"
                >
                    <Download class="size-4" />
                    {{ format.name }}
                </a>
            </div>
        </section>
    </div>
</template>
