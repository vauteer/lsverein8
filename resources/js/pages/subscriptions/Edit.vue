<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import SubscriptionController from '@/actions/App/Http/Controllers/SubscriptionController';
import Heading from '@/components/Heading.vue';
import SubscriptionFormFields from '@/components/SubscriptionFormFields.vue';
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
import { index } from '@/routes/subscriptions';
import type { BreadcrumbItem, SubscriptionFormData } from '@/types';

const props = defineProps<{
    subscription: SubscriptionFormData;
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
                title: trans('Subscriptions'),
                href: index(),
            } satisfies BreadcrumbItem,
            {
                title: trans('Edit subscription'),
                href: index(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('Edit :name', { name: subscription.name })" />

    <div class="mx-auto w-full max-w-2xl p-4">
        <Heading
            :title="$t('Edit subscription')"
            :description="subscription.name"
        />

        <Form
            v-bind="SubscriptionController.update.form(props.subscription.id)"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <SubscriptionFormFields
                :subscription="subscription"
                :errors="errors"
            />

            <div class="flex items-center justify-between">
                <Button
                    v-if="deletable"
                    type="button"
                    variant="destructive"
                    data-test="delete-subscription-button"
                    @click="confirmingDeletion = true"
                >
                    {{ $t('Delete') }}
                </Button>
                <span v-else />
                <div class="flex items-center gap-4">
                    <Button
                        variant="outline"
                        :disabled="processing"
                        data-test="save-subscription-button"
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
                    v-bind="
                        SubscriptionController.destroy.form(subscription.id)
                    "
                    v-slot="{ processing }"
                >
                    <DialogHeader class="space-y-3">
                        <DialogTitle>
                            {{
                                $t('Delete :name?', { name: subscription.name })
                            }}
                        </DialogTitle>
                        <DialogDescription>
                            {{
                                $t(
                                    'No member holds this subscription, so nothing else is affected. This action cannot be undone.',
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
                            data-test="confirm-delete-subscription-button"
                        >
                            {{ $t('Delete') }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
