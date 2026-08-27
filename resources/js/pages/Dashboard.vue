<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import AgeStructureCard from '@/components/dashboard/AgeStructureCard.vue';
import DevelopmentCard from '@/components/dashboard/DevelopmentCard.vue';
import DistributionCard from '@/components/dashboard/DistributionCard.vue';
import StatTile from '@/components/dashboard/StatTile.vue';
import Heading from '@/components/Heading.vue';
import { dashboard } from '@/routes';
import type {
    BreadcrumbItem,
    DashboardAgeBracket,
    DashboardDevelopmentPoint,
    DashboardDistributionRow,
    DashboardSummary,
    DashboardYearsBand,
} from '@/types';

const props = defineProps<{
    year: number;
    summary: DashboardSummary;
    ageStructure: DashboardAgeBracket[];
    membershipYears: DashboardYearsBand[];
    development: DashboardDevelopmentPoint[];
    sections: DashboardDistributionRow[];
    /** Null for anybody but a club admin — who pays what is a treasurer's business. */
    subscriptions: DashboardDistributionRow[] | null;
}>();

const averageAge = computed(() =>
    props.summary.average_age === null
        ? '—'
        : props.summary.average_age.toLocaleString(undefined, {
              minimumFractionDigits: 1,
              maximumFractionDigits: 1,
          }),
);

const year = computed(() => String(props.year));

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: trans('Dashboard'),
                href: dashboard(),
            } satisfies BreadcrumbItem,
        ],
    },
});
</script>

<template>
    <Head :title="$t('Dashboard')" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            :title="$t('Dashboard')"
            :description="$t('The club as it stands today')"
        />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <StatTile
                :label="$t('Members')"
                :value="String(summary.members)"
                filter="members"
            />
            <StatTile
                :label="$t('Arrivals')"
                :value="String(summary.joined)"
                :hint="year"
                filter="joined"
                :year="props.year"
            />
            <StatTile
                :label="$t('Departures')"
                :value="String(summary.left)"
                :hint="year"
                filter="retired"
                :year="props.year"
            />
            <StatTile
                :label="$t('Average age')"
                :value="averageAge"
                :hint="$t('Years')"
            />
            <StatTile
                :label="$t('Honours')"
                :value="String(summary.due_honours)"
                :hint="year"
                filter="due_honours"
            />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <AgeStructureCard :brackets="ageStructure" />

            <DistributionCard
                :title="$t('Years in the club')"
                :description="$t('How long the current members have been in')"
                :rows="membershipYears"
                :empty="$t('No members yet.')"
            />
        </div>

        <DevelopmentCard :development="development" />

        <!-- Half width beside the subscriptions, full width without them:
        a lone card in a two-column grid reads as a missing one. -->
        <div class="grid gap-4" :class="subscriptions ? 'lg:grid-cols-2' : ''">
            <DistributionCard
                :title="$t('Sections')"
                :description="$t('Members in each section right now')"
                :rows="sections"
                :empty="$t('No sections yet.')"
            />

            <DistributionCard
                v-if="subscriptions"
                :title="$t('Subscriptions')"
                :description="$t('What the current members pay')"
                :rows="subscriptions"
                :empty="$t('No subscriptions yet.')"
            />
        </div>
    </div>
</template>
