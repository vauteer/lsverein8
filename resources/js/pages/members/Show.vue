<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import MemberEventController from '@/actions/App/Http/Controllers/Members/MemberEventController';
import MemberItemController from '@/actions/App/Http/Controllers/Members/MemberItemController';
import MemberRoleController from '@/actions/App/Http/Controllers/Members/MemberRoleController';
import MemberSectionController from '@/actions/App/Http/Controllers/Members/MemberSectionController';
import MembershipController from '@/actions/App/Http/Controllers/Members/MembershipController';
import MemberSubscriptionController from '@/actions/App/Http/Controllers/Members/MemberSubscriptionController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import MemberRelationDialog from '@/components/members/MemberRelationDialog.vue';
import MemberRelationSection from '@/components/members/MemberRelationSection.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit, index } from '@/routes/members';
import type {
    BreadcrumbItem,
    MemberDetail,
    MemberEventRow,
    MemberListFilters,
    MemberRangeRow,
    MemberRelationOptions,
    MemberRelationRow,
    MemberSubscriptionRow,
} from '@/types';

const props = defineProps<{
    member: MemberDetail;
    modifiable: boolean;
    /** Subscriptions and bank details are a treasurer's business. */
    showsFinances: boolean;
    /** clubs.use_items: no inventory section where the club keeps none. */
    usesItems: boolean;
    options: MemberRelationOptions | null;
    today: string;
    backQuery: Partial<MemberListFilters> & { page?: number };
}>();

const backHref = index({ query: props.backQuery });

// What the section component renders. The raw row stays available for the
// dialog, keyed by the pivot id.
const toRows = (
    rows: (MemberRangeRow | MemberEventRow | MemberSubscriptionRow)[],
    detail: (row: never) => string,
): MemberRelationRow[] =>
    rows.map((row) => ({
        id: row.id,
        name: row.name,
        detail: detail(row as never),
        memo: row.memo,
    }));

const rangeDetail = (row: MemberRangeRow) => row.range;

const membershipRows = computed(() =>
    toRows(props.member.memberships, rangeDetail),
);
const sectionRows = computed(() => toRows(props.member.sections, rangeDetail));
const roleRows = computed(() => toRows(props.member.roles, rangeDetail));
const itemRows = computed(() => toRows(props.member.items, rangeDetail));
const eventRows = computed(() =>
    toRows(props.member.events, (row: MemberEventRow) => row.date_label),
);
const subscriptionRows = computed(() =>
    toRows(
        props.member.subscriptions,
        (row: MemberSubscriptionRow) => row.amount_label,
    ),
);

/**
 * Which dialog is open and on what. `row` null means "add"; the dialogs are
 * one instance each, remounted by `formKey` when they are pointed elsewhere.
 */
type RelationKind =
    'memberships' | 'sections' | 'roles' | 'items' | 'events' | 'subscriptions';

const openKind = ref<RelationKind | null>(null);
const editingId = ref<number | null>(null);

const openDialog = (kind: RelationKind, id: number | null) => {
    openKind.value = kind;
    editingId.value = id;
};

const isOpen = (kind: RelationKind) => openKind.value === kind;

const setOpen = (kind: RelationKind, open: boolean) => {
    if (!open && openKind.value === kind) {
        openKind.value = null;
    }
};

const formKey = computed(
    () => `${openKind.value ?? 'none'}-${editingId.value ?? 'new'}`,
);

const rangeRow = (rows: MemberRangeRow[]): MemberRangeRow | null =>
    rows.find((row) => row.id === editingId.value) ?? null;

