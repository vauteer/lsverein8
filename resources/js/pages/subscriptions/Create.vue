<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import SubscriptionController from '@/actions/App/Http/Controllers/SubscriptionController';
import Heading from '@/components/Heading.vue';
import SubscriptionFormFields from '@/components/SubscriptionFormFields.vue';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/subscriptions';
import type { BreadcrumbItem } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: trans('Subscriptions'),
                href: index(),
            } satisfies BreadcrumbItem,
            {
                title: trans('New subscription'),
                href: create(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('New subscription')" />

    <div class="mx-auto w-full max-w-2xl p-4">
        <Heading
            :title="$t('New subscription')"
            :description="
                $t('Members can be given this subscription once it exists.')
            "
        />

        <Form
            v-bind="SubscriptionController.store.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <SubscriptionFormFields :errors="errors" />

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="save-subscription-button"
                >
                    {{ $t('Save') }}
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="index()">{{ $t('Cancel') }}</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
