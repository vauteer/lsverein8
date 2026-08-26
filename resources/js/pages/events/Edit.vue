<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import EventController from '@/actions/App/Http/Controllers/EventController';
import EventFormFields from '@/components/EventFormFields.vue';
import Heading from '@/components/Heading.vue';
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
import { index } from '@/routes/events';
import type { BreadcrumbItem, EventFormData } from '@/types';

const props = defineProps<{
    event: EventFormData;
    deletable: boolean;
    backPage: number | null;
    backSearch: string | null;
}>();

const cancelHref = index({
    query: {
        page: props.backPage ?? undefined,
        search: props.backSearch ?? undefined,
    },
});

const confirmingDeletion = ref(false);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: trans('Events'),
                href: index(),
            } satisfies BreadcrumbItem,
            {
                title: trans('Edit event'),
                href: index(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('Edit :name', { name: event.name })" />

    <div class="mx-auto w-full max-w-2xl p-4">
        <Heading :title="$t('Edit event')" :description="event.name" />

        <Form
            v-bind="EventController.update.form(props.event.id)"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <EventFormFields :event="event" :errors="errors" />

            <div class="flex items-center justify-between">
                <Button
                    v-if="deletable"
                    type="button"
                    variant="destructive"
                    data-test="delete-event-button"
                    @click="confirmingDeletion = true"
                >
                    {{ $t('Delete') }}
                </Button>
                <span v-else />
                <div class="flex items-center gap-4">
                    <Button
                        variant="outline"
                        :disabled="processing"
                        data-test="save-event-button"
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
            :open="confirmingDeletion"
            @update:open="(open) => (confirmingDeletion = open)"
        >
            <DialogContent>
                <Form
                    v-bind="EventController.destroy.form(event.id)"
                    v-slot="{ processing }"
                >
                    <DialogHeader class="space-y-3">
                        <DialogTitle>
                            {{ $t('Delete :name?', { name: event.name }) }}
                        </DialogTitle>
                        <DialogDescription>
                            {{
                                $t(
                                    'No member has ever been given this event, so nothing else is affected. This action cannot be undone.',
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
                            data-test="confirm-delete-event-button"
                        >
                            {{ $t('Delete') }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
