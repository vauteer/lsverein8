<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Download, TriangleAlert } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import ClubController from '@/actions/App/Http/Controllers/ClubController';
import ClubExportController from '@/actions/App/Http/Controllers/ClubExportController';
import ClubFormFields from '@/components/ClubFormFields.vue';
import Heading from '@/components/Heading.vue';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { dashboard } from '@/routes';
import { index } from '@/routes/clubs';
import type { BreadcrumbItem, ClubFormData, SelectOption } from '@/types';

const props = defineProps<{
    club: ClubFormData;
    displayStyles: SelectOption[];
    languages: SelectOption[];
    deletable: boolean;
    /** False for a club admin, who has no club list to return to. */
    listable: boolean;
    /** Only for a BLSV club, and only the one the viewer is working in. */
}>();

// Not an Inertia link: a file download, so a visit would leave the SPA
// looking for a component. Same reason as the SEPA and member exports.
const exportHref = ClubExportController.url(props.club.id);

const confirmingDeletion = ref(false);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: trans('Clubs'), href: index() } satisfies BreadcrumbItem,
            {
                title: trans('Edit club'),
                href: index(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('Edit :name', { name: club.name })" />

    <div class="mx-auto w-full max-w-2xl p-4">
        <Heading :title="$t('Edit club')" :description="club.name" />

        <Form
            v-bind="ClubController.update.form(props.club.id)"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <ClubFormFields
                :club="club"
                :display-styles="displayStyles"
                :languages="languages"
                :errors="errors"
            />

            <div class="flex items-center justify-between">
                <Button
                    v-if="deletable"
                    type="button"
                    variant="destructive"
                    data-test="delete-club-button"
                    @click="confirmingDeletion = true"
                >
                    {{ $t('Delete') }}
                </Button>
                <span v-else />
                <div class="flex items-center gap-4">
                    <Button
                        variant="outline"
                        :disabled="processing"
                        data-test="save-club-button"
                    >
                        {{ $t('Save') }}
                    </Button>
                    <Button variant="ghost" as-child>
                        <Link :href="listable ? index() : dashboard()">
                            {{ $t('Cancel') }}
                        </Link>
                    </Button>
                </div>
            </div>
        </Form>

        <section
            class="mt-8 flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <h2 class="text-sm font-medium">{{ $t('Data') }}</h2>
            <p class="text-sm text-muted-foreground">
                {{
                    $t(
                        'Every row belonging to this club as an SQL script: the club, its members and their whole history, plus the accounts that can reach it.',
                    )
                }}
            </p>
            <p
                class="flex items-start gap-2 text-sm text-muted-foreground"
                data-test="club-export-warning"
            >
                <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                <span>
                    {{
                        $t(
                            'The script empties each table before filling it, so import it into an empty database only — run against an installation holding other clubs, it deletes their data.',
                        )
                    }}
                </span>
            </p>
            <div>
                <a
                    :href="exportHref"
                    download
                    :class="buttonVariants({ variant: 'outline' })"
                    data-test="club-export-link"
                >
                    <Download class="size-4" />
                    {{ $t('Export club data') }}
                </a>
            </div>
        </section>

        <Dialog
            :open="confirmingDeletion"
            @update:open="(open) => (confirmingDeletion = open)"
        >
            <DialogContent>
                <Form
                    v-bind="ClubController.destroy.form(club.id)"
                    v-slot="{ processing }"
                >
                    <DialogHeader class="space-y-3">
                        <DialogTitle>
                            {{ $t('Delete :name?', { name: club.name }) }}
                        </DialogTitle>
                        <DialogDescription>
                            {{
                                $t(
                                    'This club has no members and no users left, so nothing else is affected. This action cannot be undone.',
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
                            data-test="confirm-delete-club-button"
                        >
                            {{ $t('Delete') }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
