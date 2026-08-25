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

/**
 * A section as sent to the index listing: the section's own columns plus the
 * BLSV label and the number of the current club's members assigned to it.
 */
export type SectionResource = {
    id: number;
    name: string;
    blsv_id: number | null;
    blsv_label: string | null;
    members_count: number;
    shared: boolean;
    modifiable: boolean;
    deletable: boolean;
};

/**
 * The section fields sent to the create/edit forms.
 */
export type SectionFormData = {
    id: number;
    name: string;
    blsv_id: number | null;
};

/**
 * An event as sent to the index listing: the event's own columns plus the
 * number of the current club's members it has been given to.
 */
export type EventResource = {
    id: number;
    name: string;
    members_count: number;
    shared: boolean;
    modifiable: boolean;
    deletable: boolean;
};

/**
 * The event fields sent to the create/edit forms.
 */
export type EventFormData = {
    id: number;
    name: string;
};

/**
 * A backup file as sent to the index listing. `date`, `age` and `size` are
 * pre-formatted server-side; the raw timestamp is deliberately not exposed.
 */
export type BackupResource = {
    id: number;
    date: string;
    filename: string;
    age: string;
    size: string;
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
