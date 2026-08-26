<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { SelectOption } from '@/types';

const props = defineProps<{
    title: string;
    /** Wayfinder form props for the store or update endpoint. */
    action: Record<string, unknown>;
    /** Omitted for memberships: the club is implicit, there is nothing to pick. */
    options?: SelectOption[];
    optionName?: string;
    optionLabel?: string;
    selected?: number | null;
    memo?: string | null;
    /** Changes when a different row is edited, so the form remounts. */
    formKey: string;
}>();

const open = defineModel<boolean>('open', { required: true });

// reka-ui works on strings; the columns are integers.
const selectedId = ref(
    props.selected != null ? String(props.selected) : undefined,
);

// One dialog instance serves add and every row, so the picker has to be reset
// whenever the dialog is pointed at something else.
watch(
    () => props.formKey,
    () => {
        selectedId.value =
            props.selected != null ? String(props.selected) : undefined;
    },
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <Form
                :key="formKey"
                v-bind="action"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <DialogHeader>
                    <DialogTitle>{{ title }}</DialogTitle>
                </DialogHeader>

                <div class="mt-4 grid gap-4">
                    <div v-if="options && optionName" class="grid gap-2">
                        <Label :for="optionName">{{ optionLabel }}</Label>
                        <Select v-model="selectedId">
                            <SelectTrigger :id="optionName" class="w-full">
                                <SelectValue
                                    :placeholder="$t('Please choose')"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in options"
                                    :key="option.id"
                                    :value="String(option.id)"
                                >
                                    {{ option.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <input
                            type="hidden"
                            :name="optionName"
                            :value="selectedId"
                        />
                        <InputError :message="errors[optionName]" />
                    </div>

                    <!-- The shape-specific fields: a from/to range, a single
                    date, or nothing at all for a subscription. -->
                    <slot :errors="errors" />

                    <div class="grid gap-2">
                        <Label for="memo">{{ $t('Memo') }}</Label>
                        <Input
                            id="memo"
                            name="memo"
                            :default-value="memo ?? ''"
                            autocomplete="off"
                        />
                        <InputError :message="errors.memo" />
                    </div>
                </div>

                <DialogFooter class="mt-6 gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" type="button">
                            {{ $t('Cancel') }}
                        </Button>
                    </DialogClose>
                    <Button
                        variant="outline"
                        :disabled="processing"
                        data-test="save-relation-button"
                    >
                        {{ $t('Save') }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
