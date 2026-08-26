<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import ItemController from '@/actions/App/Http/Controllers/ItemController';
import Heading from '@/components/Heading.vue';
import ItemFormFields from '@/components/ItemFormFields.vue';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/items';
import type { BreadcrumbItem } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: trans('Inventory'),
                href: index(),
            } satisfies BreadcrumbItem,
            {
                title: trans('New item'),
                href: create(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('New item')" />

    <div class="mx-auto w-full max-w-2xl p-4">
        <Heading
            :title="$t('New item')"
            :description="$t('Members can be issued this item once it exists.')"
        />

        <Form
            v-bind="ItemController.store.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <ItemFormFields :errors="errors" />

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="save-item-button">
                    {{ $t('Save') }}
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="index()">{{ $t('Cancel') }}</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
