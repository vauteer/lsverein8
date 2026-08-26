<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import EventController from '@/actions/App/Http/Controllers/EventController';
import EventFormFields from '@/components/EventFormFields.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/events';
import type { BreadcrumbItem } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: trans('Events'),
                href: index(),
            } satisfies BreadcrumbItem,
            {
                title: trans('New event'),
                href: create(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('New event')" />

    <div class="mx-auto w-full max-w-2xl p-4">
        <Heading
            :title="$t('New event')"
            :description="$t('Members can be given this event once it exists.')"
        />

        <Form
            v-bind="EventController.store.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <EventFormFields :errors="errors" />

            <div class="flex items-center gap-4">
                <Button
                    variant="outline"
                    :disabled="processing"
                    data-test="save-event-button"
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
