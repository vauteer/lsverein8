<script setup lang="ts">
import iconLogo from '@/images/lsverein8-icon.svg';
import fullLogo from '@/images/lsverein8.svg';

withDefaults(
    defineProps<{
        iconOnly?: boolean;
    }>(),
    {
        iconOnly: false,
    },
);
</script>

<template>
    <img
        :src="iconOnly ? iconLogo : fullLogo"
        alt="LSVerein 8 Logo"
        class="block h-8 w-auto dark:brightness-75 dark:invert"
    />
</template>
