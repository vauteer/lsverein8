<script setup lang="ts">
import { computed } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { DashboardLogins } from '@/types';

const props = defineProps<{
    logins: DashboardLogins;
}>();

// Scaled against the busiest month of anybody, not against each user's own
// peak: the rows are meant to be comparable, and the count beside them already
// carries the magnitude.
const max = computed(() =>
    Math.max(1, ...props.logins.users.flatMap((user) => user.months)),
);

function height(count: number): string {
    return `${Math.max(count > 0 ? 8 : 0, Math.round((count / max.value) * 100))}%`;
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ $t('Logins') }}</CardTitle>
            <CardDescription>
                {{
                    $t(
                        'How often each account signed in over the last twelve months',
                    )
                }}
            </CardDescription>
        </CardHeader>
        <CardContent>
            <p
                v-if="logins.users.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ $t('No logins recorded yet.') }}
            </p>

            <template v-else>
                <ul class="flex flex-col gap-2">
                    <li
                        v-for="user in logins.users"
                        :key="user.name"
                        class="flex items-center gap-3"
                    >
                        <span class="w-32 shrink-0 truncate text-sm">
                            {{ user.name }}
                        </span>

                        <!-- One column per month, oldest first; a month with
                        any login keeps a visible stub so a quiet row still
                        reads as a shape rather than an empty strip. -->
                        <span
                            class="flex h-8 min-w-0 flex-1 items-end gap-px"
                            :aria-label="
                                $t(':count logins in the last twelve months', {
                                    count: String(user.count),
                                })
                            "
                        >
                            <span
                                v-for="(count, index) in user.months"
                                :key="index"
                                class="flex h-full flex-1 items-end rounded-xs bg-muted"
                                :title="`${logins.months[index]}: ${count}`"
                            >
                                <span
                                    class="block w-full rounded-xs bg-primary"
                                    :style="{ height: height(count) }"
                                />
                            </span>
                        </span>

                        <span
                            class="w-10 shrink-0 text-right text-sm tabular-nums"
                        >
                            {{ user.count }}
                        </span>
                    </li>
                </ul>

                <p
                    v-if="logins.dormant > 0"
                    class="mt-3 text-sm text-muted-foreground"
                >
                    {{
                        $t(':count accounts did not sign in at all', {
                            count: String(logins.dormant),
                        })
                    }}
                </p>
            </template>
        </CardContent>
    </Card>
</template>
