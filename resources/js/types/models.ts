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
    /** Members assigned right now; the number links to that selection. */
    members_count: number;
    /** Members ever assigned, former members included; its own selection. */
    ever_members_count: number;
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
    /** Members assigned right now; the number links to that selection. */
    members_count: number;
    /** Members ever assigned, former members included; its own selection. */
    ever_members_count: number;
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
 * One generated file offered for download — the SEPA collection's results and
 * the BLSV statistic. `href` names the file under /downloads without a club;
 * the server puts the current club's prefix back on (DownloadController).
 */
export type GeneratedDownload = {
    name: string;
    href: string;
    /** Set by the BLSV statistic; the SEPA screens list files without one. */
    description?: string;
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
/** A member whose bank details can be copied into a member form. */
export type AccountSource = {
    id: number;
    surname: string;
    name: string;
    bank: string | null;
    account_owner: string | null;
    iban: string | null;
    bic: string | null;
};

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
    bank: string | null;
    account_owner: string | null;
    iban: string | null;
    bic: string | null;
    memo: string | null;
    full_name: string;
};

/**
 * One pivot row as MemberRelationSection renders it. `id` is the pivot row's
 * own key, never the related row's: the same section or role may be held twice
 * over different ranges, so nothing else addresses a row.
 */
export type MemberRelationRow = {
    id: number;
    name: string;
    /** The range or date, already formatted for reading. */
    detail: string;
    memo: string | null;
};

/** One pivot row with a from/to range, as the member page lists and edits it. */
export type MemberRangeRow = {
    id: number;
    /** The related section/role/item; null for a membership, whose club is implicit. */
    related_id: number | null;
    name: string;
    range: string;
    from: string;
    to: string | null;
    memo: string | null;
};

/** One honour: a single date rather than a range. */
export type MemberEventRow = {
    id: number;
    event_id: number;
    name: string;
    date: string;
    date_label: string;
    memo: string | null;
};

/** One subscription the member holds. `member_subscription` carries no dates. */
export type MemberSubscriptionRow = {
    id: number;
    subscription_id: number;
    name: string;
    amount_label: string;
    memo: string | null;
};

/** The pickers the member page's dialogs offer; null for a read-only account. */
export type MemberRelationOptions = {
    sections: SelectOption[];
    roles: SelectOption[];
    events: SelectOption[];
    subscriptions: SelectOption[];
    items: SelectOption[];
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
    events: MemberEventRow[];
    subscriptions: MemberSubscriptionRow[];
};

/** One download the member list offers, all of them of the current selection. */
export type MemberExportFormat = {
    id: string;
    name: string;
    description: string;
};

/**
 * A member already on file who matches the one being entered — same club, same
 * name, same birthday. Shown by the create form, never stored.
 */
export type DuplicateMember = {
    id: number;
    name: string;
    member_id: number;
    href: string;
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
    sepa_creditor_id: string | null;
    sepa_mandate_date: string | null;
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

/** The headline numbers of the dashboard; `average_age` is null in an empty club. */
export type DashboardSummary = {
    members: number;
    former: number;
    joined: number;
    left: number;
    average_age: number | null;
    due_honours: number;
};

/** One of the seven BLSV age groups, split by gender. */
export type DashboardAgeBracket = {
    /** The member selection this bracket links to, e.g. `age_18-26`. */
    filter: string;
    label: string;
    male: number;
    female: number;
    /** Diverse members; the chart hides the bar while it is zero. */
    other: number;
    total: number;
};

/**
 * One bar of a distribution card. `filter` is the member selection the bar
 * links to; a bar without one is a distribution the member list cannot show,
 * and is rendered as plain text rather than as a link that lies.
 */
export type DashboardBarRow = {
    label: string;
    count: number;
    filter?: string;
};

/** One band of "how long have they been in", e.g. `20–29`. Not a selection. */
export type DashboardYearsBand = {
    label: string;
    count: number;
};

/** One year of the development chart, all three numbers of that year. */
export type DashboardDevelopmentPoint = {
    year: number;
    members: number;
    joined: number;
    left: number;
};

/**
 * One row of a distribution card — a section, a role, a subscription. The
 * count is produced by the very selection `filter` names.
 */
export type DashboardDistributionRow = DashboardBarRow & {
    filter: string;
};
