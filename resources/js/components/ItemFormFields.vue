<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ItemFormData } from '@/types';

defineProps<{
    item?: ItemFormData | null;
    errors: Record<string, string>;
}>();
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-2">
            <Label for="name">{{ $t('Name') }}</Label>
            <Input
                id="name"
                name="name"
                :default-value="item?.name"
                required
                autofocus
                autocomplete="off"
                :placeholder="$t('Item name')"
            />
            <InputError :message="errors.name" />
        </div>
    </div>
</template>
