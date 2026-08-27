<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { index as members } from '@/routes/members';
import type { DashboardBarRow } from '@/types';

const props = defineProps<{
    title: string;
    description?: string;
    rows: DashboardBarRow[];
    /** What to say when there is nothing to show. */
    empty: string;
}>();

// Relative to the largest bar, not to the club: a card of small numbers
// should still be readable.
const max = computed(() => Math.max(1, ...props.rows.map((row) => row.count)));

function width(count: number): string {
    return `${Math.max(count > 0 ? 2 : 0, Math.round((count / max.value) * 100))}%`;
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ title }}</CardTitle>
            <CardDescription v-if="description">
                {{ description }}
            </CardDescription>
        </CardHeader>
        <CardContent>
            <p v-if="rows.length === 0" class="text-sm text-muted-foreground">
                {{ empty }}
            </p>

            <ul v-else class="flex flex-col gap-2">
                <li v-for="row in rows" :key="row.label">
                    <component
                        :is="row.filter ? Link : 'div'"
                        :href="
                            row.filter
                                ? members({ query: { filter: row.filter } })
                                : undefined
                        "
                        class="group flex items-center gap-3"
                        :aria-label="
                            row.filter
                                ? $t('Show the members of :name', {
                                      name: row.label,
                                  })
                                : undefined
                        "
                    >
                        <span
                            class="w-32 shrink-0 truncate text-sm"
                            :class="
                                row.filter
                                    ? 'underline-offset-4 group-hover:underline'
                                    : ''
                            "
                        >
                            {{ row.label }}
                        </span>
                        <span
                            class="h-2.5 min-w-0 flex-1 overflow-hidden rounded-full bg-muted"
                        >
                            <span
                                class="block h-full rounded-full bg-primary transition-[width]"
                                :style="{ width: width(row.count) }"
                            />
                        </span>
                        <span
                            class="w-10 shrink-0 text-right text-sm tabular-nums"
                        >
                            {{ row.count }}
                        </span>
                    </component>
                </li>
            </ul>
        </CardContent>
    </Card>
</template>