const editedMembership = computed(() => rangeRow(props.member.memberships));
const editedSection = computed(() => rangeRow(props.member.sections));
const editedRole = computed(() => rangeRow(props.member.roles));
const editedItem = computed(() => rangeRow(props.member.items));
const editedEvent = computed(
    () => props.member.events.find((row) => row.id === editingId.value) ?? null,
);
const editedSubscription = computed(
    () =>
        props.member.subscriptions.find((row) => row.id === editingId.value) ??
        null,
);

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
                    {{ $t('Gender') }}
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

        <MemberRelationSection
            :title="$t('Memberships')"
            :rows="membershipRows"
            :modifiable="modifiable"
            :destroy="
                (row) => MembershipController.destroy.form([member.id, row])
            "
            @add="openDialog('memberships', null)"
            @edit="(row) => openDialog('memberships', row.id)"
        />

        <MemberRelationSection
            :title="$t('Sections')"
            :rows="sectionRows"
            :modifiable="modifiable"
            :destroy="
                (row) => MemberSectionController.destroy.form([member.id, row])
            "
            @add="openDialog('sections', null)"
            @edit="(row) => openDialog('sections', row.id)"
        />

        <MemberRelationSection
            :title="$t('Roles')"
            :rows="roleRows"
            :modifiable="modifiable"
            :destroy="
                (row) => MemberRoleController.destroy.form([member.id, row])
            "
            @add="openDialog('roles', null)"
            @edit="(row) => openDialog('roles', row.id)"
        />

        <MemberRelationSection
            :title="$t('Honours')"
            :rows="eventRows"
            :modifiable="modifiable"
            :destroy="
                (row) => MemberEventController.destroy.form([member.id, row])
            "
            @add="openDialog('events', null)"
            @edit="(row) => openDialog('events', row.id)"
        />

        <MemberRelationSection
            v-if="showsFinances"
            :title="$t('Subscriptions')"
            :rows="subscriptionRows"
            :modifiable="modifiable"
            :destroy="
                (row) =>
                    MemberSubscriptionController.destroy.form([member.id, row])
            "
            @add="openDialog('subscriptions', null)"
            @edit="(row) => openDialog('subscriptions', row.id)"
        />

        <MemberRelationSection
            v-if="usesItems"
            :title="$t('Issued items')"
            :rows="itemRows"
            :modifiable="modifiable"
            :destroy="
                (row) => MemberItemController.destroy.form([member.id, row])
            "
            @add="openDialog('items', null)"
            @edit="(row) => openDialog('items', row.id)"
        />

        <div>
            <Button variant="ghost" as-child>
                <Link :href="backHref">{{ $t('Back') }}</Link>
            </Button>
        </div>

        <template v-if="modifiable && options">
            <MemberRelationDialog
                :open="isOpen('memberships')"
                :title="
                    editedMembership
                        ? $t('Edit membership')
                        : $t('Add membership')
                "
                :action="
                    editedMembership
                        ? MembershipController.update.form([
                              member.id,
                              editedMembership.id,
                          ])
                        : MembershipController.store.form(member.id)
                "
                :memo="editedMembership?.memo"
                :form-key="formKey"
                @update:open="(open) => setOpen('memberships', open)"
            >
                <template #default="{ errors }">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="from">{{ $t('From') }}</Label>
                            <Input
                                id="from"
                                name="from"
                                type="date"
                                :default-value="editedMembership?.from ?? today"
                                required
                            />
                            <InputError :message="errors.from" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="to">{{ $t('To') }}</Label>
                            <Input
                                id="to"
                                name="to"
                                type="date"
                                :default-value="editedMembership?.to ?? ''"
                            />
                            <InputError :message="errors.to" />
                        </div>
                    </div>
                </template>
            </MemberRelationDialog>

            <MemberRelationDialog
                :open="isOpen('sections')"
                :title="editedSection ? $t('Edit section') : $t('Add section')"
                :action="
                    editedSection
                        ? MemberSectionController.update.form([
                              member.id,
                              editedSection.id,
                          ])
                        : MemberSectionController.store.form(member.id)
                "
                :options="options.sections"
                option-name="section_id"
                :option-label="$t('Section')"
                :selected="editedSection?.related_id"
                :memo="editedSection?.memo"
                :form-key="formKey"
                @update:open="(open) => setOpen('sections', open)"
            >
                <template #default="{ errors }">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="from">{{ $t('From') }}</Label>
                            <Input
                                id="from"
                                name="from"
                                type="date"
                                :default-value="editedSection?.from ?? today"
                                required
                            />
                            <InputError :message="errors.from" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="to">{{ $t('To') }}</Label>
                            <Input
                                id="to"
                                name="to"
                                type="date"
                                :default-value="editedSection?.to ?? ''"
                            />
                            <InputError :message="errors.to" />
                        </div>
                    </div>
                </template>
            </MemberRelationDialog>

            <MemberRelationDialog
                :open="isOpen('roles')"
                :title="editedRole ? $t('Edit role') : $t('Add role')"
                :action="
                    editedRole
                        ? MemberRoleController.update.form([
                              member.id,
                              editedRole.id,
                          ])
                        : MemberRoleController.store.form(member.id)
                "
                :options="options.roles"
                option-name="role_id"
                :option-label="$t('Role')"
                :selected="editedRole?.related_id"
                :memo="editedRole?.memo"
                :form-key="formKey"
                @update:open="(open) => setOpen('roles', open)"
            >
                <template #default="{ errors }">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="from">{{ $t('From') }}</Label>
                            <Input
                                id="from"
                                name="from"
                                type="date"
                                :default-value="editedRole?.from ?? today"
                                required
                            />
                            <InputError :message="errors.from" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="to">{{ $t('To') }}</Label>
                            <Input
                                id="to"
                                name="to"
                                type="date"
                                :default-value="editedRole?.to ?? ''"
                            />
                            <InputError :message="errors.to" />
                        </div>
                    </div>
                </template>
            </MemberRelationDialog>

            <MemberRelationDialog
                :open="isOpen('events')"
                :title="editedEvent ? $t('Edit honour') : $t('Add honour')"
                :action="
                    editedEvent
                        ? MemberEventController.update.form([
                              member.id,
                              editedEvent.id,
                          ])
                        : MemberEventController.store.form(member.id)
                "
                :options="options.events"
                option-name="event_id"
                :option-label="$t('Honour')"
                :selected="editedEvent?.event_id"
                :memo="editedEvent?.memo"
                :form-key="formKey"
                @update:open="(open) => setOpen('events', open)"
            >
                <template #default="{ errors }">
                    <div class="grid gap-2">
                        <Label for="date">{{ $t('Date') }}</Label>
                        <Input
                            id="date"
                            name="date"
                            type="date"
                            :default-value="editedEvent?.date ?? today"
                            required
                            class="w-full sm:w-44"
                        />
                        <InputError :message="errors.date" />
                    </div>
                </template>
            </MemberRelationDialog>

            <MemberRelationDialog
                v-if="showsFinances"
                :open="isOpen('subscriptions')"
                :title="
                    editedSubscription
                        ? $t('Edit subscription')
                        : $t('Add subscription')
                "
                :action="
                    editedSubscription
                        ? MemberSubscriptionController.update.form([
                              member.id,
                              editedSubscription.id,
                          ])
                        : MemberSubscriptionController.store.form(member.id)
                "
                :options="options.subscriptions"
                option-name="subscription_id"
                :option-label="$t('Subscription')"
                :selected="editedSubscription?.subscription_id"
                :memo="editedSubscription?.memo"
                :form-key="formKey"
                @update:open="(open) => setOpen('subscriptions', open)"
            />

            <MemberRelationDialog
                v-if="usesItems"
                :open="isOpen('items')"
                :title="editedItem ? $t('Edit issued item') : $t('Issue item')"
                :action="
                    editedItem
                        ? MemberItemController.update.form([
                              member.id,
                              editedItem.id,
                          ])
                        : MemberItemController.store.form(member.id)
                "
                :options="options.items"
                option-name="item_id"
                :option-label="$t('Item')"
                :selected="editedItem?.related_id"
                :memo="editedItem?.memo"
                :form-key="formKey"
                @update:open="(open) => setOpen('items', open)"
            >
                <template #default="{ errors }">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="from">{{ $t('Issued on') }}</Label>
                            <Input
                                id="from"
                                name="from"
                                type="date"
                                :default-value="editedItem?.from ?? today"
                                required
                            />
                            <InputError :message="errors.from" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="to">{{ $t('Returned on') }}</Label>
                            <Input
                                id="to"
                                name="to"
                                type="date"
                                :default-value="editedItem?.to ?? ''"
                            />
                            <InputError :message="errors.to" />
                        </div>
                    </div>
                </template>
            </MemberRelationDialog>
        </template>
    </div>
</template>
