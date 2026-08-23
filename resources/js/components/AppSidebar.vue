<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutGrid, Users } from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
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
import { index as users } from '@/routes/users';
import type { NavItem } from '@/types';

const page = usePage();

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: wTrans('Dashboard').value,
        href: dashboard(),
        icon: LayoutGrid,
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
                <SidebarMenuItem v-if="page.props.currentClub">
                    <div class="flex items-center gap-2 px-1 py-1">
                        <Avatar class="size-6 rounded-md">
                            <AvatarImage
                                v-if="page.props.currentClub.logo_url"
                                :src="page.props.currentClub.logo_url"
                                :alt="page.props.currentClub.name"
                            />
                            <AvatarFallback class="rounded-md text-xs">
                                {{ page.props.currentClub.name.charAt(0) }}
                            </AvatarFallback>
                        </Avatar>
                        <span
                            class="truncate text-sm text-sidebar-foreground/70 group-data-[collapsible=icon]:hidden"
                        >
                            {{ page.props.currentClub.name }}
                        </span>
                    </div>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
