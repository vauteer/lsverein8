<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { edit, index } from '@/routes/members';
import type { BreadcrumbItem, MemberDetail, MemberListFilters } from '@/types';

const props = defineProps<{
    member: MemberDetail;
    modifiable: boolean;
    /** Subscriptions and bank details are a treasurer's business. */
    showsFinances: boolean;
    backQuery: Partial<MemberListFilters> & { page?: number };
}>();

const backHref = index({ query: props.backQuery });

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: trans('Members'),
                href: index(),
            } satisfies BreadcrumbItem,
            {
                title: trans('Details'),
                href: index(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="member.full_name" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                :title="member.full_name"
                :description="
                    $t('Member number :number', {
                        number: String(member.member_id),
                    })
                "
            />
            <Button v-if="modifiable" as-child class="hidden sm:inline-flex">
                <Link :href="edit(member.id, { query: backQuery })">
                    {{ $t('Edit') }}
                </Link>
            </Button>
        </div>

        <div class="flex flex-wrap gap-2">
            <Badge :variant="member.is_member ? 'default' : 'secondary'">
                {{ member.is_member ? $t('Member') : $t('Former member') }}
            </Badge>
            <Badge v-if="member.death_day" variant="secondary">
                {{ $t('Deceased :date', { date: member.death_day }) }}
            </Badge>
        </div>

        <dl
            class="grid gap-x-6 gap-y-4 rounded-xl border border-sidebar-border/70 p-4 sm:grid-cols-2 dark:border-sidebar-border"
        >
            <div>
                <dt class="text-xs text-muted-foreground">
                    {{ $t('Salutation') }}
                </dt>
                <dd>{{ member.gender }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">
                    {{ $t('Date of birth') }}
                </dt>
                <dd>
                    {{ member.birthday }}
                    <span class="text-muted-foreground">
                        ({{ $t(':age years', { age: String(member.age) }) }})
                    </span>
                </dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs text-muted-foreground">
                    {{ $t('Address') }}
                </dt>
                <dd>{{ member.address }}</dd>
            </div>
            <div v-if="member.email">
                <dt class="text-xs text-muted-foreground">{{ $t('Email') }}</dt>
                <dd>{{ member.email }}</dd>
            </div>
            <div v-if="member.phone">
                <dt class="text-xs text-muted-foreground">{{ $t('Phone') }}</dt>
                <dd>{{ member.phone }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">
                    {{ $t('Joined on') }}
                </dt>
                <dd>
                    {{ member.entry }}
                    <span class="text-muted-foreground">
                        ({{
                            $t(':years years', {
                                years: String(member.membership_years),
                            })
                        }})
                    </span>
                </dd>
            </div>
            <div v-if="showsFinances">
                <dt class="text-xs text-muted-foreground">
                    {{ $t('Payment method') }}
                </dt>
                <dd>
                    {{ member.payment_method }}
                    <span
                        v-if="member.iban"
                        class="font-mono text-xs text-muted-foreground"
                    >
                        {{ member.iban }}
                    </span>
                </dd>
            </div>
            <div v-if="member.memo" class="sm:col-span-2">
                <dt class="text-xs text-muted-foreground">{{ $t('Memo') }}</dt>
                <dd>{{ member.memo }}</dd>
            </div>
        </dl>

        <section
            v-for="group in [
                { title: $t('Memberships'), rows: member.memberships },
                { title: $t('Sections'), rows: member.sections },
                { title: $t('Roles'), rows: member.roles },
                { title: $t('Issued items'), rows: member.items },
            ]"
            :key="group.title"
            class="flex flex-col gap-2"
        >
            <h2 class="text-sm font-medium">{{ group.title }}</h2>
            <p
                v-if="group.rows.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ $t('Nothing recorded.') }}
            </p>
            <ul v-else class="flex flex-col gap-1 text-sm">
                <li
                    v-for="(row, position) in group.rows"
                    :key="`${row.name}-${position}`"
                    class="flex flex-wrap justify-between gap-x-4 rounded-lg border border-sidebar-border/70 px-3 py-2 dark:border-sidebar-border"
                >
                    <span class="font-medium">{{ row.name }}</span>
                    <span class="text-muted-foreground tabular-nums">
                        {{ row.range }}
                        <template v-if="row.memo">· {{ row.memo }}</template>
                    </span>
                </li>
            </ul>
        </section>

        <section class="flex flex-col gap-2">
            <h2 class="text-sm font-medium">{{ $t('Honours') }}</h2>
            <p
                v-if="member.events.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ $t('Nothing recorded.') }}
            </p>
            <ul v-else class="flex flex-col gap-1 text-sm">
                <li
                    v-for="(event, position) in member.events"
                    :key="`${event.name}-${position}`"
                    class="flex flex-wrap justify-between gap-x-4 rounded-lg border border-sidebar-border/70 px-3 py-2 dark:border-sidebar-border"
                >
                    <span class="font-medium">{{ event.name }}</span>
                    <span class="text-muted-foreground tabular-nums">
                        {{ event.date }}
                        <template v-if="event.memo"
                            >· {{ event.memo }}</template
                        >
                    </span>
                </li>
            </ul>
        </section>

        <section v-if="showsFinances" class="flex flex-col gap-2">
            <h2 class="text-sm font-medium">{{ $t('Subscriptions') }}</h2>
            <p
                v-if="member.subscriptions.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ $t('Nothing recorded.') }}
            </p>
            <ul v-else class="flex flex-col gap-1 text-sm">
                <li
                    v-for="(subscription, position) in member.subscriptions"
                    :key="`${subscription.name}-${position}`"
                    class="flex flex-wrap justify-between gap-x-4 rounded-lg border border-sidebar-border/70 px-3 py-2 dark:border-sidebar-border"
                >
                    <span class="font-medium">{{ subscription.name }}</span>
                    <span class="text-muted-foreground tabular-nums">
                        {{ subscription.amount_label }}
                    </span>
                </li>
            </ul>
        </section>

        <div>
            <Button variant="ghost" as-child>
                <Link :href="backHref">{{ $t('Back') }}</Link>
            </Button>
        </div>
    </div>
</template>
