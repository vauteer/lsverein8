<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
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
import type { MemberRelationRow } from '@/types';

defineProps<{
    title: string;
    rows: MemberRelationRow[];
    /** No add/edit/remove for a read-only account; the list still shows. */
    modifiable: boolean;
    /** Wayfinder form props for this row's destroy endpoint. */
    destroy: (row: number) => Record<string, unknown>;
    /** Shown instead of the list when the club has nothing recorded. */
    empty?: string;
}>();

const emit = defineEmits<{
    add: [];
    edit: [row: MemberRelationRow];
}>();

const removing = ref<MemberRelationRow | null>(null);
</script>

<template>
    <section class="flex flex-col gap-2">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-medium">{{ title }}</h2>
            <Button
                v-if="modifiable"
                variant="ghost"
                size="icon"
                :aria-label="$t('Add to :section', { section: title })"
                :data-test="`add-relation-button`"
                @click="emit('add')"
            >
                <Plus class="size-4" />
            </Button>
        </div>

        <p v-if="rows.length === 0" class="text-sm text-muted-foreground">
            {{ empty ?? $t('Nothing recorded.') }}
        </p>

        <ul v-else class="flex flex-col gap-1 text-sm">
            <li
                v-for="row in rows"
                :key="row.id"
                class="flex flex-wrap items-center justify-between gap-x-4 rounded-lg border border-sidebar-border/70 px-3 py-2 dark:border-sidebar-border"
            >
                <span class="font-medium">{{ row.name }}</span>
                <!-- Memo first: it says what the entry is about, the dates only
                say when. -->
                <span class="ml-auto text-muted-foreground">
                    <template v-if="row.memo">{{ row.memo }} · </template>
                    <span class="tabular-nums">{{ row.detail }}</span>
                </span>
                <span v-if="modifiable" class="flex gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        :aria-label="$t('Edit :name', { name: row.name })"
                        @click="emit('edit', row)"
                    >
                        <Pencil class="size-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        :aria-label="$t('Remove :name', { name: row.name })"
                        @click="removing = row"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </span>
            </li>
        </ul>

        <!-- One confirmation for the whole section rather than one per row:
        the row being removed is what the dialog is pointed at. -->
        <Dialog
            :open="removing !== null"
            @update:open="(open) => !open && (removing = null)"
        >
            <DialogContent v-if="removing">
                <Form
                    v-bind="destroy(removing.id)"
                    v-slot="{ processing }"
                    @success="removing = null"
                >
                    <DialogHeader class="space-y-3">
                        <DialogTitle>
                            {{ $t('Remove :name?', { name: removing.name }) }}
                        </DialogTitle>
                        <DialogDescription>
                            {{
                                $t(
                                    'Only this entry is removed. :name itself is kept.',
                                    { name: removing.name },
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
                            data-test="confirm-remove-relation-button"
                        >
                            {{ $t('Remove') }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </section>
</template>
