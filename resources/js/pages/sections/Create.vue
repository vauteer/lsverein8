<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import SectionController from '@/actions/App/Http/Controllers/SectionController';
import Heading from '@/components/Heading.vue';
import SectionFormFields from '@/components/SectionFormFields.vue';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/sections';
import type { BreadcrumbItem, SelectOption } from '@/types';

defineProps<{
    blsvSections: SelectOption[] | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: trans('Sections'),
                href: index(),
            } satisfies BreadcrumbItem,
            {
                title: trans('New section'),
                href: create(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('New section')" />

    <div class="mx-auto w-full max-w-2xl p-4">
        <Heading
            :title="$t('New section')"
            :description="
                $t('Members can be assigned to this section once it exists.')
            "
        />

        <Form
            v-bind="SectionController.store.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <SectionFormFields :blsv-sections="blsvSections" :errors="errors" />

            <div class="flex items-center gap-4">
                <Button
                    variant="outline"
                    :disabled="processing"
                    data-test="save-section-button"
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
