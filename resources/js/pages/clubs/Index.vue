<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { ArrowRightLeft, Check, Pencil, Plus, Search } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { ref, watch } from 'vue';
import ClubSwitchController from '@/actions/App/Http/Controllers/ClubSwitchController';
import Heading from '@/components/Heading.vue';
import PaginationNav from '@/components/PaginationNav.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
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
import { create, edit, index } from '@/routes/clubs';
import { index as members } from '@/routes/members';
import type { BreadcrumbItem, ClubResource, Paginated } from '@/types';

const props = defineProps<{
    clubs: Paginated<ClubResource>;
    filters: { search: string };
    canCreate: boolean;
}>();

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
            { title: trans('Clubs'), href: index() } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('Clubs')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                :title="$t('Clubs')"
                :description="$t('Every club in this installation')"
            />
            <Button
                variant="outline"
                v-if="canCreate"
                as-child
                class="hidden md:inline-flex"
            >
                <Link :href="create()">
                    <Plus class="size-4" />
                    {{ $t('New club') }}
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
                :aria-label="$t('Filter clubs')"
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
                        <TableHead class="hidden md:table-cell">
                            {{ $t('City') }}
                        </TableHead>
                        <TableHead class="text-right">
                            {{ $t('Members') }}
                        </TableHead>
                        <TableHead class="text-right">
                            {{ $t('Users') }}
                        </TableHead>
                        <TableHead class="text-right">
                            {{ $t('Actions') }}
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="club in clubs.data" :key="club.id">
                        <TableCell class="font-medium">
                            <span class="flex items-center gap-2">
                                <Avatar class="size-6 rounded-md">
                                    <AvatarImage
                                        v-if="club.logo_url"
                                        class="object-contain"
                                        :src="club.logo_url"
                                        :alt="club.name"
                                    />
                                    <AvatarFallback
                                        class="rounded-md text-[10px]"
                                    >
                                        {{ club.name.charAt(0) }}
                                    </AvatarFallback>
                                </Avatar>
                                {{ club.name }}
                                <Tooltip v-if="club.current">
                                    <TooltipTrigger as-child>
                                        <Check
                                            class="size-3.5 shrink-0 text-muted-foreground"
                                            :aria-label="$t('Current club')"
                                        />
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        {{ $t('Current club') }}
                                    </TooltipContent>
                                </Tooltip>
                            </span>
                            <div
                                class="text-xs font-normal text-muted-foreground md:hidden"
                            >
                                {{ club.city }}
                            </div>
                        </TableCell>
                        <TableCell
                            class="hidden text-muted-foreground md:table-cell"
                        >
                            {{ club.city }}
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            <!-- Only the current club's row is a link: the
                            member list is scoped to the club the viewer is
                            working in, so on any other row it would show the
                            wrong people. Switch first — the row offers that. -->
                            <Link
                                v-if="club.current && club.members_count > 0"
                                :href="members()"
                                class="underline-offset-4 hover:underline"
                                :aria-label="
                                    $t('Show the members of :name', {
                                        name: club.name,
                                    })
                                "
                            >
                                {{ club.members_count }}
                            </Link>
                            <span
                                v-else
                                :class="
                                    club.current ? '' : 'text-muted-foreground'
                                "
                            >
                                {{ club.members_count }}
                            </span>
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{ club.users_count }}
                        </TableCell>
                        <TableCell>
                            <div class="flex justify-end gap-1">
                                <Form
                                    v-if="club.switchable"
                                    v-bind="
                                        ClubSwitchController.store.form(club.id)
                                    "
                                >
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                :aria-label="
                                                    $t('Switch to :name', {
                                                        name: club.name,
                                                    })
                                                "
                                            >
                                                <ArrowRightLeft
                                                    class="size-4"
                                                />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            {{ $t('Switch club') }}
                                        </TooltipContent>
                                    </Tooltip>
                                </Form>
                                <Tooltip v-if="club.modifiable">
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            as-child
                                        >
                                            <Link
                                                :href="edit(club.id)"
                                                :aria-label="
                                                    $t('Edit :name', {
                                                        name: club.name,
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
                    <TableEmpty v-if="clubs.data.length === 0" :colspan="5">
                        {{
                            search ? $t('No clubs found.') : $t('No clubs yet.')
                        }}
                    </TableEmpty>
                </TableBody>
            </Table>
        </div>

        <div
            v-if="clubs.meta.total > clubs.data.length"
            class="flex items-center justify-center md:justify-between"
        >
            <p class="hidden text-sm text-muted-foreground md:block">
                {{
                    $t('Entries :from–:to of :total', {
                        from: String(clubs.meta.from ?? 0),
                        to: String(clubs.meta.to ?? 0),
                        total: String(clubs.meta.total),
                    })
                }}
            </p>
            <PaginationNav :links="clubs.meta.links" />
        </div>
    </div>
</template>
