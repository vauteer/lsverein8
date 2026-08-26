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
import type { MemberFormData, SelectOption } from '@/types';

const props = defineProps<{
    member?: MemberFormData | null;
    genders: SelectOption[];
    paymentMethods: SelectOption[];
    errors: Record<string, string>;
}>();

const gender = ref(String(props.member?.gender ?? props.genders[0].id));
const paymentMethod = ref(
    String(props.member?.payment_method ?? props.paymentMethods[0].id),
);

// 'k' is PaymentMethod::Account — the only method the club collects by SEPA,
// and the only one the bank block is required for. The fields stay in the DOM
// when hidden would lose what was already typed, so they are only collapsed.
const collectsByAccount = computed(() => paymentMethod.value === 'k');
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="surname">{{ $t('Surname') }}</Label>
                <Input
                    id="surname"
                    name="surname"
                    :default-value="member?.surname"
                    required
                    autofocus
                    autocomplete="off"
                />
                <InputError :message="errors.surname" />
            </div>
            <div class="grid gap-2">
                <Label for="first_name">{{ $t('First name') }}</Label>
                <Input
                    id="first_name"
                    name="first_name"
                    :default-value="member?.first_name"
                    required
                    autocomplete="off"
                />
                <InputError :message="errors.first_name" />
            </div>
        </div>

        <div
            class="grid gap-4"
            :class="member ? 'sm:grid-cols-3' : 'sm:grid-cols-2'"
        >
            <div class="grid gap-2">
                <Label for="gender">{{ $t('Salutation') }}</Label>
                <Select v-model="gender">
                    <SelectTrigger id="gender" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in genders"
                            :key="option.id"
                            :value="String(option.id)"
                        >
                            {{ option.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <input type="hidden" name="gender" :value="gender" />
                <InputError :message="errors.gender" />
            </div>
            <div class="grid gap-2">
                <Label for="birthday">{{ $t('Date of birth') }}</Label>
                <Input
                    id="birthday"
                    name="birthday"
                    type="date"
                    :default-value="member?.birthday"
                    required
                />
                <InputError :message="errors.birthday" />
            </div>
            <!-- Only when editing: nobody is entered into the club dead, so
            the create form does not ask. MemberStoreRequest does not accept
            the field either. -->
            <div v-if="member" class="grid gap-2">
                <Label for="death_day">{{ $t('Date of death') }}</Label>
                <Input
                    id="death_day"
                    name="death_day"
                    type="date"
                    :default-value="member.death_day ?? ''"
                />
                <InputError :message="errors.death_day" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="street">{{ $t('Street') }}</Label>
            <Input
                id="street"
                name="street"
                :default-value="member?.street"
                required
                autocomplete="off"
            />
            <InputError :message="errors.street" />
        </div>

        <div class="grid gap-4 sm:grid-cols-[8rem_1fr]">
            <div class="grid gap-2">
                <Label for="zipcode">{{ $t('Postcode') }}</Label>
                <Input
                    id="zipcode"
                    name="zipcode"
                    :default-value="member?.zipcode"
                    required
                    autocomplete="off"
                />
                <InputError :message="errors.zipcode" />
            </div>
            <div class="grid gap-2">
                <Label for="city">{{ $t('City') }}</Label>
                <Input
                    id="city"
                    name="city"
                    :default-value="member?.city"
                    required
                    autocomplete="off"
                />
                <InputError :message="errors.city" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="email">{{ $t('Email') }}</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    :default-value="member?.email ?? ''"
                    autocomplete="off"
                />
                <InputError :message="errors.email" />
            </div>
            <div class="grid gap-2">
                <Label for="phone">{{ $t('Phone') }}</Label>
                <Input
                    id="phone"
                    name="phone"
                    :default-value="member?.phone ?? ''"
                    autocomplete="off"
                />
                <InputError :message="errors.phone" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="payment_method">{{ $t('Payment method') }}</Label>
            <Select v-model="paymentMethod">
                <SelectTrigger id="payment_method" class="w-full sm:max-w-xs">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="option in paymentMethods"
                        :key="option.id"
                        :value="String(option.id)"
                    >
                        {{ option.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <input type="hidden" name="payment_method" :value="paymentMethod" />
            <InputError :message="errors.payment_method" />
        </div>

        <div
            v-show="collectsByAccount"
            class="grid gap-6 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <p class="text-sm text-muted-foreground">
                {{
                    $t(
                        'Needed to collect from this member by direct debit. The account owner may differ from the member.',
                    )
                }}
            </p>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="bank">{{ $t('Bank') }}</Label>
                    <Input
                        id="bank"
                        name="bank"
                        :default-value="member?.bank ?? ''"
                        autocomplete="off"
                    />
                    <InputError :message="errors.bank" />
                </div>
                <div class="grid gap-2">
                    <Label for="account_owner">{{ $t('Account owner') }}</Label>
                    <Input
                        id="account_owner"
                        name="account_owner"
                        :default-value="member?.account_owner ?? ''"
                        autocomplete="off"
                    />
                    <InputError :message="errors.account_owner" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-[1fr_12rem]">
                <div class="grid gap-2">
                    <Label for="iban">{{ $t('IBAN') }}</Label>
                    <Input
                        id="iban"
                        name="iban"
                        :default-value="member?.iban ?? ''"
                        autocomplete="off"
                        class="font-mono"
                    />
                    <InputError :message="errors.iban" />
                </div>
                <div class="grid gap-2">
                    <Label for="bic">{{ $t('BIC') }}</Label>
                    <Input
                        id="bic"
                        name="bic"
                        :default-value="member?.bic ?? ''"
                        autocomplete="off"
                        class="font-mono"
                    />
                    <InputError :message="errors.bic" />
                </div>
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="memo">{{ $t('Memo') }}</Label>
            <Input
                id="memo"
                name="memo"
                :default-value="member?.memo ?? ''"
                autocomplete="off"
            />
            <InputError :message="errors.memo" />
        </div>
    </div>
</template>
