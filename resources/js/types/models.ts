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
    /** Null means the user follows the club language. */
    locale: string | null;
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
    /** Null means the user follows the club language. */
    locale: string | null;
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
 * An inventory item as sent to the index listing: the item's own columns plus
 * the number of the current club's members it has been issued to.
 */
export type ItemResource = {
    id: number;
    name: string;
    members_count: number;
    modifiable: boolean;
    deletable: boolean;
};

/**
 * The item fields sent to the create/edit forms.
 */
export type ItemFormData = {
    id: number;
    name: string;
};

/**
 * A subscription as sent to the index listing: the subscription's own columns
 * plus the pre-formatted amount and the number of the current club's members
 * holding it.
 */
export type SubscriptionResource = {
    id: number;
    name: string;
    amount: number;
    /** Formatted server-side, so the decimal comma is not in a template. */
    amount_label: string;
    transfer_text: string | null;
    memo: string | null;
    members_count: number;
    modifiable: boolean;
    deletable: boolean;
};

/**
 * The subscription fields sent to the create/edit forms.
 */
export type SubscriptionFormData = {
    id: number;
    name: string;
    amount: number;
    transfer_text: string | null;
    memo: string | null;
};

/**
 * One fee the collection dialog offers. 0 € fees never appear here: there is
 * nothing to collect from them (SubscriptionController::debitOptions()).
 */
export type DebitableSubscription = {
    id: number;
    name: string;
    amount_label: string;
};

/**
 * One generated file offered on the debit result page. `href` is a bare name
 * under /downloads; the server prefixes the current club (DownloadController).
 */
export type GeneratedDownload = {
    name: string;
    href: string;
};

/**
 * A member who holds one of the collected subscriptions but does not pay by
 * direct debit, so they have to be billed by hand.
 */
export type OutstandingPayment = {
    name: string;
    subscription: string;
    paymentMethod: string;
};

/**
 * A member as sent to the index listing. `subscriptions` and `last_event` are
 * null for a non-admin — what somebody pays is a treasurer's business, and the
 * bank details are not in this shape at all.
 *
 * Everything derived (`age`, `is_member`, `membership_years`, `sections`,
 * `roles`) is computed against the chosen year's key date, not against today.
 */
export type MemberResource = {
    id: number;
    /** The club's own running number, not the primary key. */
    member_id: number;
    surname: string;
    first_name: string;
    full_name: string;
    address: string;
    gender: string;
    birthday: string;
    age: number;
    /** Dead as of the key date; the list marks them with a dagger. */
    gone: boolean;
    is_member: boolean;
    membership_years: number;
    sections: string;
    roles: string;
    subscriptions: string | null;
    last_event: string | null;
    modifiable: boolean;
};

/**
 * The member fields sent to the create/edit forms — bank details included,
 * which is why only an admin reaches those pages.
 */
export type MemberFormData = {
    id: number;
    member_id: number;
    surname: string;
    first_name: string;
    gender: string;
    birthday: string;
    death_day: string | null;
    street: string;
    zipcode: string;
    city: string;
    email: string | null;
    phone: string | null;
    payment_method: string;
    bank: string | null;
    account_owner: string | null;
    iban: string | null;
    bic: string | null;
    memo: string | null;
    full_name: string;
};

/** One pivot row with a from/to range, as the detail page lists it. */
export type MemberRangeRow = {
    name: string;
    range: string;
    memo: string | null;
};

/** The member as the read-only detail page shows them, already formatted. */
export type MemberDetail = {
    id: number;
    member_id: number;
    full_name: string;
    gender: string;
    birthday: string;
    death_day: string;
    age: number;
    address: string;
    email: string | null;
    phone: string | null;
    memo: string | null;
    entry: string;
    membership_years: number;
    is_member: boolean;
    payment_method: string;
    bank: string | null;
    account_owner: string | null;
    iban: string | null;
    bic: string | null;
    memberships: MemberRangeRow[];
    sections: MemberRangeRow[];
    roles: MemberRangeRow[];
    items: MemberRangeRow[];
    events: { name: string; date: string; memo: string | null }[];
    subscriptions: {
        name: string;
        amount_label: string;
        memo: string | null;
    }[];
};

/** The state the member list is read with, round-tripped through the URL. */
export type MemberListFilters = {
    search: string;
    filter: string;
    sort: string;
    year: number;
};

/**
 * A one-off direct debit as sent to the index listing: the debit's own columns
 * plus the member it is booked on and the pre-formatted amount and date.
 */
export type DebitResource = {
    id: number;
    member_id: number;
    member_name: string;
    amount: number;
    /** Formatted server-side, so the decimal comma is not in a template. */
    amount_label: string;
    transfer_text: string;
    /** ISO, for the form; `due_at_label` is the German one for the table. */
    due_at: string;
    due_at_label: string;
    /** Whether a collection started today would take this row along. */
    due: boolean;
    modifiable: boolean;
    deletable: boolean;
};

/**
 * The debit fields sent to the create/edit forms. `member_name` is only there
 * so the picker can show the member of a debit already on file.
 */
export type DebitFormData = {
    id: number;
    member_id: number;
    member_name: string;
    amount: number;
    transfer_text: string;
    due_at: string;
};

/**
 * One member the debit form can be booked on: this club's members who have a
 * bank account on file (DebitController::memberOptions()).
 */
export type DebitableMember = {
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
