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

/**
 * A role as sent to the index listing: the role's own columns plus the number
 * of the current club's members currently or formerly holding it.
 */
export type RoleResource = {
    id: number;
    name: string;
    members_count: number;
    shared: boolean;
    modifiable: boolean;
    deletable: boolean;
};

/**
 * The role fields sent to the create/edit forms.
 */
export type RoleFormData = {
    id: number;
    name: string;
};

/**
 * A club as sent to the (root-only) index listing. Bank details are absent on
 * purpose — they belong on the form, not in a list.
 */
export type ClubResource = {
    id: number;
    name: string;
    city: string;
    logo_url: string | null;
    blsv_member: boolean;
    members_count: number;
    users_count: number;
    current: boolean;
    modifiable: boolean;
    deletable: boolean;
    switchable: boolean;
};

/**
 * The club fields sent to the create/edit forms. `logo_url` is display only:
 * there is no upload yet, and the column is preserved on update.
 */
export type ClubFormData = {
    id: number;
    name: string;
    street: string;
    zipcode: string;
    city: string;
    bank: string;
    account_owner: string;
    iban: string;
    bic: string;
    sepa: string | null;
    sepa_date: string | null;
    display: number | string;
    locale: string;
    honor_years: string | null;
    blsv_member: boolean;
    use_items: boolean;
    logo_url: string | null;
    has_logo: boolean;
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
