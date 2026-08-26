<script setup lang="ts">
import { computed, ref } from 'vue';
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
import type { DebitableMember, DebitFormData } from '@/types';

const props = defineProps<{
    debit?: DebitFormData | null;
    members: DebitableMember[];
    /** Today, for a new debit's due date; the server's idea of it. */
    today?: string;
    errors: Record<string, string>;
}>();

// Select works on strings, the column is an integer.
const memberId = ref(props.debit ? String(props.debit.member_id) : undefined);

// A member who has lost their bank details since the debit was booked would
// otherwise leave the trigger blank and the form unsavable without silently
// rebooking the debit on somebody else.
const missingMember = computed(
    () =>
        props.debit !== null &&
        props.debit !== undefined &&
        !props.members.some(({ id }) => id === props.debit?.member_id),
);
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-2">
            <Label for="member_id">{{ $t('Member') }}</Label>
            <Select v-model="memberId">
                <SelectTrigger id="member_id" class="w-full">
                    <SelectValue :placeholder="$t('Pick a member')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-if="missingMember && debit"
                        :value="String(debit.member_id)"
                    >
                        {{ debit.member_name }}
                    </SelectItem>
                    <SelectItem
                        v-for="member in members"
                        :key="member.id"
                        :value="String(member.id)"
                    >
                        {{ member.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <input type="hidden" name="member_id" :value="memberId" />
            <p class="text-sm text-muted-foreground">
                {{
                    $t(
                        'Only members with a bank account on file can be debited.',
                    )
                }}
            </p>
            <InputError :message="errors.member_id" />
        </div>

        <div class="grid gap-2">
            <Label for="amount">{{ $t('Amount') }}</Label>
            <Input
                id="amount"
                name="amount"
                type="number"
                step="0.01"
                min="0.01"
                :default-value="debit?.amount"
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
                :default-value="debit?.transfer_text ?? ''"
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
            <Label for="due_at">{{ $t('Due on') }}</Label>
            <Input
                id="due_at"
                name="due_at"
                type="date"
                :default-value="debit?.due_at ?? today"
                required
                class="w-full sm:w-44"
            />
            <p class="text-sm text-muted-foreground">
                {{ $t('Collected from this date on.') }}
            </p>
            <InputError :message="errors.due_at" />
        </div>
    </div>
</template>
