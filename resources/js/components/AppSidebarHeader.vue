<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import ClubIdentity from '@/components/ClubIdentity.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();

const currentClub = computed(() => page.props.currentClub);
</script>

<template>
    <header
        class="shrink-0 border-b border-sidebar-border/70 transition-[width,height] ease-linear md:flex md:h-16 md:items-center md:px-4 md:group-has-data-[collapsible=icon]/sidebar-wrapper:h-12"
    >
        <!-- Phone only: the logo and the club live in the sidebar, which is a
        closed drawer here, so on a small screen there was nothing at the top
        of the page saying which app and which club this is. Repeated in a bar
        of their own rather than squeezed in beside the breadcrumbs — the same
        fix as in lscraft5. Hidden from md up, where the sidebar shows both. -->
        <div
            class="flex h-14 items-center gap-3 border-b border-sidebar-border/70 px-4 md:hidden"
        >
            <Link :href="dashboard()" :aria-label="$t('Dashboard')">
                <AppLogo />
            </Link>
            <!-- Next to the logo, not pushed to the far edge: the club is an
            avatar plus a short name, so justify-between (which lscraft5 can
            afford with its wide wordmark) left a gap the width of the screen.
            ClubIdentity, not a bare logo, because it honours Club.identity_display —
            a club that shows only its name still appears here. -->
            <ClubIdentity v-if="currentClub" :club="currentClub" />
        </div>
        <div class="flex h-14 items-center gap-2 px-6 md:h-auto md:px-0">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
    </header>
</template>
