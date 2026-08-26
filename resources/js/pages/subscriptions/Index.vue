<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Banknote, Pencil, Plus, Search } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import PaginationNav from '@/components/PaginationNav.vue';
import SubscriptionDebitDialog from '@/components/SubscriptionDebitDialog.vue';
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
import { create, edit, index } from '@/routes/subscriptions';
import type {
    BreadcrumbItem,
    DebitableSubscription,
    Paginated,
    SubscriptionResource,
} from '@/types';

const props = defineProps<{
    subscriptions: Paginated<SubscriptionResource>;
    filters: { search: string };
    canCreate: boolean;
    canDebit: boolean;
    /** Every collectible fee of the club, not just the page on screen. */
    debitable: DebitableSubscription[];
    freeCount: number;
    sepaDate: string | null;
}>();

// So the edit page's Cancel button can return here instead of resetting to
// the first, unfiltered page.
const editQuery = computed(() => ({
    page: props.subscriptions.meta.current_page,
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
                title: trans('Subscriptions'),
                href: index(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('Subscriptions')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                :title="$t('Subscriptions')"
                :description="
                    $t('Membership fees that can be charged to a member')
                "
            />
            <div class="hidden items-center gap-2 md:flex">
                <Button
                    v-if="canDebit"
                    variant="outline"
                    data-test="open-collect-fees-button"
                    @click="collectionOpen = true"
                >
                    <Banknote class="size-4" />
                    {{ $t('Collect fees') }}
                </Button>
                <Button variant="outline" v-if="canCreate" as-child>
                    <Link :href="create()">
                        <Plus class="size-4" />
                        {{ $t('New subscription') }}
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
                :placeholder="$t('Name')"
                :aria-label="$t('Filter subscriptions')"
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
                            {{ $t('Amount') }}
                        </TableHead>
                        <TableHead class="hidden md:table-cell">
                            {{ $t('Transfer text') }}
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
                        v-for="subscription in subscriptions.data"
                        :key="subscription.id"
                    >
                        <TableCell class="font-medium">
                            {{ subscription.name }}
                            <div
                                v-if="subscription.memo"
                                class="text-xs font-normal text-muted-foreground"
                            >
                                {{ subscription.memo }}
                            </div>
                            <div
                                class="text-xs font-normal text-muted-foreground md:hidden"
                            >
                                {{ subscription.transfer_text }}
                            </div>
                        </TableCell>
                        <TableCell
                            class="text-right whitespace-nowrap tabular-nums"
                        >
                            {{ subscription.amount_label }}
                        </TableCell>
                        <TableCell
                            class="hidden text-muted-foreground md:table-cell"
                        >
                            {{ subscription.transfer_text ?? '—' }}
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{ subscription.members_count }}
                        </TableCell>
                        <TableCell>
                            <div class="flex justify-end gap-1">
                                <Tooltip v-if="subscription.modifiable">
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            as-child
                                        >
                                            <Link
                                                :href="
                                                    edit(subscription.id, {
                                                        query: editQuery,
                                                    })
                                                "
                                                :aria-label="
                                                    $t('Edit :name', {
                                                        name: subscription.name,
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
                        v-if="subscriptions.data.length === 0"
                        :colspan="5"
                    >
                        {{
                            search
                                ? $t('No subscriptions found.')
                                : $t('No subscriptions yet.')
                        }}
                    </TableEmpty>
                </TableBody>
            </Table>
        </div>

        <div
            v-if="subscriptions.meta.total > subscriptions.data.length"
            class="flex items-center justify-center md:justify-between"
        >
            <p class="hidden text-sm text-muted-foreground md:block">
                {{
                    $t('Entries :from–:to of :total', {
                        from: String(subscriptions.meta.from ?? 0),
                        to: String(subscriptions.meta.to ?? 0),
                        total: String(subscriptions.meta.total),
                    })
                }}
            </p>
            <PaginationNav :links="subscriptions.meta.links" />
        </div>

        <SubscriptionDebitDialog
            v-if="canDebit && sepaDate"
            v-model:open="collectionOpen"
            :debitable="debitable"
            :free-count="freeCount"
            :sepa-date="sepaDate"
        />
    </div>
</template>
