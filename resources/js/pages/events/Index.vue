<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Globe, Pencil, Plus, Search } from '@lucide/vue';
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
import { create, edit, index } from '@/routes/events';
import type { BreadcrumbItem, EventResource, Paginated } from '@/types';

const props = defineProps<{
    events: Paginated<EventResource>;
    filters: { search: string };
    canCreate: boolean;
}>();

// So the edit page's Cancel button can return here instead of resetting to
// the first, unfiltered page.
const editQuery = computed(() => ({
    page: props.events.meta.current_page,
    search: props.filters.search || undefined,
}));

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
                title: trans('Events'),
                href: index(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('Events')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                :title="$t('Events')"
                :description="
                    $t('Honours and awards a member receives or earns')
                "
            />
            <Button v-if="canCreate" as-child class="hidden md:inline-flex">
                <Link :href="create()">
                    <Plus class="size-4" />
                    {{ $t('New event') }}
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
                :aria-label="$t('Filter events')"
                class="pl-9"
            />
        </div>

        <div
            class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{{ $t('Name') }}</TableHead>
                        <TableHead class="text-right">
                            {{ $t('Members') }}
                        </TableHead>
                        <TableHead class="text-right">
                            {{ $t('Actions') }}
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="event in events.data" :key="event.id">
                        <TableCell class="font-medium">
                            <span class="flex items-center gap-1.5">
                                {{ event.name }}
                                <Tooltip v-if="event.shared">
                                    <TooltipTrigger as-child>
                                        <Globe
                                            class="size-3.5 shrink-0 text-muted-foreground"
                                            :aria-label="$t('Shared event')"
                                        />
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        {{ $t('Shared event') }}
                                    </TooltipContent>
                                </Tooltip>
                            </span>
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{ event.members_count }}
                        </TableCell>
                        <TableCell>
                            <div class="flex justify-end gap-1">
                                <Tooltip v-if="event.modifiable">
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            as-child
                                        >
                                            <Link
                                                :href="
                                                    edit(event.id, {
                                                        query: editQuery,
                                                    })
                                                "
                                                :aria-label="
                                                    $t('Edit :name', {
                                                        name: event.name,
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
                    <TableEmpty v-if="events.data.length === 0" :colspan="3">
                        {{
                            search
                                ? $t('No events found.')
                                : $t('No events yet.')
                        }}
                    </TableEmpty>
                </TableBody>
            </Table>
        </div>

        <div
            v-if="events.meta.total > events.data.length"
            class="flex items-center justify-center md:justify-between"
        >
            <p class="hidden text-sm text-muted-foreground md:block">
                {{
                    $t('Entries :from–:to of :total', {
                        from: String(events.meta.from ?? 0),
                        to: String(events.meta.to ?? 0),
                        total: String(events.meta.total),
                    })
                }}
            </p>
            <PaginationNav :links="events.meta.links" />
        </div>
    </div>
</template>
