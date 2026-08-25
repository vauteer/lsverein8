<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import Heading from '@/components/Heading.vue';
import RoleFormFields from '@/components/RoleFormFields.vue';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/roles';
import type { BreadcrumbItem } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: trans('Roles'),
                href: index(),
            } satisfies BreadcrumbItem,
            {
                title: trans('New role'),
                href: create(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('New role')" />

    <div class="mx-auto w-full max-w-2xl p-4">
        <Heading
            :title="$t('New role')"
            :description="$t('Members can be given this role once it exists.')"
        />

        <Form
            v-bind="RoleController.store.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <RoleFormFields :errors="errors" />

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="save-role-button">
                    {{ $t('Save') }}
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="index()">{{ $t('Cancel') }}</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
