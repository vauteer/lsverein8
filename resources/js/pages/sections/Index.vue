<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Search } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import PaginationNav from '@/components/PaginationNav.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { index as members } from '@/routes/members';
import { create, edit, index } from '@/routes/sections';
import type { BreadcrumbItem, Paginated, SectionResource } from '@/types';

const props = defineProps<{
    sections: Paginated<SectionResource>;
    filters: { search: string };
    canCreate: boolean;
    blsv: boolean;
}>();

// So the edit page's Cancel button can return here instead of resetting to
// the first, unfiltered page.
const editQuery = computed(() => ({
    page: props.sections.meta.current_page,
    search: props.filters.search || undefined,
}));

const columnCount = computed(() => (props.blsv ? 4 : 3));

const search = ref(props.filters.search);

let searchDebounce: ReturnType<typeof setTimeout> | undefined;

watch(search, (value) => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => {
        router.get(
            index.url({ query: { search: value || undefined } }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: trans('Sections'),
                href: index(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('Sections')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                :title="$t('Sections')"
                :description="$t('Manage the sections of this club')"
            />
            <Button variant="outline" v-if="canCreate" as-child>
                <Link :href="create()" :aria-label="$t('New section')">
                    <Plus class="size-4" />
                    <span class="max-md:hidden">{{ $t('New section') }}</span>
                </Link>
            </Button>
        </div>

        <div class="relative w-full max-w-sm">
            <Search
                class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="search"
                type="search"
                :placeholder="$t('Name')"
                :aria-label="$t('Filter sections')"
                class="pl-9"
            />
        </div>

        <div
            class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <Table>
                <TableHeader>
                    <TableRow>
                        <!-- w-full: the name column absorbs what is left, so
                        the other columns shrink to their content and the last
                        one stays on screen on a phone. -->
                        <TableHead class="w-full">{{ $t('Name') }}</TableHead>
                        <TableHead v-if="blsv" class="hidden md:table-cell">
                            {{ $t('BLSV section') }}
                        </TableHead>
                        <TableHead class="text-right">
                            {{ $t('Members') }}
                        </TableHead>
                        <TableHead class="text-right">
                            {{ $t('Actions') }}
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="section in sections.data"
                        :key="section.id"
                    >
                        <!-- whitespace-normal: table cells are nowrap by
                        default, so a long name pushed the table wider than the
                        viewport and the last column off it. Wrapping rather
                        than truncating — these are told apart by their full
                        name. -->
                        <TableCell class="font-medium whitespace-normal">
                            <span class="min-w-0 break-words">
                                {{ section.name }}
                            </span>
                            <div
                                v-if="blsv && section.blsv_label"
                                class="text-xs font-normal text-muted-foreground md:hidden"
                            >
                                {{ section.blsv_label }}
                            </div>
                        </TableCell>
                        <TableCell
                            v-if="blsv"
                            class="hidden text-muted-foreground md:table-cell"
                        >
                            {{ section.blsv_label ?? '—' }}
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            <!-- The number is the same set the selection
                            shows, so it doubles as the way there. Nothing to
                            click when the section is empty. -->
                            <Link
                                v-if="section.members_count > 0"
                                :href="
                                    members({
                                        query: {
                                            filter: `section_${section.id}`,
                                        },
                                    })
                                "
                                class="underline-offset-4 hover:underline"
                                :aria-label="
                                    $t('Show the members of :name', {
                                        name: section.name,
                                    })
                                "
                            >
                                {{ section.members_count }}
                            </Link>
                            <span v-else class="text-muted-foreground">
                                {{ section.members_count }}
                            </span>
                        </TableCell>
                        <TableCell>
                            <div class="flex justify-end gap-1">
                                <Tooltip v-if="section.modifiable">
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            as-child
                                        >
                                            <Link
                                                :href="
                                                    edit(section.id, {
                                                        query: editQuery,
                                                    })
                                                "
                                                :aria-label="
                                                    $t('Edit :name', {
                                                        name: section.name,
                                                    })
                                                "
                                            >
                                                <Pencil class="size-4" />
                                            </Link>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        {{ $t('Edit') }}
                                    </TooltipContent>
                                </Tooltip>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableEmpty
                        v-if="sections.data.length === 0"
                        :colspan="columnCount"
                    >
                        {{
                            search
                                ? $t('No sections found.')
                                : $t('No sections yet.')
                        }}
                    </TableEmpty>
                </TableBody>
            </Table>
        </div>

        <div
            v-if="sections.meta.total > sections.data.length"
            class="flex items-center justify-center md:justify-between"
        >
            <p class="hidden text-sm text-muted-foreground md:block">
                {{
                    $t('Entries :from–:to of :total', {
                        from: String(sections.meta.from ?? 0),
                        to: String(sections.meta.to ?? 0),
                        total: String(sections.meta.total),
                    })
                }}
            </p>
            <PaginationNav :links="sections.meta.links" />
        </div>
    </div>
</template>
