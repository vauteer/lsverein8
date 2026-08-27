<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { index as members } from '@/routes/members';

const props = defineProps<{
    label: string;
    value: string;
    /** Extra line under the value, e.g. what the year of the number is. */
    hint?: string;
    /**
     * The member selection this number is taken from. A tile only becomes a
     * link when its number is exactly what that selection lists.
     */
    filter?: string;
    /** The key date the selection is read against, when it is not this year. */
    year?: number;
}>();

const href = computed(() =>
    props.filter === undefined
        ? undefined
        : members({ query: { filter: props.filter, year: props.year } }),
);
</script>

<template>
    <Card class="gap-0 py-4">
        <CardContent class="px-4">
            <component
                :is="href ? Link : 'div'"
                :href="href"
                class="block"
                :class="href ? 'group' : ''"
            >
                <p class="text-sm text-muted-foreground">{{ label }}</p>
                <p
                    class="text-2xl font-semibold tabular-nums"
                    :class="
                        href ? 'underline-offset-4 group-hover:underline' : ''
                    "
                >
                    {{ value }}
                </p>
                <p v-if="hint" class="text-xs text-muted-foreground">
                    {{ hint }}
                </p>
            </component>
        </CardContent>
    </Card>
</template>
