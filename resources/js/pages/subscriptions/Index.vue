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
import { index as members } from '@/routes/members';
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
            <div class="flex shrink-0 items-center gap-2">
                <Button
                    v-if="canDebit"
                    variant="outline"
                    data-test="open-collect-fees-button"
                    :aria-label="$t('Collect fees')"
                    @click="collectionOpen = true"
                >
                    <Banknote class="size-4" />
                    <span class="max-md:hidden">{{ $t('Collect fees') }}</span>
                </Button>
                <Button variant="outline" v-if="canCreate" as-child>
                    <Link :href="create()" :aria-label="$t('New subscription')">
                        <Plus class="size-4" />
                        <span class="max-md:hidden">{{
                            $t('New subscription')
                        }}</span>
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
                        <!-- w-full: the name column absorbs what is left, so
                        the other columns shrink to their content and the last
                        one stays on screen on a phone. -->
                        <TableHead class="w-full">{{ $t('Name') }}</TableHead>
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
                        <!-- whitespace-normal: table cells are nowrap by
                        default, so a long name pushed the table wider than the
                        viewport and the last column off it. -->
                        <TableCell class="font-medium whitespace-normal">
                            {{ subscription.name }}
                            <!-- Both the note and the transfer text are held
                            back on a phone: three stacked lines per row made
                            the list hard to scan, and neither is what one
                            comes to this screen for. From md up the note
                            returns here and the transfer text gets its own
                            column. -->
                            <div
                                v-if="subscription.memo"
                                class="hidden text-xs font-normal text-muted-foreground md:block"
                            >
                                {{ subscription.memo }}
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
                            <!-- The number is the same set the selection
                            shows, so it doubles as the way there. Nothing to
                            click at zero. -->
                            <Link
                                v-if="subscription.members_count > 0"
                                :href="
                                    members({
                                        query: {
                                            filter: `subscription_${subscription.id}`,
                                        },
                                    })
                                "
                                class="underline-offset-4 hover:underline"
                                :aria-label="
                                    $t('Show the members paying :name', {
                                        name: subscription.name,
                                    })
                                "
                            >
                                {{ subscription.members_count }}
                            </Link>
                            <span v-else class="text-muted-foreground">
                                {{ subscription.members_count }}
                            </span>
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
