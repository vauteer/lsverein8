<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Award,
    Banknote,
    ClipboardList,
    Contact,
    DatabaseBackup,
    FileText,
    Info,
    LayoutGrid,
    Building2,
    Shapes,
    Euro,
    Package,
    Telescope,
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
import { about, blsv, dashboard, telescope } from '@/routes';
import { index as backups } from '@/routes/backups';
import { edit as editClub, index as clubs } from '@/routes/clubs';
import { index as debits } from '@/routes/debits';
import { index as events } from '@/routes/events';
import { index as items } from '@/routes/items';
import { index as logViewer } from '@/routes/log-viewer';
import { index as members } from '@/routes/members';
import { index as roles } from '@/routes/roles';
import { index as sections } from '@/routes/sections';
import { index as subscriptions } from '@/routes/subscriptions';
import { index as users } from '@/routes/users';
import type { NavItem } from '@/types';

const page = usePage();

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: wTrans('Dashboard').value,
        href: dashboard(),
        icon: LayoutGrid,
    },
    // The club's own people, so it comes before the lists that classify them.
    {
        title: wTrans('Members').value,
        href: members(),
        icon: Contact,
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
    {
        title: wTrans('Subscriptions').value,
        href: subscriptions(),
        icon: Euro,
    },
    // Admin-only: a debit names a member and the money about to leave their
    // account, so unlike the fee list this is not open to the whole club.
    ...(page.props.auth.canManageDebits
        ? [
              {
                  title: wTrans('Debits').value,
                  href: debits(),
                  icon: Banknote,
              },
          ]
        : []),
    // Opt-in per club (clubs.use_items); ItemPolicy refuses the screens for
    // a club that has not switched the inventory on.
    ...(page.props.currentClub?.uses_items
        ? [
              {
                  title: wTrans('Inventory').value,
                  href: items(),
                  icon: Package,
              },
          ]
        : []),
    ...(page.props.auth.canManageUsers
        ? [
              {
                  title: wTrans('Users').value,
                  href: users(),
                  icon: Users,
              },
          ]
        : []),
    // The club's yearly report and its Nachmeldungen to the association.
    // Only where the club is a BLSV member and the account may build them, so
    // the Feuerwehr never sees it. Everything above this entry is the club's
    // own data; from here down it is administration — and the BLSV report is
    // exactly that boundary, the club's duty outward.
    ...(page.props.auth.canReportToBlsv
        ? [
              {
                  title: wTrans('BLSV').value,
                  href: blsv(),
                  icon: ClipboardList,
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
    // Root accounts only, for the same reason: an entry carries the request
    // payload and query bindings of every club at once. Telescope ships its
    // own Blade/Vue app, so this one is external too.
    ...(page.props.auth.canViewTelescope
        ? [
              {
                  title: wTrans('Telescope').value,
                  href: telescope().url,
                  icon: Telescope,
                  external: true,
              },
          ]
        : []),
    // Last, and for everybody: credits and the contact address, which is
    // where a user goes when something on one of the screens above is wrong.
    {
        title: wTrans('About').value,
        href: about(),
        icon: Info,
    },
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
