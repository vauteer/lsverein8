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
import type { AccountSource, MemberFormData, SelectOption } from '@/types';

const props = defineProps<{
    member?: MemberFormData | null;
    genders: SelectOption[];
    accountSources: AccountSource[];
    errors: Record<string, string>;
}>();

const gender = ref(String(props.member?.gender ?? props.genders[0].id));

// Bound so the account picker below can follow it as it is typed.
const surname = ref(props.member?.surname ?? '');

// The bank fields are bound rather than left uncontrolled, because copying
// from another member has to write into them.
const bank = ref(props.member?.bank ?? '');
const accountOwner = ref(props.member?.account_owner ?? '');
const iban = ref(props.member?.iban ?? '');
const bic = ref(props.member?.bic ?? '');

// Narrowed to the surname being entered. The club's 218 account holders are
// no use as one list; by surname it is a median of one and seven at the most.
// Measured on production: 35 of the 38 current members without an account
// share a surname with one that has, so this hits nearly every case it is
// for. Exactly two of 180 accounts are shared across surnames — those are
// typed by hand, which is what happened before this existed.
const matchingSources = computed(() => {
    const wanted = surname.value.trim().toLowerCase();

    return wanted === ''
        ? []
        : props.accountSources.filter(
              (source) => source.surname.toLowerCase() === wanted,
          );
});

// A family shares one account, so the same four fields would otherwise be
// typed again for every child. All four at once, never a subset: the fields
// are all-or-nothing on the server, and half a copied account is exactly the
// broken record that rule exists to prevent.
function copyAccountFrom(id: string) {
    const source = matchingSources.value.find((m) => String(m.id) === id);

    if (!source) {
        return;
    }

    bank.value = source.bank ?? '';
    accountOwner.value = source.account_owner ?? '';
    iban.value = source.iban ?? '';
    bic.value = source.bic ?? '';
}
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="surname">{{ $t('Surname') }}</Label>
                <Input
                    id="surname"
                    name="surname"
                    v-model="surname"
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
                <Label for="gender">{{ $t('Gender') }}</Label>
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

        <div
            class="grid gap-6 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <p class="text-sm text-muted-foreground">
                {{
                    $t(
                        'Bank details make the member a direct debit payer. Leave them empty and the fees are billed by hand. Fill in all four or none; the account owner may differ from the member.',
                    )
                }}
            </p>

            <div v-if="matchingSources.length > 0" class="grid gap-2">
                <Label for="copy_account_from">
                    {{ $t('Copy bank details from') }}
                </Label>
                <Select
                    @update:model-value="(id) => copyAccountFrom(String(id))"
                >
                    <SelectTrigger
                        id="copy_account_from"
                        class="w-full sm:max-w-md"
                    >
                        <SelectValue :placeholder="$t('Pick a member')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="source in matchingSources"
                            :key="source.id"
                            :value="String(source.id)"
                        >
                            {{ source.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p class="text-xs text-muted-foreground">
                    {{
                        $t(
                            'Members with the same surname. Overwrites all four fields below.',
                        )
                    }}
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="bank">{{ $t('Bank') }}</Label>
                    <Input
                        id="bank"
                        name="bank"
                        v-model="bank"
                        autocomplete="off"
                    />
                    <InputError :message="errors.bank" />
                </div>
                <div class="grid gap-2">
                    <Label for="account_owner">{{ $t('Account owner') }}</Label>
                    <Input
                        id="account_owner"
                        name="account_owner"
                        v-model="accountOwner"
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
                        v-model="iban"
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
                        v-model="bic"
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
