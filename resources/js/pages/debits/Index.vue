<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Banknote, Pencil, Plus, Search } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import DebitCollectDialog from '@/components/DebitCollectDialog.vue';
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
import { create, edit, index } from '@/routes/debits';
import type { BreadcrumbItem, DebitResource, Paginated } from '@/types';

const props = defineProps<{
    debits: Paginated<DebitResource>;
    filters: { search: string };
    canCreate: boolean;
    canCollect: boolean;
    /** Whether the club has any debit at all, search notwithstanding. */
    hasDebits: boolean;
    sepaDate: string | null;
}>();

// So the edit page's Cancel button can return here instead of resetting to
// the first, unfiltered page.
const editQuery = computed(() => ({
    page: props.debits.meta.current_page,
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

const collectionOpen = ref(false);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: trans('Debits'),
                href: index(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('Debits')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                :title="$t('Debits')"
                :description="
                    $t('One-off amounts waiting to be collected from a member')
                "
            />
            <div class="hidden items-center gap-2 md:flex">
                <Button
                    v-if="canCollect && hasDebits"
                    variant="outline"
                    data-test="open-collect-debits-button"
                    @click="collectionOpen = true"
                >
                    <Banknote class="size-4" />
                    {{ $t('Collect debits') }}
                </Button>
                <Button variant="outline" v-if="canCreate" as-child>
                    <Link :href="create()">
                        <Plus class="size-4" />
                        {{ $t('New debit') }}
                    </Link>
                </Button>
            </div>
        </div>

        <div class="relative w-full max-w-sm">
            <Search
                class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="search"
                type="search"
                :placeholder="$t('Name or transfer text')"
                :aria-label="$t('Filter debits')"
                class="pl-9"
            />
        </div>

        <div
            class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{{ $t('Member') }}</TableHead>
                        <TableHead class="hidden md:table-cell">
                            {{ $t('Transfer text') }}
                        </TableHead>
                        <TableHead class="text-right">
                            {{ $t('Amount') }}
                        </TableHead>
                        <TableHead>{{ $t('Due on') }}</TableHead>
                        <TableHead class="text-right">
                            {{ $t('Actions') }}
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="debit in debits.data" :key="debit.id">
                        <TableCell class="font-medium">
                            {{ debit.member_name }}
                            <div
                                class="text-xs font-normal text-muted-foreground md:hidden"
                            >
                                {{ debit.transfer_text }}
                            </div>
                        </TableCell>
                        <TableCell
                            class="hidden text-muted-foreground md:table-cell"
                        >
                            {{ debit.transfer_text }}
                        </TableCell>
                        <TableCell
                            class="text-right whitespace-nowrap tabular-nums"
                        >
                            {{ debit.amount_label }}
                        </TableCell>
                        <TableCell class="whitespace-nowrap tabular-nums">
                            {{ debit.due_at_label }}
                            <div
                                v-if="!debit.due"
                                class="text-xs text-muted-foreground"
                            >
                                {{ $t('Not due yet') }}
                            </div>
                        </TableCell>
                        <TableCell>
                            <div class="flex justify-end gap-1">
                                <Tooltip v-if="debit.modifiable">
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            as-child
                                        >
                                            <Link
                                                :href="
                                                    edit(debit.id, {
                                                        query: editQuery,
                                                    })
                                                "
                                                :aria-label="
                                                    $t('Edit :name', {
                                                        name: debit.member_name,
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
                    <TableEmpty v-if="debits.data.length === 0" :colspan="5">
                        {{
                            search
                                ? $t('No debits found.')
                                : $t('No debits yet.')
                        }}
                    </TableEmpty>
                </TableBody>
            </Table>
        </div>

        <div
            v-if="debits.meta.total > debits.data.length"
            class="flex items-center justify-center md:justify-between"
        >
            <p class="hidden text-sm text-muted-foreground md:block">
                {{
                    $t('Entries :from–:to of :total', {
                        from: String(debits.meta.from ?? 0),
                        to: String(debits.meta.to ?? 0),
                        total: String(debits.meta.total),
                    })
                }}
            </p>
            <PaginationNav :links="debits.meta.links" />
        </div>

        <DebitCollectDialog
            v-if="canCollect && sepaDate"
            v-model:open="collectionOpen"
            :sepa-date="sepaDate"
        />
    </div>
</template>
