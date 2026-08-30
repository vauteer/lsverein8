<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronDown,
    Download,
    IdCard,
    Pencil,
    Plus,
    Search,
} from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import MemberExportController from '@/actions/App/Http/Controllers/MemberExportController';
import Heading from '@/components/Heading.vue';
import PaginationNav from '@/components/PaginationNav.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import { create, edit, index, show } from '@/routes/members';
import type {
    BreadcrumbItem,
    MemberExportFormat,
    MemberListFilters,
    MemberResource,
    Paginated,
    SelectOption,
} from '@/types';

const props = defineProps<{
    members: Paginated<MemberResource>;
    filters: MemberListFilters;
    filterOptions: SelectOption[];
    sortOptions: SelectOption[];
    yearOptions: SelectOption[];
    /** False for a subscription selection: a subscription has no date range. */
    yearApplies: boolean;
    canCreate: boolean;
    exportFormats: MemberExportFormat[];
}>();

const search = ref(props.filters.search);
const filter = ref(props.filters.filter);
const sort = ref(props.filters.sort);
const year = ref(String(props.filters.year));

// The whole list state travels in the URL, so a bookmark or the back button
// restores the same selection, order and key date.
const listQuery = computed(() => ({
    search: search.value || undefined,
    filter: filter.value || undefined,
    sort: sort.value || undefined,
    year: Number(year.value),
}));

