<script setup lang="ts">
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { SectionFormData, SelectOption } from '@/types';

const props = defineProps<{
    section?: SectionFormData | null;
    /** Null for clubs that are not a BLSV member; the field is then hidden. */
    blsvSections: SelectOption[] | null;
    errors: Record<string, string>;
}>();

/**
 * reka-ui cannot hold an empty item value, so "no assignment" is carried as a
 * sentinel and translated back to an empty string in the submitted input,
 * which Laravel converts to null.
 */
const NONE = 'none';

const blsvId = ref(
    props.section?.blsv_id == null ? NONE : String(props.section.blsv_id),
);
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-2">
            <Label for="name">{{ $t('Name') }}</Label>
            <Input
                id="name"
                name="name"
                :default-value="section?.name"
                required
                autofocus
                autocomplete="off"
                :placeholder="$t('Section name')"
            />
            <InputError :message="errors.name" />
        </div>

        <div v-if="blsvSections" class="grid gap-2">
            <Label for="blsv_id">{{ $t('BLSV section') }}</Label>
            <Select v-model="blsvId">
                <SelectTrigger id="blsv_id" class="w-full sm:max-w-xs">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="NONE">{{ $t('(none)') }}</SelectItem>
                    <SelectItem
                        v-for="blsvSection in blsvSections"
                        :key="blsvSection.id"
                        :value="String(blsvSection.id)"
                    >
                        {{ blsvSection.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <input
                type="hidden"
                name="blsv_id"
                :value="blsvId === NONE ? '' : blsvId"
            />
            <p class="text-sm text-muted-foreground">
                {{
                    $t(
                        'Only sections with a BLSV number are reported in the annual membership report.',
                    )
                }}
            </p>
            <InputError :message="errors.blsv_id" />
        </div>
    </div>
</template>
