<script setup lang="ts">
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
import type { SelectOption, UserFormData } from '@/types';

withDefaults(
    defineProps<{
        user?: UserFormData | null;
        roles: SelectOption[];
        locales: SelectOption[];
        errors: Record<string, string>;
        /**
         * The create form accepts a bare email for an account that already
         * exists elsewhere, in which case the other fields are ignored.
         */
        detailsRequired?: boolean;
    }>(),
    { detailsRequired: true },
);
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-2">
            <Label for="email">{{ $t('Email address') }}</Label>
            <Input
                id="email"
                type="email"
                name="email"
                :default-value="user?.email"
                required
                autocomplete="off"
                :placeholder="$t('Email address')"
            />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="name">{{ $t('Name') }}</Label>
            <Input
                id="name"
                name="name"
                :default-value="user?.name"
                :required="detailsRequired"
                autocomplete="off"
                :placeholder="$t('Full name')"
            />
            <InputError :message="errors.name" />
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="phone">{{ $t('Phone') }}</Label>
                <Input
                    id="phone"
                    type="tel"
                    name="phone"
                    :default-value="user?.phone ?? undefined"
                    autocomplete="off"
                />
                <InputError :message="errors.phone" />
            </div>

            <div class="grid gap-2">
                <Label for="locale">{{ $t('Language') }}</Label>
                <Select name="locale" :default-value="user?.locale ?? 'de'">
                    <SelectTrigger id="locale" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="locale in locales"
                            :key="locale.id"
                            :value="String(locale.id)"
                        >
                            {{ locale.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.locale" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="role">{{ $t('Role') }}</Label>
            <Select
                name="role"
                :default-value="String(user?.role ?? roles[0]?.id)"
            >
                <SelectTrigger id="role" class="w-full sm:max-w-xs">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="role in roles"
                        :key="role.id"
                        :value="String(role.id)"
                    >
                        {{ role.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <p class="text-sm text-muted-foreground">
                {{ $t('Determines what this user may do within the club.') }}
            </p>
            <InputError :message="errors.role" />
        </div>
    </div>
</template>
