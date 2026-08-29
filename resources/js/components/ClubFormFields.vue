<script setup lang="ts">
import { computed, ref, useTemplateRef } from 'vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
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
    identityDisplays: SelectOption[];
    languages: SelectOption[];
    errors: Record<string, string>;
}>();

const fileInput = useTemplateRef<HTMLInputElement>('fileInput');
const preview = ref<string | null>(null);
const removeLogo = ref(false);
const hasLogo = ref(props.club?.has_logo ?? false);

/** Chosen file wins, then "removed", otherwise whatever the server serves. */
const logoSrc = computed(
    () =>
        preview.value ??
        (removeLogo.value ? null : (props.club?.logo_url ?? null)),
);

function onLogoChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    removeLogo.value = false;
    hasLogo.value = true;
    preview.value = URL.createObjectURL(file);
}

function removeLogoFile() {
    removeLogo.value = true;
    hasLogo.value = false;
    preview.value = null;

    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

const identityDisplay = ref(
    String(props.club?.identity_display ?? props.identityDisplays[0].id),
);
const locale = ref(props.club?.locale ?? props.languages[0].id);
const blsvMember = ref(props.club?.blsv_member ?? false);
const useItems = ref(props.club?.use_items ?? false);
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-2">
            <Label>{{ $t('Logo') }}</Label>
            <div class="flex items-center gap-4">
                <Avatar class="size-16 rounded-lg">
                    <!-- contain, not cover: cropping a wordmark to a square
                    can cut the club name out of it. -->
                    <AvatarImage
                        v-if="logoSrc"
                        class="object-contain"
                        :src="logoSrc"
                        :alt="club?.name ?? ''"
                    />
                    <AvatarFallback
                        class="rounded-lg text-black dark:text-white"
                    >
                        {{ (club?.name ?? '?').charAt(0) }}
                    </AvatarFallback>
                </Avatar>
                <div class="flex flex-col items-start gap-2">
                    <input
                        ref="fileInput"
                        type="file"
                        name="logo"
                        accept="image/png,image/jpeg,image/webp,image/svg+xml"
                        class="text-sm text-muted-foreground file:mr-3 file:inline-flex file:h-7 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:text-sm file:font-medium file:text-secondary-foreground"
                        @change="onLogoChange"
                    />
                    <input
                        type="hidden"
                        name="remove_logo"
                        :value="removeLogo ? '1' : '0'"
                    />
                    <Button
                        v-if="hasLogo"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="removeLogoFile"
                    >
                        {{ $t('Remove logo') }}
                    </Button>
                </div>
            </div>
            <InputError :message="errors.logo" />
        </div>

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
                <Label for="sepa_creditor_id">{{
                    $t('SEPA creditor identifier')
                }}</Label>
                <Input
                    id="sepa_creditor_id"
                    name="sepa_creditor_id"
                    :default-value="club?.sepa_creditor_id ?? ''"
                    autocomplete="off"
                    class="font-mono"
                />
                <InputError :message="errors.sepa_creditor_id" />
            </div>
            <div class="grid gap-2">
                <Label for="sepa_mandate_date">{{
                    $t('SEPA mandate date')
                }}</Label>
                <Input
                    id="sepa_mandate_date"
                    name="sepa_mandate_date"
                    type="date"
                    :default-value="club?.sepa_mandate_date ?? ''"
                />
                <InputError :message="errors.sepa_mandate_date" />
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
                <Label for="identity_display">{{ $t('Display') }}</Label>
                <Select v-model="identityDisplay">
                    <SelectTrigger id="identity_display" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in identityDisplays"
                            :key="option.id"
                            :value="String(option.id)"
                        >
                            {{ option.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <input
                    type="hidden"
                    name="identity_display"
                    :value="identityDisplay"
                />
                <InputError :message="errors.identity_display" />
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
