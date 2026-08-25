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
    impersonator: Pick<User, 'id' | 'name'> | null;
};

export type SharedClub = {
    name: string;
    logo_url: string | null;
};
