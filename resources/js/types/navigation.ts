import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    /**
     * Renders a plain anchor instead of an Inertia link. Needed for pages that
     * are not Inertia responses, such as the Blade-rendered log viewer: an
     * Inertia visit there receives HTML without the X-Inertia header.
     */
    external?: boolean;
};