const reload = (resetPage = true) => {
    router.get(
        index.url({
            query: {
                ...listQuery.value,
                page: resetPage ? undefined : props.members.meta.current_page,
            },
        }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

let searchDebounce: ReturnType<typeof setTimeout> | undefined;

watch(search, () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => reload(), 300);
});

// A changed selection, order or key date takes effect at once — no debounce,
// they come from a picker rather than from typing.
watch([filter, sort, year], () => reload());

// The download carries the list state, so the file matches the screen.
const exportHref = (format: string) =>
    MemberExportController.url(format, { query: listQuery.value });

// So the edit and detail pages can come back to exactly this list.
const rowQuery = computed(() => ({
    ...listQuery.value,
    page: props.members.meta.current_page,
}));

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: trans('Members'),
                href: index(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('Members')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                :title="$t('Members')"
                :description="
                    $t(':total people in the current selection', {
                        total: String(members.meta.total),
                    })
                "
            />
            <div class="flex shrink-0 items-center gap-2">
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            :disabled="members.meta.total === 0"
                            data-test="open-export-menu-button"
                            :aria-label="$t('Export')"
                        >
                            <Download class="size-4" />
                            <span class="max-md:hidden">{{
                                $t('Export')
                            }}</span>
                            <ChevronDown class="size-4 max-md:hidden" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-64">
                        <!-- Plain anchors, not Inertia links: these are file
                        downloads, so a visit would leave the SPA looking for a
                        component. -->
                        <DropdownMenuItem
                            v-for="format in exportFormats"
                            :key="format.id"
                            as-child
                        >
                            <a :href="exportHref(format.id)" download>
                                <span class="flex flex-col gap-0.5">
                                    <span>{{ format.name }}</span>
                                    <span class="text-xs text-muted-foreground">
                                        {{ format.description }}
                                    </span>
                                </span>
                            </a>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
                <Button variant="outline" v-if="canCreate" as-child>
                    <Link
                        :href="create({ query: rowQuery })"
                        :aria-label="$t('New member')"
                    >
                        <Plus class="size-4" />
                        <span class="max-md:hidden">{{
                            $t('New member')
                        }}</span>
                    </Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="grid gap-2">
                <Label for="filter">{{ $t('Selection') }}</Label>
                <Select v-model="filter">
                    <SelectTrigger id="filter" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in filterOptions"
                            :key="option.id"
                            :value="String(option.id)"
                        >
                            {{ option.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="grid gap-2">
                <Label for="search">{{ $t('Search') }}</Label>
                <div class="relative">
                    <Search
                        class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        id="search"
                        v-model="search"
                        type="search"
                        :placeholder="$t('Name, address or memo')"
                        class="pl-9"
                    />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="sort">{{ $t('Order') }}</Label>
                <Select v-model="sort">
                    <SelectTrigger id="sort" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in sortOptions"
                            :key="option.id"
                            :value="String(option.id)"
                        >
                            {{ option.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="grid gap-2">
                <Label for="year">{{ $t('As of year') }}</Label>
                <Select v-model="year" :disabled="!yearApplies">
                    <SelectTrigger id="year" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in yearOptions"
                            :key="option.id"
                            :value="String(option.id)"
                        >
                            {{ option.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p v-if="!yearApplies" class="text-xs text-muted-foreground">
                    {{
                        $t(
                            'A subscription carries no dates, so this selection is always read as of today.',
                        )
                    }}
                </p>
            </div>
        </div>

        <div
            class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-16 text-right">
                            {{ $t('No.') }}
                        </TableHead>
                        <TableHead>{{ $t('Name') }}</TableHead>
                        <TableHead class="hidden lg:table-cell">
                            {{ $t('Address') }}
                        </TableHead>
                        <TableHead class="hidden md:table-cell">
                            {{ $t('Sections and roles') }}
                        </TableHead>
                        <TableHead class="text-right">
                            {{ $t('Actions') }}
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="member in members.data" :key="member.id">
                        <TableCell
                            class="text-right text-muted-foreground tabular-nums"
                        >
                            {{ member.member_id }}
                        </TableCell>
                        <TableCell>
                            <div
                                class="font-medium"
                                :class="
                                    member.is_member
                                        ? ''
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ member.surname }} {{ member.first_name }}
                                <span
                                    v-if="member.gone"
                                    :title="$t('Deceased')"
                                >
                                    †
                                </span>
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{
                                    $t(
                                        ':birthday · :age years · :years in the club',
                                        {
                                            birthday: member.birthday,
                                            age: String(member.age),
                                            years: String(
                                                member.membership_years,
                                            ),
                                        },
                                    )
                                }}
                            </div>
                        </TableCell>
                        <TableCell
                            class="hidden text-muted-foreground lg:table-cell"
                        >
                            {{ member.address }}
                        </TableCell>
                        <TableCell class="hidden md:table-cell">
                            <div class="max-w-56 truncate">
                                {{ member.sections || '—' }}
                            </div>
                            <div
                                class="max-w-56 truncate text-xs text-muted-foreground"
                            >
                                {{ member.roles }}
                            </div>
                            <div
                                v-if="member.subscriptions !== null"
                                class="max-w-56 truncate text-xs text-muted-foreground"
                            >
                                {{ member.subscriptions }}
                                <template v-if="member.latest_event">
                                    · {{ member.latest_event }}
                                </template>
                            </div>
                        </TableCell>
                        <TableCell>
                            <div class="flex justify-end gap-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            as-child
                                        >
                                            <Link
                                                :href="
                                                    show(member.id, {
                                                        query: rowQuery,
                                                    })
                                                "
                                                :aria-label="
                                                    $t('Show :name', {
                                                        name: member.full_name,
                                                    })
                                                "
                                            >
                                                <IdCard class="size-4" />
                                            </Link>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        {{ $t('Details') }}
                                    </TooltipContent>
                                </Tooltip>
                                <Tooltip v-if="member.modifiable">
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            as-child
                                        >
                                            <Link
                                                :href="
                                                    edit(member.id, {
                                                        query: rowQuery,
                                                    })
                                                "
                                                :aria-label="
                                                    $t('Edit :name', {
                                                        name: member.full_name,
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
                    <TableEmpty v-if="members.data.length === 0" :colspan="5">
                        {{ $t('No member matches this selection.') }}
                    </TableEmpty>
                </TableBody>
            </Table>
        </div>

        <div
            v-if="members.meta.total > members.data.length"
            class="flex items-center justify-center md:justify-between"
        >
            <p class="hidden text-sm text-muted-foreground md:block">
                {{
                    $t('Entries :from–:to of :total', {
                        from: String(members.meta.from ?? 0),
                        to: String(members.meta.to ?? 0),
                        total: String(members.meta.total),
                    })
                }}
            </p>
            <PaginationNav :links="members.meta.links" />
        </div>
    </div>
</template>
