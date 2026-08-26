<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import DebitController from '@/actions/App/Http/Controllers/DebitController';
import DebitFormFields from '@/components/DebitFormFields.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/debits';
import type { BreadcrumbItem, DebitableMember } from '@/types';

defineProps<{
    members: DebitableMember[];
    today: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: trans('Debits'),
                href: index(),
            } satisfies BreadcrumbItem,
            {
                title: trans('New debit'),
                href: create(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('New debit')" />

    <div class="mx-auto w-full max-w-2xl p-4">
        <Heading
            :title="$t('New debit')"
            :description="
                $t(
                    'A one-off amount, collected from the member by the next run and then removed.',
                )
            "
        />

        <Form
            v-bind="DebitController.store.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <DebitFormFields
                :members="members"
                :today="today"
                :errors="errors"
            />

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="save-debit-button">
                    {{ $t('Save') }}
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="index()">{{ $t('Cancel') }}</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
