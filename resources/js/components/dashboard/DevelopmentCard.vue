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
import type { DashboardDevelopmentPoint } from '@/types';

const props = defineProps<{
    development: DashboardDevelopmentPoint[];
}>();

const max = computed(() =>
    Math.max(1, ...props.development.map((point) => point.members)),
);

function height(count: number): string {
    return `${Math.max(count > 0 ? 2 : 0, Math.round((count / max.value) * 100))}%`;
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ $t('Development') }}</CardTitle>
            <CardDescription>
                {{
                    $t(
                        'Members at the end of each year, with arrivals and departures',
                    )
                }}
            </CardDescription>
        </CardHeader>
        <CardContent class="overflow-x-auto">
            <div class="flex min-w-[32rem] items-end gap-2">
                <div
                    v-for="point in development"
                    :key="point.year"
                    class="flex min-w-0 flex-1 flex-col items-center gap-1"
                >
                    <span class="text-xs tabular-nums">{{
                        point.members
                    }}</span>

                    <!-- The head count at 31 December is what the member list
                    shows for that year through its own year picker, so the
                    column and the list it opens agree. -->
                    <Link
                        :href="
                            members({
                                query: { filter: 'members', year: point.year },
                            })
                        "
                        class="group flex h-28 w-full items-end"
                        :aria-label="
                            $t('Show the members of :name', {
                                name: String(point.year),
                            })
                        "
                    >
                        <span
                            class="w-full rounded-t-sm bg-primary group-hover:bg-primary/70"
                            :style="{ height: height(point.members) }"
                        />
                    </Link>

                    <span class="text-xs text-muted-foreground tabular-nums">
                        {{ point.year }}
                    </span>

                    <span
                        class="flex flex-col items-center text-[10px] tabular-nums"
                    >
                        <Link
                            class="text-chart-2 underline-offset-2 hover:underline"
                            :href="
                                members({
                                    query: {
                                        filter: 'joined',
                                        year: point.year,
                                    },
                                })
                            "
                            :aria-label="
                                $t('Joined in :year', {
                                    year: String(point.year),
                                })
                            "
                        >
                            +{{ point.joined }}
                        </Link>
                        <Link
                            class="text-destructive underline-offset-2 hover:underline"
                            :href="
                                members({
                                    query: {
                                        filter: 'left',
                                        year: point.year,
                                    },
                                })
                            "
                            :aria-label="
                                $t('Left in :year', {
                                    year: String(point.year),
                                })
                            "
                        >
                            −{{ point.left }}
                        </Link>
                    </span>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
