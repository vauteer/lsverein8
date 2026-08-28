<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { ref, watch } from 'vue';
import MemberController from '@/actions/App/Http/Controllers/MemberController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import MemberFormFields from '@/components/MemberFormFields.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { create, index } from '@/routes/members';
import type {
    BreadcrumbItem,
    DuplicateMember,
    MemberListFilters,
    SelectOption,
} from '@/types';

const props = defineProps<{
    genders: SelectOption[];
    sections: SelectOption[];
    subscriptions: SelectOption[];
    today: string;
    backQuery: Partial<MemberListFilters> & { page?: number };
    /** Somebody of the same name and birthday is already on file. */
    duplicate: DuplicateMember | null;
}>();

// Same as the edit form: storing hands the list selection on, so the new
// member's page opens with the selection you came from.
const { backQuery } = props;
const cancelHref = index({ query: backQuery });

const sectionId = ref(
    props.sections.length > 0 ? String(props.sections[0].id) : '',
);
// reka-ui cannot hold an empty value, so "no subscription" travels as a
// sentinel that the hidden input turns back into '' — same pattern as the
// club language in UserFormFields.
const NO_SUBSCRIPTION = 'none';
const subscriptionId = ref(NO_SUBSCRIPTION);

// Cleared whenever a fresh warning arrives, so a second, different duplicate
// cannot be waved through by a tick the user set for the first one.
const confirmDuplicate = ref(false);
watch(
    () => props.duplicate,
    () => (confirmDuplicate.value = false),
);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: trans('Members'),
                href: index(),
            } satisfies BreadcrumbItem,
            {
                title: trans('New member'),
                href: create(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('New member')" />

    <div class="mx-auto w-full max-w-3xl p-4">
        <Heading
            :title="$t('New member')"
            :description="
                $t(
                    'The member number is assigned automatically. Joining date, section and subscription can be changed later.',
                )
            "
        />

        <Form
            v-bind="MemberController.store.form({ query: backQuery })"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <MemberFormFields :genders="genders" :errors="errors" />

            <div
                class="grid gap-6 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <h2 class="text-sm font-medium">{{ $t('Joining') }}</h2>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="entry_date">{{ $t('Joined on') }}</Label>
                        <Input
                            id="entry_date"
                            name="entry_date"
                            type="date"
                            :default-value="today"
                            required
                        />
                        <InputError :message="errors.entry_date" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="section_id">{{ $t('Section') }}</Label>
                        <Select v-model="sectionId">
                            <SelectTrigger id="section_id" class="w-full">
                                <SelectValue
                                    :placeholder="$t('Pick a section')"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="section in sections"
                                    :key="section.id"
                                    :value="String(section.id)"
                                >
                                    {{ section.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <input
                            type="hidden"
                            name="section_id"
                            :value="sectionId"
                        />
                        <InputError :message="errors.section_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="subscription_id">
                            {{ $t('Subscription') }}
                        </Label>
                        <Select v-model="subscriptionId">
                            <SelectTrigger id="subscription_id" class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="NO_SUBSCRIPTION">
                                    {{ $t('(none)') }}
                                </SelectItem>
                                <SelectItem
                                    v-for="subscription in subscriptions"
                                    :key="subscription.id"
                                    :value="String(subscription.id)"
                                >
                                    {{ subscription.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <input
                            type="hidden"
                            name="subscription_id"
                            :value="
                                subscriptionId === NO_SUBSCRIPTION
                                    ? ''
                                    : subscriptionId
                            "
                        />
                        <InputError :message="errors.subscription_id" />
                    </div>
                </div>
            </div>

            <div
                v-if="duplicate"
                class="flex flex-col gap-3 rounded-xl border border-destructive/50 p-4"
                data-test="duplicate-warning"
            >
                <p class="flex items-start gap-2 text-sm">
                    <TriangleAlert
                        class="mt-0.5 size-4 shrink-0 text-destructive"
                    />
                    <span>{{ errors.confirm_duplicate }}</span>
                </p>
                <div>
                    <!-- A plain anchor would lose the entered data; this is an
                    Inertia visit, so the browser's back button brings the
                    half-filled form back. -->
                    <Link
                        :href="duplicate.href"
                        class="text-sm underline underline-offset-4"
                        data-test="duplicate-link"
                    >
                        {{
                            $t('Open :name (no. :number)', {
                                name: duplicate.name,
                                number: String(duplicate.member_id),
                            })
                        }}
                    </Link>
                </div>
                <Label class="flex items-center gap-2 text-sm font-normal">
                    <Checkbox v-model="confirmDuplicate" />
                    {{ $t('A different person — enter them anyway') }}
                </Label>
            </div>
            <input
                type="hidden"
                name="confirm_duplicate"
                :value="confirmDuplicate ? '1' : ''"
            />

            <div class="flex items-center gap-4">
                <Button
                    variant="outline"
                    :disabled="processing"
                    data-test="save-member-button"
                >
                    {{ $t('Save') }}
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="cancelHref">{{ $t('Cancel') }}</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
