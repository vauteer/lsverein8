<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import ClubController from '@/actions/App/Http/Controllers/ClubController';
import ClubFormFields from '@/components/ClubFormFields.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/clubs';
import type { BreadcrumbItem, SelectOption } from '@/types';

defineProps<{
    identityDisplays: SelectOption[];
    languages: SelectOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: trans('Clubs'), href: index() } satisfies BreadcrumbItem,
            {
                title: trans('New club'),
                href: create(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('New club')" />

    <div class="mx-auto w-full max-w-2xl p-4">
        <Heading
            :title="$t('New club')"
            :description="
                $t('You are added to the new club as its administrator.')
            "
        />

        <Form
            v-bind="ClubController.store.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <ClubFormFields
                :identity-displays="identityDisplays"
                :languages="languages"
                :errors="errors"
            />

            <div class="flex items-center gap-4">
                <Button
                    variant="outline"
                    :disabled="processing"
                    data-test="save-club-button"
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
