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
import type { DashboardAgeBracket } from '@/types';

const props = defineProps<{
    brackets: DashboardAgeBracket[];
}>();

const max = computed(() =>
    Math.max(1, ...props.brackets.map((bracket) => bracket.total)),
);

const totals = computed(() => ({
    male: props.brackets.reduce((sum, bracket) => sum + bracket.male, 0),
    female: props.brackets.reduce((sum, bracket) => sum + bracket.female, 0),
    other: props.brackets.reduce((sum, bracket) => sum + bracket.other, 0),
}));

/** Each segment's share of the widest bracket's bar, so bars stay comparable. */
function share(count: number): string {
    return `${(count / max.value) * 100}%`;
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ $t('Age structure') }}</CardTitle>
            <CardDescription>
                {{ $t('How old the current members are') }}
            </CardDescription>
        </CardHeader>
        <CardContent class="flex flex-col gap-4">
            <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs">
                <li class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-xs bg-chart-1" />
                    {{ $t('Male') }}
                    <span class="tabular-nums">{{ totals.male }}</span>
                </li>
                <li class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-xs bg-chart-2" />
                    {{ $t('Female') }}
                    <span class="tabular-nums">{{ totals.female }}</span>
                </li>
                <li v-if="totals.other > 0" class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-xs bg-chart-4" />
                    {{ $t('Diverse') }}
                    <span class="tabular-nums">{{ totals.other }}</span>
                </li>
            </ul>

            <ul class="flex flex-col gap-2">
                <li v-for="bracket in brackets" :key="bracket.filter">
                    <Link
                        :href="members({ query: { filter: bracket.filter } })"
                        class="group flex items-center gap-3"
                        :aria-label="
                            $t('Show the members of :name', {
                                name: bracket.label,
                            })
                        "
                    >
                        <span
                            class="w-28 shrink-0 truncate text-sm underline-offset-4 group-hover:underline"
                        >
                            {{ bracket.label }}
                        </span>
                        <span
                            class="flex h-2.5 min-w-0 flex-1 overflow-hidden rounded-full bg-muted"
                        >
                            <span
                                class="h-full bg-chart-1"
                                :style="{ width: share(bracket.male) }"
                            />
                            <span
                                class="h-full bg-chart-2"
                                :style="{ width: share(bracket.female) }"
                            />
                            <span
                                class="h-full bg-chart-4"
                                :style="{ width: share(bracket.other) }"
                            />
                        </span>
                        <span
                            class="w-10 shrink-0 text-right text-sm tabular-nums"
                        >
                            {{ bracket.total }}
                        </span>
                    </Link>
                </li>
            </ul>
        </CardContent>
    </Card>
</template>
