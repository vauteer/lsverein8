<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, useTemplateRef } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useInitials } from '@/composables/useInitials';
import { edit } from '@/routes/profile';
import type { SelectOption } from '@/types';

const props = defineProps<{
    hasProfileImage: boolean;
    /** Null when this account follows its club's language. */
    locale: string | null;
    locales: SelectOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: trans('Profile settings'),
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const { getInitials } = useInitials();

/**
 * reka-ui cannot hold an empty item value, so "follow the club" is carried as
 * a sentinel and translated back to an empty string in the submitted input,
 * which Laravel converts to null.
 */
const INHERIT = 'inherit';

const locale = ref(props.locale ?? INHERIT);

const fileInput = useTemplateRef<HTMLInputElement>('fileInput');
const preview = ref<string | null>(null);
const removeImage = ref(false);
const hasCustomImage = ref(props.hasProfileImage);

/**
 * The locally chosen file wins, then "removed" (which falls back to the
 * initials), otherwise whatever the server currently serves.
 */
const avatarSrc = computed(
    () => preview.value ?? (removeImage.value ? undefined : user.value.avatar),
);

function onFileChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    removeImage.value = false;
    hasCustomImage.value = true;
    preview.value = URL.createObjectURL(file);
}

function removePhoto() {
    removeImage.value = true;
    hasCustomImage.value = false;
    preview.value = null;

    if (fileInput.value) {
        fileInput.value.value = '';
    }
}
</script>

<template>
    <Head :title="$t('Profile settings')" />

    <h1 class="sr-only">{{ $t('Profile settings') }}</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="$t('Profile')"
            :description="$t('Update your name and email address')"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label>{{ $t('Profile photo') }}</Label>
                <div class="flex items-center gap-4">
                    <Avatar class="size-16 rounded-lg">
                        <AvatarImage
                            v-if="avatarSrc"
                            :src="avatarSrc"
                            :alt="user.name"
                        />
                        <AvatarFallback
                            class="rounded-lg text-black dark:text-white"
                        >
                            {{ getInitials(user.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="flex flex-col items-start gap-2">
                        <input
                            ref="fileInput"
                            type="file"
                            name="profile_image"
                            accept="image/png,image/jpeg,image/webp"
                            class="text-sm text-muted-foreground file:mr-3 file:inline-flex file:h-7 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:text-sm file:font-medium file:text-secondary-foreground"
                            @change="onFileChange"
                        />
                        <input
                            type="hidden"
                            name="remove_profile_image"
                            :value="removeImage ? '1' : '0'"
                        />
                        <Button
                            v-if="hasCustomImage"
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="removePhoto"
                        >
                            {{ $t('Remove photo') }}
                        </Button>
                    </div>
                </div>
                <InputError :message="errors.profile_image" />
            </div>

            <div class="grid gap-2">
                <Label for="name">{{ $t('Name') }}</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    :placeholder="$t('Full name')"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">{{ $t('Email address') }}</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    :placeholder="$t('Email address')"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="locale">{{ $t('Language') }}</Label>
                <Select v-model="locale">
                    <SelectTrigger id="locale" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="INHERIT">
                            {{ $t('(club language)') }}
                        </SelectItem>
                        <SelectItem
                            v-for="option in locales"
                            :key="option.id"
                            :value="String(option.id)"
                        >
                            {{ option.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <input
                    type="hidden"
                    name="locale"
                    :value="locale === INHERIT ? '' : locale"
                />
                <InputError class="mt-2" :message="errors.locale" />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    variant="outline"
                    :disabled="processing"
                    data-test="update-profile-button"
                >
                    {{ $t('Save') }}
                </Button>
            </div>
        </Form>
    </div>

    <DeleteUser />
</template>
