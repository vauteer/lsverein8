export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    canManageUsers: boolean;
    canViewLogs: boolean;
    canManageBackups: boolean;
    canManageClubs: boolean;
    canEditCurrentClub: boolean;
    impersonator: Pick<User, 'id' | 'name'> | null;
};

export type SharedClub = {
    id: number;
    name: string;
    logo_url: string | null;
    /** From Club.display: a wordmark logo already contains the name. */
    show_logo: boolean;
    show_name: boolean;
    /** clubs.use_items: the inventory is opt-in per club. */
    uses_items: boolean;
};

/** One entry of the sidebar club picker. Empty list when there is one club. */
export type SwitchableClub = {
    id: number;
    name: string;
    current: boolean;
};
