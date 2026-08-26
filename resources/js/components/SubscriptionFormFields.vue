<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { SubscriptionFormData } from '@/types';

defineProps<{
    subscription?: SubscriptionFormData | null;
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
                :default-value="subscription?.name"
                required
                autofocus
                autocomplete="off"
                :placeholder="$t('Subscription name')"
            />
            <InputError :message="errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="amount">{{ $t('Amount') }}</Label>
            <Input
                id="amount"
                name="amount"
                type="number"
                step="0.01"
                min="0"
                :default-value="subscription?.amount ?? 0"
                required
                class="w-full sm:max-w-[12rem]"
            />
            <InputError :message="errors.amount" />
        </div>

        <div class="grid gap-2">
            <Label for="transfer_text">{{ $t('Transfer text') }}</Label>
            <Input
                id="transfer_text"
                name="transfer_text"
                :default-value="subscription?.transfer_text ?? ''"
                required
                autocomplete="off"
                :placeholder="
                    $t('Variables: <AJ> year, <VN> first name, <NN> surname')
                "
            />
            <p class="text-sm text-muted-foreground">
                {{
                    $t(
                        'Appears on the member’s bank statement. Umlauts and other special characters are not allowed, because the SEPA format cannot carry them.',
                    )
                }}
            </p>
            <InputError :message="errors.transfer_text" />
        </div>

        <div class="grid gap-2">
            <Label for="memo">{{ $t('Memo') }}</Label>
            <Input
                id="memo"
                name="memo"
                :default-value="subscription?.memo ?? ''"
                autocomplete="off"
            />
            <InputError :message="errors.memo" />
        </div>
    </div>
</template>
