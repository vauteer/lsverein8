<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Download, TriangleAlert } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index } from '@/routes/subscriptions';
import type {
    BreadcrumbItem,
    GeneratedDownload,
    OutstandingPayment,
} from '@/types';

const props = defineProps<{
    downloads: GeneratedDownload[];
    outStandings: OutstandingPayment[];
    executionDate: string;
    backPage: number | null;
    backSearch: string | null;
}>();

const backHref = index({
    query: {
        page: props.backPage ?? undefined,
        search: props.backSearch ?? undefined,
    },
});

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: trans('Subscriptions'),
                href: index(),
            } satisfies BreadcrumbItem,
            {
                title: trans('Collect fees'),
                href: index(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('Collect fees')" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4">
        <Heading
            :title="$t('Collect fees')"
            :description="
                $t('Due on :date', {
                    date: executionDate,
                })
            "
        />

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
                        'The file is rebuilt on every collection and is not kept, so download it now.',
                    )
                }}
            </p>
        </div>

        <div v-if="outStandings.length > 0" class="flex flex-col gap-2">
            <h2 class="flex items-center gap-2 text-sm font-medium">
                <TriangleAlert class="size-4 shrink-0 text-muted-foreground" />
                {{ $t('Outstanding payments') }}
            </h2>
            <p class="text-sm text-muted-foreground">
                {{
                    $t(
                        'These members hold one of the collected subscriptions but do not pay by direct debit, so they have to be billed by hand.',
                    )
                }}
            </p>
            <div
                class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{{ $t('Name') }}</TableHead>
                            <TableHead>{{ $t('Subscription') }}</TableHead>
                            <TableHead>{{ $t('Payment method') }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="(outStanding, position) in outStandings"
                            :key="`${outStanding.name}-${position}`"
                        >
                            <TableCell class="font-medium">
                                {{ outStanding.name }}
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ outStanding.subscription }}
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ outStanding.paymentMethod }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>

        <div>
            <Button variant="ghost" as-child>
                <Link :href="backHref">{{ $t('Back') }}</Link>
            </Button>
        </div>
    </div>
</template>
