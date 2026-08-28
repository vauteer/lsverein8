<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ArchiveRestore, HardDriveDownload, Plus, Trash2 } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import BackupController from '@/actions/App/Http/Controllers/BackupController';
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
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { index } from '@/routes/backups';
import type { BackupResource, BreadcrumbItem } from '@/types';

defineProps<{
    backups: BackupResource[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: trans('Backups'),
                href: index(),
            } satisfies BreadcrumbItem,
        ],
    },
});

const backupToRestore = ref<BackupResource | null>(null);
const backupToDelete = ref<BackupResource | null>(null);
</script>

<template>
    <Head :title="$t('Backups')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                :title="$t('Backups')"
                :description="$t('Create and restore database backups')"
            />
            <Form
                v-bind="BackupController.store.form()"
                :options="{ preserveScroll: true }"
                v-slot="{ processing }"
                class="shrink-0"
            >
                <Button
                    variant="outline"
                    :disabled="processing"
                    data-test="create-backup-button"
                >
                    <Plus class="size-4" />
                    {{ processing ? $t('Creating …') : $t('New backup') }}
                </Button>
            </Form>
        </div>

        <div
            class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <Table>
                <TableHeader>
                    <TableRow>
                        <!-- Labelled, not converted: the timestamp is UTC
                             like everything the application stores, and the
                             filename says so too. -->
                        <TableHead>{{ $t('Date (UTC)') }}</TableHead>
                        <TableHead class="hidden md:table-cell">{{
                            $t('Age')
                        }}</TableHead>
                        <TableHead>{{ $t('Size') }}</TableHead>
                        <TableHead class="hidden text-right md:table-cell">{{
                            $t('Actions')
                        }}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="backup in backups" :key="backup.id">
                        <TableCell class="font-medium tabular-nums">
                            {{ backup.date }}
                        </TableCell>
                        <TableCell
                            class="hidden text-muted-foreground md:table-cell"
                        >
                            {{ backup.age }}
                        </TableCell>
                        <TableCell class="tabular-nums">
                            {{ backup.size }}
                        </TableCell>
                        <TableCell class="hidden md:table-cell">
                            <div class="flex justify-end gap-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            as-child
                                        >
                                            <a
                                                :href="
                                                    BackupController.download.url(
                                                        backup.filename,
                                                    )
                                                "
                                                :aria-label="
                                                    $t(
                                                        'Download backup from :date',
                                                        {
                                                            date: backup.date,
                                                        },
                                                    )
                                                "
                                            >
                                                <HardDriveDownload
                                                    class="size-4"
                                                />
                                            </a>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>{{
                                        $t('Download')
                                    }}</TooltipContent>
                                </Tooltip>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            :aria-label="
                                                $t(
                                                    'Restore backup from :date',
                                                    {
                                                        date: backup.date,
                                                    },
                                                )
                                            "
                                            @click="backupToRestore = backup"
                                        >
                                            <ArchiveRestore
                                                class="size-4 text-destructive"
                                            />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>{{
                                        $t('Restore')
                                    }}</TooltipContent>
                                </Tooltip>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            :aria-label="
                                                $t('Delete backup from :date', {
                                                    date: backup.date,
                                                })
                                            "
                                            @click="backupToDelete = backup"
                                        >
                                            <Trash2
                                                class="size-4 text-destructive"
                                            />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>{{
                                        $t('Delete')
                                    }}</TooltipContent>
                                </Tooltip>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableEmpty v-if="backups.length === 0" :colspan="4">
                        {{ $t('No backups yet.') }}
                    </TableEmpty>
                </TableBody>
            </Table>
        </div>

        <Dialog
            :open="backupToRestore !== null"
            @update:open="(open) => !open && (backupToRestore = null)"
        >
            <DialogContent>
                <Form
                    v-if="backupToRestore"
                    v-bind="
                        BackupController.restore.form(backupToRestore.filename)
                    "
                    :options="{ preserveScroll: true }"
                    @success="backupToRestore = null"
                    v-slot="{ processing }"
                >
                    <DialogHeader class="space-y-3">
                        <DialogTitle>{{
                            $t('Restore backup from :date?', {
                                date: backupToRestore.date,
                            })
                        }}</DialogTitle>
                        <DialogDescription>
                            {{
                                $t(
                                    'This will replace the entire database with the state from :date. All changes made since then will be lost. A safety backup of the current state is created automatically first.',
                                    { date: backupToRestore.date },
                                )
                            }}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="mt-6 gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary" type="button">{{
                                $t('Cancel')
                            }}</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            :disabled="processing"
                            data-test="confirm-restore-backup-button"
                        >
                            {{ processing ? $t('Restoring …') : $t('Restore') }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="backupToDelete !== null"
            @update:open="(open) => !open && (backupToDelete = null)"
        >
            <DialogContent>
                <Form
                    v-if="backupToDelete"
                    v-bind="
                        BackupController.destroy.form(backupToDelete.filename)
                    "
                    :options="{ preserveScroll: true }"
                    @success="backupToDelete = null"
                    v-slot="{ processing }"
                >
                    <DialogHeader class="space-y-3">
                        <DialogTitle>{{
                            $t('Delete backup from :date?', {
                                date: backupToDelete.date,
                            })
                        }}</DialogTitle>
                        <DialogDescription>
                            {{
                                $t(
                                    'This will permanently delete the backup file ":filename". This action cannot be undone.',
                                    { filename: backupToDelete.filename },
                                )
                            }}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="mt-6 gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary" type="button">{{
                                $t('Cancel')
                            }}</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            :disabled="processing"
                            data-test="confirm-delete-backup-button"
                        >
                            {{ $t('Delete') }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
