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
import { create, edit, index } from '@/routes/items';
import type { BreadcrumbItem, ItemResource, Paginated } from '@/types';

const props = defineProps<{
    items: Paginated<ItemResource>;
    filters: { search: string };
    canCreate: boolean;
}>();

// So the edit page's Cancel button can return here instead of resetting to
// the first, unfiltered page.
const editQuery = computed(() => ({
    page: props.items.meta.current_page,
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
                title: trans('Inventory'),
                href: index(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('Inventory')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                :title="$t('Inventory')"
                :description="
                    $t('Equipment and gear that is issued to a member')
                "
            />
            <Button
                variant="outline"
                v-if="canCreate"
                as-child
                class="hidden md:inline-flex"
            >
                <Link :href="create()">
                    <Plus class="size-4" />
                    {{ $t('New item') }}
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
                :aria-label="$t('Filter items')"
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
                    <TableRow v-for="item in items.data" :key="item.id">
                        <TableCell class="font-medium">
                            {{ item.name }}
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{ item.members_count }}
                        </TableCell>
                        <TableCell>
                            <div class="flex justify-end gap-1">
                                <Tooltip v-if="item.modifiable">
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            as-child
                                        >
                                            <Link
                                                :href="
                                                    edit(item.id, {
                                                        query: editQuery,
                                                    })
                                                "
                                                :aria-label="
                                                    $t('Edit :name', {
                                                        name: item.name,
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
                    <TableEmpty v-if="items.data.length === 0" :colspan="3">
                        {{
                            search ? $t('No items found.') : $t('No items yet.')
                        }}
                    </TableEmpty>
                </TableBody>
            </Table>
        </div>

        <div
            v-if="items.meta.total > items.data.length"
            class="flex items-center justify-center md:justify-between"
        >
            <p class="hidden text-sm text-muted-foreground md:block">
                {{
                    $t('Entries :from–:to of :total', {
                        from: String(items.meta.from ?? 0),
                        to: String(items.meta.to ?? 0),
                        total: String(items.meta.total),
                    })
                }}
            </p>
            <PaginationNav :links="items.meta.links" />
        </div>
    </div>
</template>
