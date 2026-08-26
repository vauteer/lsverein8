<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import UserController from '@/actions/App/Http/Controllers/UserController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import UserFormFields from '@/components/UserFormFields.vue';
import { create, index } from '@/routes/users';
import type { BreadcrumbItem, SelectOption } from '@/types';

defineProps<{
    roles: SelectOption[];
    locales: SelectOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: trans('Users'),
                href: index(),
            } satisfies BreadcrumbItem,
            {
                title: trans('New user'),
                href: create(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('New user')" />

    <div class="mx-auto w-full max-w-2xl p-4">
        <Heading
            :title="$t('New user')"
            :description="
                $t(
                    'The new user is emailed a link to set their own password. If the email address already has an account, it is added to this club instead.',
                )
            "
        />

        <Form
            v-bind="UserController.store.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <UserFormFields
                :roles="roles"
                :locales="locales"
                :errors="errors"
                :details-required="false"
            />

            <div class="flex items-center gap-4">
                <Button
                    variant="outline"
                    :disabled="processing"
                    data-test="save-user-button"
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
