<script setup lang="ts">
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { ClubFormData, SelectOption } from '@/types';

const props = defineProps<{
    club?: ClubFormData | null;
    displayStyles: SelectOption[];
    languages: SelectOption[];
    errors: Record<string, string>;
}>();

const display = ref(String(props.club?.display ?? props.displayStyles[0].id));
const locale = ref(props.club?.locale ?? props.languages[0].id);
const blsvMember = ref(props.club?.blsv_member ?? false);
const useItems = ref(props.club?.use_items ?? false);
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-2">
            <Label for="name">{{ $t('Name') }}</Label>
            <Input
                id="name"
                name="name"
                :default-value="club?.name"
                required
                autofocus
                autocomplete="off"
            />
            <InputError :message="errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="street">{{ $t('Street') }}</Label>
            <Input
                id="street"
                name="street"
                :default-value="club?.street"
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
                    :default-value="club?.zipcode"
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
                    :default-value="club?.city"
                    required
                    autocomplete="off"
                />
                <InputError :message="errors.city" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="bank">{{ $t('Bank') }}</Label>
            <Input
                id="bank"
                name="bank"
                :default-value="club?.bank"
                required
                autocomplete="off"
            />
            <InputError :message="errors.bank" />
        </div>

        <div class="grid gap-2">
            <Label for="account_owner">{{ $t('Account owner') }}</Label>
            <Input
                id="account_owner"
                name="account_owner"
                :default-value="club?.account_owner"
                required
                autocomplete="off"
            />
            <InputError :message="errors.account_owner" />
        </div>

        <div class="grid gap-4 sm:grid-cols-[2fr_1fr]">
            <div class="grid gap-2">
                <Label for="iban">{{ $t('IBAN') }}</Label>
                <Input
                    id="iban"
                    name="iban"
                    :default-value="club?.iban"
                    required
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
                    :default-value="club?.bic"
                    required
                    autocomplete="off"
                    class="font-mono"
                />
                <InputError :message="errors.bic" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="sepa">{{ $t('SEPA creditor identifier') }}</Label>
                <Input
                    id="sepa"
                    name="sepa"
                    :default-value="club?.sepa ?? ''"
                    autocomplete="off"
                    class="font-mono"
                />
                <InputError :message="errors.sepa" />
            </div>
            <div class="grid gap-2">
                <Label for="sepa_date">{{ $t('SEPA mandate date') }}</Label>
                <Input
                    id="sepa_date"
                    name="sepa_date"
                    type="date"
                    :default-value="club?.sepa_date ?? ''"
                />
                <InputError :message="errors.sepa_date" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="honor_years">
                {{ $t('Honour after years of membership') }}
            </Label>
            <Input
                id="honor_years"
                name="honor_years"
                :default-value="club?.honor_years ?? ''"
                placeholder="25,40,50"
                autocomplete="off"
            />
            <p class="text-sm text-muted-foreground">
                {{
                    $t(
                        'Comma separated. A member reaching one of these membership years is listed as due for an honour.',
                    )
                }}
            </p>
            <InputError :message="errors.honor_years" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="display">{{ $t('Display') }}</Label>
                <Select v-model="display">
                    <SelectTrigger id="display" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="style in displayStyles"
                            :key="style.id"
                            :value="String(style.id)"
                        >
                            {{ style.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <input type="hidden" name="display" :value="display" />
                <InputError :message="errors.display" />
            </div>
            <div class="grid gap-2">
                <Label for="locale">{{ $t('Language') }}</Label>
                <Select v-model="locale">
                    <SelectTrigger id="locale" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="language in languages"
                            :key="language.id"
                            :value="String(language.id)"
                        >
                            {{ language.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <input type="hidden" name="locale" :value="locale" />
                <InputError :message="errors.locale" />
            </div>
        </div>

        <div class="grid gap-3">
            <div class="flex items-center gap-2">
                <Checkbox id="blsv_member" v-model="blsvMember" />
                <Label for="blsv_member" class="font-normal">
                    {{ $t('BLSV member') }}
                </Label>
            </div>
            <input
                type="hidden"
                name="blsv_member"
                :value="blsvMember ? '1' : '0'"
            />
            <InputError :message="errors.blsv_member" />

            <div class="flex items-center gap-2">
                <Checkbox id="use_items" v-model="useItems" />
                <Label for="use_items" class="font-normal">
                    {{ $t('Use inventory') }}
                </Label>
            </div>
            <input
                type="hidden"
                name="use_items"
                :value="useItems ? '1' : '0'"
            />
            <InputError :message="errors.use_items" />
        </div>
    </div>
</template>
