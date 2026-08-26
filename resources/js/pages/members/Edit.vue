<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import MemberController from '@/actions/App/Http/Controllers/MemberController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import MemberFormFields from '@/components/MemberFormFields.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, show } from '@/routes/members';
import type {
    BreadcrumbItem,
    MemberFormData,
    MemberListFilters,
    SelectOption,
} from '@/types';

const props = defineProps<{
    member: MemberFormData;
    genders: SelectOption[];
    paymentMethods: SelectOption[];
    /** Only somebody with an open membership can be resigned. */
    resignable: boolean;
    deletable: boolean;
    today: string;
    backQuery: Partial<MemberListFilters> & { page?: number };
}>();

const cancelHref = index({ query: props.backQuery });

const confirmingDeletion = ref(false);
const confirmingResignation = ref(false);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: trans('Members'),
                href: index(),
            } satisfies BreadcrumbItem,
            {
                title: trans('Edit member'),
                href: index(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('Edit :name', { name: member.full_name })" />

    <div class="mx-auto w-full max-w-3xl p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                :title="member.full_name"
                :description="
                    $t('Member number :number', {
                        number: String(member.member_id),
                    })
                "
            />
            <Button variant="outline" as-child class="hidden sm:inline-flex">
                <Link :href="show(member.id, { query: backQuery })">
                    {{ $t('Details') }}
                </Link>
            </Button>
        </div>

        <Form
            v-bind="MemberController.update.form(props.member.id)"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <MemberFormFields
                :member="member"
                :genders="genders"
                :payment-methods="paymentMethods"
                :errors="errors"
            />

            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <Button
                        v-if="resignable"
                        type="button"
                        variant="outline"
                        data-test="resign-member-button"
                        @click="confirmingResignation = true"
                    >
                        {{ $t('End membership') }}
                    </Button>
                    <Button
                        v-if="deletable"
                        type="button"
                        variant="destructive"
                        data-test="delete-member-button"
                        @click="confirmingDeletion = true"
                    >
                        {{ $t('Delete') }}
                    </Button>
                </div>
                <div class="flex items-center gap-4">
                    <Button
                        :disabled="processing"
                        data-test="save-member-button"
                    >
                        {{ $t('Save') }}
                    </Button>
                    <Button variant="ghost" as-child>
                        <Link :href="cancelHref">{{ $t('Cancel') }}</Link>
                    </Button>
                </div>
            </div>
        </Form>

        <Dialog
            :open="confirmingResignation"
            @update:open="(open) => (confirmingResignation = open)"
        >
            <DialogContent>
                <Form
                    v-bind="MemberController.resign.form(member.id)"
                    v-slot="{ errors, processing }"
                >
                    <DialogHeader class="space-y-3">
                        <DialogTitle>{{ $t('End membership') }}</DialogTitle>
                        <DialogDescription>
                            {{
                                $t(
                                    'Closes every open membership and section on this date. :name and their history are kept.',
                                    { name: member.full_name },
                                )
                            }}
                        </DialogDescription>
                    </DialogHeader>
                    <div class="mt-6 grid gap-2">
                        <Label for="resign_date">{{ $t('Left on') }}</Label>
                        <Input
                            id="resign_date"
                            name="date"
                            type="date"
                            :default-value="today"
                            required
                            class="w-full sm:w-44"
                        />
                        <InputError :message="errors.date" />
                    </div>
                    <DialogFooter class="mt-6 gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary" type="button">
                                {{ $t('Cancel') }}
                            </Button>
                        </DialogClose>
                        <Button
                            :disabled="processing"
                            data-test="confirm-resign-member-button"
                        >
                            {{ $t('End membership') }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="confirmingDeletion"
            @update:open="(open) => (confirmingDeletion = open)"
        >
            <DialogContent>
                <Form
                    v-bind="MemberController.destroy.form(member.id)"
                    v-slot="{ processing }"
                >
                    <DialogHeader class="space-y-3">
                        <DialogTitle>
                            {{
                                $t('Delete :name?', { name: member.full_name })
                            }}
                        </DialogTitle>
                        <DialogDescription>
                            {{
                                $t(
                                    'Their memberships, sections, roles, honours, subscriptions and issued items go with them. To record that somebody has left, end the membership instead. This action cannot be undone.',
                                )
                            }}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="mt-6 gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary" type="button">
                                {{ $t('Cancel') }}
                            </Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            :disabled="processing"
                            data-test="confirm-delete-member-button"
                        >
                            {{ $t('Delete') }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
