<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { RoleFormData } from '@/types';

defineProps<{
    role?: RoleFormData | null;
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
                :default-value="role?.name"
                required
                autofocus
                autocomplete="off"
                :placeholder="$t('Role name')"
            />
            <InputError :message="errors.name" />
        </div>
    </div>
</template>
