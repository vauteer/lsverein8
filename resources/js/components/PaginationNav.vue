<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { buttonVariants } from '@/components/ui/button';
import type { PaginationLink } from '@/types';

defineProps<{
    links: PaginationLink[];
}>();

const entities: Record<string, string> = {
    '&laquo;': '«',
    '&raquo;': '»',
    '&hellip;': '…',
    '&nbsp;': ' ',
    '&amp;': '&',
};

const decodeLabel = (label: string): string =>
    label.replace(/&[a-z]+;/g, (entity) => entities[entity] ?? entity);
</script>

<template>
    <nav class="flex flex-wrap gap-1" aria-label="Pagination">
        <component
            :is="link.url ? Link : 'span'"
            v-for="(link, index) in links"
            :key="index"
            :href="link.url ?? undefined"
            :class="[
                link.active
                    ? buttonVariants({ variant: 'secondary' })
                    : buttonVariants({ variant: 'outline' }),
                !link.url && 'cursor-default opacity-50',
                index > 1 && index < links.length - 2
                    ? 'hidden sm:inline-flex'
                    : 'inline-flex',
            ]"
            :aria-current="link.active ? 'page' : undefined"
            :aria-disabled="!link.url ? true : undefined"
        >
            {{ decodeLabel(link.label) }}
        </component>
    </nav>
</template>
