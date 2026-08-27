<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';
import type { SimpleIcon } from 'simple-icons';
import { computed } from 'vue';

type Props = {
    icon: LucideIcon | SimpleIcon;
};

const props = defineProps<Props>();

/**
 * Lucide ships functional components, simple-icons ships plain objects that
 * carry the official path and brand colour — the shape tells the two apart.
 *
 * The brand marks are drawn unmodified and in their own colour: they are used
 * to point at the project they belong to, not to decorate this application.
 */
const brand = computed<SimpleIcon | null>(() =>
    typeof props.icon === 'function' ? null : props.icon,
);
</script>

<template>
    <svg
        v-if="brand"
        role="img"
        viewBox="0 0 24 24"
        xmlns="http://www.w3.org/2000/svg"
        :fill="`#${brand.hex}`"
        :aria-label="brand.title"
    >
        <path :d="brand.path" />
    </svg>

    <component :is="icon" v-else class="text-muted-foreground" />
</template>
