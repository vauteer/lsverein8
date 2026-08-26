<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import ItemController from '@/actions/App/Http/Controllers/ItemController';
import Heading from '@/components/Heading.vue';
import ItemFormFields from '@/components/ItemFormFields.vue';
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
import { index } from '@/routes/items';
import type { BreadcrumbItem, ItemFormData } from '@/types';

const props = defineProps<{
    item: ItemFormData;
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
                title: trans('Inventory'),
                href: index(),
            } satisfies BreadcrumbItem,
            {
                title: trans('Edit item'),
                href: index(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('Edit :name', { name: item.name })" />

    <div class="mx-auto w-full max-w-2xl p-4">
        <Heading :title="$t('Edit item')" :description="item.name" />

        <Form
            v-bind="ItemController.update.form(props.item.id)"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <ItemFormFields :item="item" :errors="errors" />

            <div class="flex items-center justify-between">
                <Button
                    v-if="deletable"
                    type="button"
                    variant="destructive"
                    data-test="delete-item-button"
                    @click="confirmingDeletion = true"
                >
                    {{ $t('Delete') }}
                </Button>
                <span v-else />
                <div class="flex items-center gap-4">
                    <Button :disabled="processing" data-test="save-item-button">
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
                    v-bind="ItemController.destroy.form(item.id)"
                    v-slot="{ processing }"
                >
                    <DialogHeader class="space-y-3">
                        <DialogTitle>
                            {{ $t('Delete :name?', { name: item.name }) }}
                        </DialogTitle>
                        <DialogDescription>
                            {{
                                $t(
                                    'This item has never been issued to a member, so nothing else is affected. This action cannot be undone.',
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
                            data-test="confirm-delete-item-button"
                        >
                            {{ $t('Delete') }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
