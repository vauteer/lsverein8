export type SelectOption = {
    id: number | string;
    name: string;
};

/**
 * A user of the current club as sent to the index listing: the account's own
 * columns plus the club_user role and the derived last-login timestamp.
 */
export type UserResource = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    locale: string;
    last_login: string | null;
    role: number | null;
    role_label: string | null;
    admin: boolean;
    avatar: string;
    modifiable: boolean;
    impersonatable: boolean;
};

/**
 * The user fields sent to the create/edit forms, with the club_user role
 * flattened in as `role` (there is no role column on the users table).
 */
export type UserFormData = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    locale: string;
    role: number;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        links: PaginationLink[];
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
};
