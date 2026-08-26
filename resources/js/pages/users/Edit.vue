<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import UserController from '@/actions/App/Http/Controllers/UserController';
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
import UserFormFields from '@/components/UserFormFields.vue';
import { index } from '@/routes/users';
import type { BreadcrumbItem, SelectOption, UserFormData } from '@/types';

const props = defineProps<{
    user: UserFormData;
    roles: SelectOption[];
    locales: SelectOption[];
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
                title: trans('Users'),
                href: index(),
            } satisfies BreadcrumbItem,
            {
                title: trans('Edit user'),
                href: index(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('Edit :name', { name: user.name })" />

    <div class="mx-auto w-full max-w-2xl p-4">
        <Heading :title="$t('Edit user')" :description="user.name" />

        <Form
            v-bind="UserController.update.form(props.user.id)"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <UserFormFields
                :user="user"
                :roles="roles"
                :locales="locales"
                :errors="errors"
            />

            <div class="flex items-center justify-between">
                <Button
                    v-if="deletable"
                    type="button"
                    variant="destructive"
                    data-test="delete-user-button"
                    @click="confirmingDeletion = true"
                >
                    {{ $t('Delete') }}
                </Button>
                <span v-else />
                <div class="flex items-center gap-4">
                    <Button
                        variant="outline"
                        :disabled="processing"
                        data-test="save-user-button"
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
                    v-bind="UserController.destroy.form(user.id)"
                    v-slot="{ processing }"
                >
                    <DialogHeader class="space-y-3">
                        <DialogTitle>
                            {{ $t('Delete :name?', { name: user.name }) }}
                        </DialogTitle>
                        <DialogDescription>
                            {{
                                $t(
                                    "This will remove :name from this club. If they don't belong to any other club, their account will be deleted entirely. This action cannot be undone.",
                                    { name: user.name },
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
                            data-test="confirm-delete-user-button"
                        >
                            {{ $t('Delete') }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
