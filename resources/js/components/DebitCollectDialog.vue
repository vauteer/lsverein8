<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { collect } from '@/routes/debits';

const props = defineProps<{
    sepaDate: string;
}>();

const open = defineModel<boolean>('open', { required: true });

const page = usePage();

const executionDate = ref(props.sepaDate);
const collecting = ref(false);

// Every opening starts from the suggested date again, so a date the user
// typed and then backed out of does not decide the next run.
watch(open, (isOpen) => {
    if (isOpen) {
        executionDate.value = props.sepaDate;
    }
});

const run = () => {
    router.post(
        collect.url(),
        { date: executionDate.value },
        {
            onStart: () => (collecting.value = true),
            onFinish: () => (collecting.value = false),
        },
    );
};

const title = trans('Collect debits');
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader class="space-y-3">
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Builds one SEPA file from every debit due on or before this date, and removes those debits.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-2">
                <Label for="execution_date">{{ $t('Execution date') }}</Label>
                <Input
                    id="execution_date"
                    v-model="executionDate"
                    type="date"
                    class="w-full sm:w-44"
                />
                <p class="text-xs text-muted-foreground">
                    {{
                        $t(
                            'A direct debit has to reach the bank a few days before this date.',
                        )
                    }}
                </p>
                <InputError :message="page.props.errors.date" />
            </div>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary" type="button">
                        {{ $t('Cancel') }}
                    </Button>
                </DialogClose>
                <Button
                    :disabled="collecting"
                    data-test="confirm-collect-debits-button"
                    @click="run"
                >
                    {{ title }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
