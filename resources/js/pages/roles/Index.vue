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
import { create, edit, index } from '@/routes/roles';
import type { BreadcrumbItem, RoleResource, Paginated } from '@/types';

const props = defineProps<{
    roles: Paginated<RoleResource>;
    filters: { search: string };
    canCreate: boolean;
}>();

// So the edit page's Cancel button can return here instead of resetting to
// the first, unfiltered page.
const editQuery = computed(() => ({
    page: props.roles.meta.current_page,
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
                title: trans('Roles'),
                href: index(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('Roles')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                :title="$t('Roles')"
                :description="$t('Manage the roles of this club')"
            />
            <Button v-if="canCreate" as-child class="hidden md:inline-flex">
                <Link :href="create()">
                    <Plus class="size-4" />
                    {{ $t('New role') }}
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
                :aria-label="$t('Filter roles')"
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
                    <TableRow v-for="role in roles.data" :key="role.id">
                        <TableCell class="font-medium">
                            <span class="flex items-center gap-1.5">
                                {{ role.name }}
                                <Tooltip v-if="role.shared">
                                    <TooltipTrigger as-child>
                                        <Globe
                                            class="size-3.5 shrink-0 text-muted-foreground"
                                            :aria-label="$t('Shared role')"
                                        />
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        {{ $t('Shared role') }}
                                    </TooltipContent>
                                </Tooltip>
                            </span>
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{ role.members_count }}
                        </TableCell>
                        <TableCell>
                            <div class="flex justify-end gap-1">
                                <Tooltip v-if="role.modifiable">
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            as-child
                                        >
                                            <Link
                                                :href="
                                                    edit(role.id, {
                                                        query: editQuery,
                                                    })
                                                "
                                                :aria-label="
                                                    $t('Edit :name', {
                                                        name: role.name,
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
                    <TableEmpty v-if="roles.data.length === 0" :colspan="3">
                        {{
                            search ? $t('No roles found.') : $t('No roles yet.')
                        }}
                    </TableEmpty>
                </TableBody>
            </Table>
        </div>

        <div
            v-if="roles.meta.total > roles.data.length"
            class="flex items-center justify-center md:justify-between"
        >
            <p class="hidden text-sm text-muted-foreground md:block">
                {{
                    $t('Entries :from–:to of :total', {
                        from: String(roles.meta.from ?? 0),
                        to: String(roles.meta.to ?? 0),
                        total: String(roles.meta.total),
                    })
                }}
            </p>
            <PaginationNav :links="roles.meta.links" />
        </div>
    </div>
</template>
