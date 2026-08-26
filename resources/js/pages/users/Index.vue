<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { LogIn, Pencil, Plus, Search, Shield } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import ImpersonationController from '@/actions/App/Http/Controllers/ImpersonationController';
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
import { create, edit, index } from '@/routes/users';
import type { BreadcrumbItem, Paginated, UserResource } from '@/types';

const props = defineProps<{
    users: Paginated<UserResource>;
    filters: { search: string };
}>();

const page = usePage();

// So the edit page's Cancel button can return here instead of resetting to
// the first, unfiltered page.
const editQuery = computed(() => ({
    page: props.users.meta.current_page,
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

function formatLastLogin(user: UserResource): string {
    if (!user.last_login) {
        return trans('Never');
    }

    return new Date(user.last_login).toLocaleString(page.props.locale, {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: trans('Users'),
                href: index(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('Users')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                :title="$t('Users')"
                :description="$t('Manage the users of this club')"
            />
            <Button variant="outline" as-child class="hidden md:inline-flex">
                <Link :href="create()">
                    <Plus class="size-4" />
                    {{ $t('New user') }}
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
                :placeholder="$t('Name or email address')"
                :aria-label="$t('Filter users')"
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
                            {{ $t('Email address') }}
                        </TableHead>
                        <TableHead class="hidden lg:table-cell">
                            {{ $t('Phone') }}
                        </TableHead>
                        <TableHead>{{ $t('Role') }}</TableHead>
                        <TableHead class="hidden md:table-cell">
                            {{ $t('Last login') }}
                        </TableHead>
                        <TableHead class="text-right">
                            {{ $t('Actions') }}
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="user in users.data" :key="user.id">
                        <TableCell class="font-medium">
                            <span class="flex items-center gap-1.5">
                                {{ user.name }}
                                <Tooltip v-if="user.admin">
                                    <TooltipTrigger as-child>
                                        <Shield
                                            class="size-3.5 shrink-0 text-muted-foreground"
                                            :aria-label="$t('Superuser')"
                                        />
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        {{ $t('Superuser') }}
                                    </TooltipContent>
                                </Tooltip>
                            </span>
                            <div
                                class="text-xs font-normal text-muted-foreground md:hidden"
                            >
                                {{ user.email }}
                            </div>
                        </TableCell>
                        <TableCell class="hidden md:table-cell">
                            {{ user.email }}
                        </TableCell>
                        <TableCell
                            class="hidden text-muted-foreground lg:table-cell"
                        >
                            {{ user.phone || '—' }}
                        </TableCell>
                        <TableCell>{{ user.role_label ?? '—' }}</TableCell>
                        <TableCell
                            class="hidden text-muted-foreground md:table-cell"
                        >
                            {{ formatLastLogin(user) }}
                        </TableCell>
                        <TableCell>
                            <div class="flex justify-end gap-1">
                                <Tooltip v-if="user.impersonatable">
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            as-child
                                        >
                                            <Link
                                                :href="
                                                    ImpersonationController.store(
                                                        user.id,
                                                    )
                                                "
                                                as="button"
                                                :aria-label="
                                                    $t('Log in as :name', {
                                                        name: user.name,
                                                    })
                                                "
                                            >
                                                <LogIn class="size-4" />
                                            </Link>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        {{ $t('Log in as user') }}
                                    </TooltipContent>
                                </Tooltip>
                                <Tooltip v-if="user.modifiable">
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            as-child
                                        >
                                            <Link
                                                :href="
                                                    edit(user.id, {
                                                        query: editQuery,
                                                    })
                                                "
                                                :aria-label="
                                                    $t('Edit :name', {
                                                        name: user.name,
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
                    <TableEmpty v-if="users.data.length === 0" :colspan="6">
                        {{
                            search ? $t('No users found.') : $t('No users yet.')
                        }}
                    </TableEmpty>
                </TableBody>
            </Table>
        </div>

        <div
            v-if="users.meta.total > users.data.length"
            class="flex items-center justify-center md:justify-between"
        >
            <p class="hidden text-sm text-muted-foreground md:block">
                {{
                    $t('Entries :from–:to of :total', {
                        from: String(users.meta.from ?? 0),
                        to: String(users.meta.to ?? 0),
                        total: String(users.meta.total),
                    })
                }}
            </p>
            <PaginationNav :links="users.meta.links" />
        </div>
    </div>
</template>
