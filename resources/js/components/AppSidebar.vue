<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Award,
    DatabaseBackup,
    FileText,
    LayoutGrid,
    Building2,
    Shapes,
    UserCog,
    Users,
} from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import ClubSwitcher from '@/components/ClubSwitcher.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as backups } from '@/routes/backups';
import { edit as editClub, index as clubs } from '@/routes/clubs';
import { index as events } from '@/routes/events';
import { index as logViewer } from '@/routes/log-viewer';
import { index as roles } from '@/routes/roles';
import { index as sections } from '@/routes/sections';
import { index as users } from '@/routes/users';
import type { NavItem } from '@/types';

const page = usePage();

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: wTrans('Dashboard').value,
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: wTrans('Sections').value,
        href: sections(),
        icon: Shapes,
    },
    {
        title: wTrans('Events').value,
        href: events(),
        icon: Award,
    },
    {
        title: wTrans('Roles').value,
        href: roles(),
        icon: UserCog,
    },
    ...(page.props.auth.canManageUsers
        ? [
              {
                  title: wTrans('Users').value,
                  href: users(),
                  icon: Users,
              },
          ]
        : []),
    // Root accounts only: a backup is the whole database, every club at once.
    // Root gets the whole installation's club list; a club admin only a link
    // to the club they are currently in, which is the only one they may edit.
    ...(page.props.auth.canManageClubs
        ? [
              {
                  title: wTrans('Clubs').value,
                  href: clubs(),
                  icon: Building2,
              },
          ]
        : page.props.auth.canEditCurrentClub && page.props.currentClub
          ? [
                {
                    title: wTrans('Club').value,
                    href: editClub(page.props.currentClub.id),
                    icon: Building2,
                },
            ]
          : []),
    ...(page.props.auth.canManageBackups
        ? [
              {
                  title: wTrans('Backups').value,
                  href: backups(),
                  icon: DatabaseBackup,
              },
          ]
        : []),
    // Root accounts only: storage/logs spans every club in the installation.
    // The log viewer is a Blade page, hence external.
    ...(page.props.auth.canViewLogs
        ? [
              {
                  title: wTrans('Logs').value,
                  href: logViewer().url,
                  icon: FileText,
                  external: true,
              },
          ]
        : []),
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
                <SidebarMenuItem>
                    <ClubSwitcher />
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <!-- The mobile sheet sits flush against the viewport edge (unlike
        the desktop sidebar's "inset" variant, which already has its own
        margin), so it needs more clearance to keep the user menu from
        being covered by Debugbar's toolbar docked at the bottom. -->
        <SidebarFooter class="pb-20 md:pb-12">
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
