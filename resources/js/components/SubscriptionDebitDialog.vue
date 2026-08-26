<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { debit } from '@/routes/subscriptions';
import type { DebitableSubscription } from '@/types';

const props = defineProps<{
    debitable: DebitableSubscription[];
    /** Fees left out because they are 0 €; only used to explain the gap. */
    freeCount: number;
    sepaDate: string;
}>();

const open = defineModel<boolean>('open', { required: true });

const page = usePage();

const selected = ref<number[]>([]);
const executionDate = ref(props.sepaDate);
const collecting = ref(false);

// Every opening starts from a clean slate: a leftover tick from a dialog the
// user backed out of must not collect anything on the next run.
watch(open, (isOpen) => {
    if (isOpen) {
        selected.value = [];
        executionDate.value = props.sepaDate;
    }
});

const allSelected = computed(
    () =>
        props.debitable.length > 0 &&
        selected.value.length === props.debitable.length,
);

const someSelected = computed(
    () => selected.value.length > 0 && !allSelected.value,
);

const toggle = (id: number, checked: boolean) => {
    selected.value = checked
        ? [...selected.value, id]
        : selected.value.filter((selectedId) => selectedId !== id);
};

const toggleAll = (checked: boolean | 'indeterminate') => {
    selected.value =
        checked === true ? props.debitable.map(({ id }) => id) : [];
};

const collect = () => {
    router.post(
        debit.url(),
        { subscriptions: selected.value, date: executionDate.value },
        {
            onStart: () => (collecting.value = true),
            onFinish: () => (collecting.value = false),
        },
    );
};

const title = trans('Collect fees');
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader class="space-y-3">
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Builds one SEPA file for the members who pay the selected fees by direct debit. Everybody else is listed so you can bill them by hand.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="debitable.length === 0" class="text-sm">
                {{ $t('No subscription can be collected by direct debit.') }}
            </div>

            <div v-else class="flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <div
                        class="flex items-center gap-3 border-b pb-2 text-sm font-medium"
                    >
                        <Checkbox
                            id="select_all_subscriptions"
                            :model-value="
                                allSelected
                                    ? true
                                    : someSelected
                                      ? 'indeterminate'
                                      : false
                            "
                            @update:model-value="toggleAll"
                        />
                        <Label
                            for="select_all_subscriptions"
                            class="font-medium"
                        >
                            {{ $t('Select all') }}
                        </Label>
                        <span
                            class="ml-auto text-xs font-normal text-muted-foreground"
                        >
                            {{
                                $t(':selected of :total selected', {
                                    selected: String(selected.length),
                                    total: String(debitable.length),
                                })
                            }}
                        </span>
                    </div>

                    <div class="-mx-1 max-h-64 overflow-y-auto px-1">
                        <div
                            v-for="subscription in debitable"
                            :key="subscription.id"
                            class="flex items-center gap-3 py-2"
                        >
                            <Checkbox
                                :id="`subscription_${subscription.id}`"
                                :model-value="
                                    selected.includes(subscription.id)
                                "
                                @update:model-value="
                                    (checked) =>
                                        toggle(
                                            subscription.id,
                                            checked === true,
                                        )
                                "
                            />
                            <Label
                                :for="`subscription_${subscription.id}`"
                                class="flex flex-1 items-center justify-between gap-4 font-normal"
                            >
                                <span>{{ subscription.name }}</span>
                                <span
                                    class="text-muted-foreground tabular-nums"
                                >
                                    {{ subscription.amount_label }}
                                </span>
                            </Label>
                        </div>
                    </div>

                    <p
                        v-if="freeCount > 0"
                        class="border-t pt-2 text-xs text-muted-foreground"
                    >
                        {{
                            $t(
                                'Subscriptions of 0 € are not listed, there is nothing to collect from them.',
                            )
                        }}
                    </p>
                    <InputError :message="page.props.errors.subscriptions" />
                </div>

                <div class="grid gap-2">
                    <Label for="execution_date">
                        {{ $t('Execution date') }}
                    </Label>
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
            </div>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary" type="button">
                        {{ $t('Cancel') }}
                    </Button>
                </DialogClose>
                <Button
                    :disabled="collecting || selected.length === 0"
                    data-test="confirm-collect-fees-button"
                    @click="collect"
                >
                    {{ title }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
