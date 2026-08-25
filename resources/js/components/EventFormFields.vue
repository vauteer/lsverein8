<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { EventFormData } from '@/types';

defineProps<{
    event?: EventFormData | null;
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
                :default-value="event?.name"
                required
                autofocus
                autocomplete="off"
                :placeholder="$t('Event name')"
            />
            <InputError :message="errors.name" />
        </div>
    </div>
</template>
