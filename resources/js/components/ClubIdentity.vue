<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import type { SharedClub } from '@/types';

/**
 * How a club presents itself, honouring Club.identity_display: logo and name, logo
 * only, or name only. A wordmark logo already contains the club name, so
 * showing both would print it twice.
 *
 * The logo half still renders when show_logo is set but no file is uploaded —
 * the avatar falls back to the initial, which beats an empty sidebar.
 */
defineProps<{
    club: SharedClub;
}>();
</script>

<template>
    <Avatar v-if="club.show_logo" class="size-6 rounded-md">
        <!-- contain, not cover: cropping a wordmark can cut off the name. -->
        <AvatarImage
            v-if="club.logo_url"
            class="object-contain"
            :src="club.logo_url"
            :alt="club.name"
        />
        <AvatarFallback class="rounded-md text-xs">
            {{ club.name.charAt(0) }}
        </AvatarFallback>
    </Avatar>
    <span
        v-if="club.show_name"
        class="truncate text-sm text-sidebar-foreground/70 group-data-[collapsible=icon]:hidden"
    >
        {{ club.name }}
    </span>
</template>
