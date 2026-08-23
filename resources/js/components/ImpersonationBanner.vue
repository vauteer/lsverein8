<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { UserRoundX } from '@lucide/vue';
import { computed } from 'vue';
import ImpersonationController from '@/actions/App/Http/Controllers/ImpersonationController';

const page = usePage();
const impersonator = computed(() => page.props.auth.impersonator);
</script>

<template>
    <div
        v-if="impersonator"
        class="flex items-center justify-center gap-2 bg-amber-500 px-4 py-2 text-sm font-medium text-amber-950 dark:bg-amber-600 dark:text-amber-50"
    >
        <span>
            {{
                $t('Logged in as :user — by :impersonator', {
                    user: page.props.auth.user.name,
                    impersonator: impersonator.name,
                })
            }}
        </span>
        <Link
            :href="ImpersonationController.destroy()"
            as="button"
            class="inline-flex items-center gap-1 underline hover:no-underline"
        >
            <UserRoundX class="size-4" />
            {{ $t('Back to :name', { name: impersonator.name }) }}
        </Link>
    </div>
</template>
