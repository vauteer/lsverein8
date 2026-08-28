<?php

namespace App\Http\Controllers;

use App\Concerns\SelectsMembers;
use App\Enums\Gender;
use App\Enums\MemberExport;
use App\Enums\MemberFilter;
use App\Enums\MemberSort;
use App\Http\Requests\MemberResignRequest;
use App\Http\Requests\MemberStoreRequest;
use App\Http\Requests\MemberUpdateRequest;
use App\Http\Resources\MemberResource;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\Event;
use App\Models\Item;
use App\Models\Member;
use App\Models\Role;
use App\Models\Section;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    use SelectsMembers;

    private const int PER_PAGE = 15;

    public function index(Request $request): Response
    {
        $selection = $this->selection($request);

        return Inertia::render('members/Index', [
            'members' => MemberResource::collection(
                $selection['query']
                    // Everything MemberResource derives comes from these;
                    // without them the list is one query per row per relation.
                    ->with(['memberships', 'sections', 'roles', 'subscriptions', 'events'])
                    ->paginate(self::PER_PAGE)
                    ->withQueryString()
            ),
            'filters' => [
                'search' => $selection['search'],
                'filter' => $selection['filter'],
                'sort' => $selection['sort']->value,
                'year' => $selection['year'],
            ],
            'filterOptions' => [
                ...MemberFilter::optionsFor($request->user()),
                ...$this->dynamicFilters(),
            ],
            'sortOptions' => MemberSort::options(),
            'yearOptions' => $this->yearOptions(),
            // A subscription has no from/to, so asking which members held one
            // in 2019 is not a question the data can answer. lsverein7 greyed
            // the year picker out for exactly these selections.
            'yearApplies' => ! str_starts_with($selection['filter'], 'subscription_'),
            'canCreate' => $request->user()->can('create', Member::class),
            // Every format exports this same selection, so the menu needs no
            // state of its own beyond the list's own query string. Which
            // formats are offered depends on the selection, though: see
            // MemberExport::isAvailableFor().
            'exportFormats' => MemberExport::optionsFor($selection['filter']),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('members/Create', [
            ...$this->formOptions(),
            'sections' => $this->options(Section::query()->orderBy('name')),
            'subscriptions' => $this->options(Subscription::query()->orderBy('name')),
            'accountSources' => $this->accountSources(),
            'today' => now()->format('Y-m-d'),
            'backQuery' => $this->backQuery($request),
            // Set only when store() bounced the form because somebody of that
            // name and birthday is already on file. Flashed, so it is gone on
            // the next visit.
            'duplicate' => session('duplicate'),
        ]);
    }

    public function store(MemberStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $clubId = currentClubId();

        $duplicate = $this->findDuplicate($validated);

        // Not a rule in MemberStoreRequest: the page needs the found member
        // itself — their number, their membership dates and a link — and a
        // FormRequest can only hand back a string.
        if ($duplicate !== null && ! $request->boolean('confirm_duplicate')) {
            return back()
                ->withErrors(['confirm_duplicate' => $this->duplicateMessage($duplicate)])
                ->with('duplicate', $this->duplicateDetails($duplicate));
        }

        $member = Member::create([
            ...$this->memberAttributes($validated),
            'club_id' => $clubId,
            // The club's own running number, not the primary key. Handed out
            // here rather than accepted from the form so two admins filling in
            // a member at the same time cannot pick the same one.
            'member_id' => (int) Member::query()->max('member_id') + 1,
        ]);

        $member->memberships()->attach($clubId, ['from' => $validated['entry_date']]);
        $member->sections()->attach($validated['section_id'], [
            'from' => $validated['entry_date'],
            'memo' => __('Joined'),
        ]);

        if (($validated['subscription_id'] ?? null) !== null) {
            $member->subscriptions()->attach($validated['subscription_id'], ['memo' => __('Joined')]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member created.')]);

        // To the member page, not back to the list — the same reason resign()
        // gives. A member who joins next month, or who is outside the current
        // selection for any other reason, is simply not in the list, which
        // reads as though the save had failed. Somebody did exactly that and
        // entered the member a second time.
        return to_route('members.show', [$member, ...$this->backQuery($request)]);
    }

    /**
     * Somebody of the same name and birthday already on file in this club.
     *
     * Name plus birthday, not name alone: two Hans Bauers in a village club is
     * ordinary, the same one twice is not. ClubScope keeps the search inside
     * the club. Former members count — that is the case worth catching, see
     * duplicateMessage().
     *
     * @param  array<string, mixed>  $validated
     */
    private function findDuplicate(array $validated): ?Member
    {
        return Member::query()
            ->where('surname', $validated['surname'])
            ->where('first_name', $validated['first_name'])
            ->whereDate('birthday', $validated['birthday'])
            ->first();
    }

    /**
     * What the warning says, which depends on whether they are still a member.
     *
     * A former member is the expensive case: entering them again as a new
     * record loses every year they were in the club, because membershipYears()
     * sums the club_member rows of *one* member. Two of the four duplicate
     * groups in production came about that way — one of them costs the person
     * fourteen years and moves their 25-year honour from 2035 to 2050.
     */
    private function duplicateMessage(Member $member): string
    {
        $membership = $this->latestMembership($member);

        if ($membership?->to !== null) {
            return __('There is already a :name, born :birthday, who left on :date. Reopen the membership there instead of entering them again — a new record loses the years they were in the club.', [
                'name' => $member->first_name.' '.$member->surname,
                'birthday' => formatDate($member->birthday),
                'date' => formatDate($membership->to),
            ]);
        }

        return __('There is already a :name, born :birthday, in the club. Confirm below if this really is a different person.', [
            'name' => $member->first_name.' '.$member->surname,
            'birthday' => formatDate($member->birthday),
        ]);
    }

    /**
     * @return array{id: int, name: string, member_id: int, href: string}
     */
    private function duplicateDetails(Member $member): array
    {
        return [
            'id' => $member->id,
            'name' => $member->first_name.' '.$member->surname,
            'member_id' => $member->member_id,
            'href' => route('members.show', $member, absolute: false),
        ];
    }

    private function latestMembership(Member $member): ?ClubMember
    {
        return $member->memberships()
            ->orderByPivot('from', 'desc')
            ->first()?->pivot;
    }

    public function show(Request $request, Member $member): Response
    {
        $member->load(['memberships', 'sections', 'roles', 'subscriptions', 'events', 'items']);

        $modifiable = $request->user()->can('update', $member);
        // Subscriptions are a treasurer's business, same rule the index uses.
        $showsFinances = (bool) $request->user()->hasAdminRights();
        $usesItems = (bool) currentClub()->use_items;

        return Inertia::render('members/Show', [
            'member' => [
                ...$this->readableAttributes($member),
                'memberships' => $this->rangeRows($member->memberships),
                'sections' => $this->rangeRows($member->sections, 'section_id'),
                'roles' => $this->rangeRows($member->roles, 'role_id'),
                'items' => $usesItems ? $this->rangeRows($member->items, 'item_id') : [],
                'events' => array_values($member->events
                    ->map(fn (Event $event): array => [
                        'id' => $event->pivot->id,
                        'event_id' => $event->id,
                        'name' => $event->name,
                        'date' => $event->pivot->date->format('Y-m-d'),
                        'date_label' => formatDate($event->pivot->date),
                        'memo' => $event->pivot->memo,
                    ])
                    ->all()),
                'subscriptions' => $showsFinances ? array_values($member->subscriptions
                    ->map(fn (Subscription $subscription): array => [
                        'id' => $subscription->pivot->id,
                        'subscription_id' => $subscription->id,
                        'name' => $subscription->name,
                        'amount_label' => $subscription->amountLabel(),
                        'memo' => $subscription->pivot->memo,
                    ])
                    ->all()) : [],
            ],
            'modifiable' => $modifiable,
            'showsFinances' => $showsFinances,
            'usesItems' => $usesItems,
            // Only what the dialogs need to offer, and only for somebody who
            // may actually change something.
            'options' => $modifiable ? [
                'sections' => $this->options(Section::query()->orderBy('name')),
                'roles' => $this->options(Role::query()->orderBy('name')),
                'events' => $this->options(Event::query()->orderBy('name')),
                'subscriptions' => $showsFinances
                    ? $this->options(Subscription::query()->orderBy('amount')->orderBy('name'))
                    : [],
                'items' => $usesItems ? $this->options(Item::query()->orderBy('name')) : [],
            ] : null,
            'today' => now()->format('Y-m-d'),
            'backQuery' => $this->backQuery($request),
        ]);
    }

    public function edit(Request $request, Member $member): Response
    {
        return Inertia::render('members/Edit', [
            ...$this->formOptions(),
            'member' => [
                'id' => $member->id,
                'member_id' => $member->member_id,
                'surname' => $member->surname,
                'first_name' => $member->first_name,
                'gender' => $member->gender->value,
                'birthday' => $member->birthday->format('Y-m-d'),
                'death_day' => $member->death_day?->format('Y-m-d'),
                'street' => $member->street,
                'zipcode' => $member->zipcode,
                'city' => $member->city,
                'email' => $member->email,
                'phone' => $member->phone,
                'bank' => $member->bank,
                'account_owner' => $member->account_owner,
                'iban' => $member->iban,
                'bic' => $member->bic,
                'memo' => $member->memo,
                'full_name' => $member->fullName(),
            ],
            // Only somebody with an open membership can be resigned; for
            // everybody else the button would do nothing.
            'resignable' => $member->isMember(),
            // The picker's own floor, so the limit MemberResignRequest
            // enforces is visible before the form is sent.
            'earliestResignation' => $member->lastOpenStart()?->addDay()->format('Y-m-d'),
            'deletable' => $request->user()->can('delete', $member),
            'accountSources' => $this->accountSources($member),
            'today' => now()->format('Y-m-d'),
            'backQuery' => $this->backQuery($request),
        ]);
    }

    public function update(MemberUpdateRequest $request, Member $member): RedirectResponse
    {
        $member->update($this->memberAttributes($request->validated()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member updated.')]);

        return to_route('members.index', $this->backQuery($request));
    }

    /**
     * End the membership: close every open club membership and every open
     * section on the given date. The member and their history stay — this is
     * the normal way somebody leaves, as opposed to destroy().
     */
    public function resign(MemberResignRequest $request, Member $member): RedirectResponse
    {
        $date = $request->validated()['date'];

        $member->memberships()->wherePivotNull('to')->updateExistingPivot(
            $member->memberships()->wherePivotNull('to')->pluck('clubs.id')->all(),
            ['to' => $date]
        );

        $member->sections()->wherePivotNull('to')->updateExistingPivot(
            $member->sections()->wherePivotNull('to')->pluck('sections.id')->all(),
            ['to' => $date]
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Membership ended.')]);

        // To the member page, not back to the edit form and not to the list.
        // The list would usually no longer contain them — the default
        // selection is current members — which reads as though something went
        // wrong. The member page shows the closed ranges instead, which is
        // exactly what just happened.
        return to_route('members.show', [$member, ...$this->backQuery($request)]);
    }

    public function destroy(Request $request, Member $member): RedirectResponse
    {
        $member->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member deleted.')]);

        return to_route('members.index', $this->backQuery($request));
    }

    /**
     * The years the key date may be set to: this one back to YEARS_BACK.
     *
     * @return list<array{id: int, name: string}>
     */
    private function yearOptions(): array
    {
        $current = now()->year;

        return array_map(
            fn (int $year): array => ['id' => $year, 'name' => (string) $year],
            range($current, $current - self::YEARS_BACK)
        );
    }

    /**
     * The member's own columns out of a validated payload — the entry fields
     * belong to pivots and must not reach Member::create().
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function memberAttributes(array $validated): array
    {
        unset($validated['entry_date'], $validated['section_id'], $validated['subscription_id']);

        // Stored grouped in fours like the club's own, so a hand-typed IBAN
        // and one pasted from a bank statement end up identical.
        if (($validated['iban'] ?? null) !== null && $validated['iban'] !== '') {
            $validated['iban'] = normalizeIban($validated['iban']);
        }

        return $validated;
    }

    /**
     * The choices both member forms need.
     *
     * No payment methods any more: the form has no picker since 2026-08-28,
     * the bank details decide. `PaymentMethod::options()` is still used, but
     * only by the member list's selection.
     *
     * @return array{genders: list<array{id: string, name: string}>}
     */
    private function formOptions(): array
    {
        return [
            'genders' => Gender::options(),
        ];
    }

    /**
     * Members whose bank details can be copied into the form.
     *
     * A family shares one account, so the same four fields get typed again
     * for every child. The label is name plus IBAN, exactly as
     * DebitController::memberOptions() writes it — the treasurer picks by
     * account, and two households can carry the same surname.
     *
     * Current members only, unlike the debit picker: a list that also carried
     * everybody who ever left, plus the dead, ran to 375 entries in club 1 and
     * was unusable. It is 218 this way, and the form narrows that to the
     * matching surname — a median of one, seven at the most.
     *
     * The surname filter is deliberately not applied here. The create form has
     * no surname until it is typed, and on the edit form the field can still
     * be corrected; the list has to follow either way, which only the client
     * can do.
     *
     * All four fields travel with the option rather than being fetched on
     * demand. They are already reachable from this page, which is admin-only
     * (MemberPolicy::update), and the debit form has shipped the club's IBANs
     * the same way since it was ported.
     *
     * @return list<array{id: int, surname: string, name: string, bank: string|null, account_owner: string|null, iban: string|null, bic: string|null}>
     */
    private function accountSources(?Member $except = null): array
    {
        // array_values, not Collection::all(): an Eloquent collection is keyed
        // by position but typed as a map, and only array_values() is a list.
        // Same shape as DebitController::memberOptions(), which hydrates the
        // club's account holders the same way.
        return array_values(Member::query()
            ->members()
            ->hasAccount()
            ->when($except, fn (Builder $query, Member $member) => $query->whereKeyNot($member->id))
            ->orderBy('surname')
            ->orderBy('first_name')
            ->get(['id', 'surname', 'first_name', 'bank', 'account_owner', 'iban', 'bic'])
            ->map(fn (Member $member): array => [
                'id' => $member->id,
                // Matched against the surname in the form, so the picker can
                // narrow itself down as that field is typed or corrected.
                'surname' => $member->surname,
                'name' => "{$member->surname} {$member->first_name} (".normalizeIban($member->iban).')',
                'bank' => $member->bank,
                'account_owner' => $member->account_owner,
                'iban' => $member->iban,
                'bic' => $member->bic,
            ])
            ->all());
    }

    /**
     * A member's own columns as the detail page shows them, already formatted.
     *
     * @return array<string, mixed>
     */
    private function readableAttributes(Member $member): array
    {
        return [
            'id' => $member->id,
            'member_id' => $member->member_id,
            'full_name' => $member->fullName(),
            'gender' => $member->gender->label(),
            'birthday' => formatDate($member->birthday),
            'death_day' => formatDate($member->death_day),
            'age' => $member->age,
            'address' => "{$member->street}, {$member->zipcode} {$member->city}",
            'email' => $member->email,
            'phone' => $member->phone,
            'memo' => $member->memo,
            'entry' => formatDate($member->entry()),
            'membership_years' => $member->membershipYears(),
            'is_member' => $member->isMember(),
            'payment_method' => $member->payment_method->label(),
            'bank' => $member->bank,
            'account_owner' => $member->account_owner,
            'iban' => $member->iban,
            'bic' => $member->bic,
        ];
    }

    /**
     * Pivot rows carrying a from/to range, as the member page lists and edits
     * them.
     *
     * The four relations that have one all name their row `name` and all carry
     * from/to/memo, so a single mapper covers them. The union is a template
     * bound rather than the declared type because Eloquent's Collection is
     * invariant in TModel: `Collection<int, Club>` is not a
     * `Collection<int, Club|Item|Role|Section>`.
     *
     * `id` is the pivot row's own key, not the related row's: the same section
     * or role may be held twice over different ranges, so that is the only
     * thing that addresses a row. `$foreignKey` names the related column the
     * edit dialog preselects; memberships have none, the club is implicit.
     *
     * @template TRelated of Club|Item|Role|Section
     *
     * @param  Collection<int, TRelated>  $related
     * @return list<array{id: int, related_id: int|null, name: string, range: string, from: string, to: string|null, memo: string|null}>
     */
    private function rangeRows(Collection $related, ?string $foreignKey = null): array
    {
        return array_values($related
            ->map(fn (Club|Item|Role|Section $model): array => [
                'id' => $model->pivot->id,
                'related_id' => $foreignKey === null ? null : $model->id,
                'name' => $model->name,
                // An open range reads "seit 01.03.2024" rather than
                // getRange()'s trailing dash. getRange() itself is left alone:
                // four pivot models and a test depend on that form.
                'range' => $model->pivot->to === null
                    ? __('since :date', ['date' => formatDate($model->pivot->from)])
                    : getRange(
                        $model->pivot->from->toDateString(),
                        $model->pivot->to->toDateString()
                    ),
                'from' => $model->pivot->from->format('Y-m-d'),
                'to' => $model->pivot->to?->format('Y-m-d'),
                'memo' => $model->pivot->memo,
            ])
            ->all());
    }

    /**
     * @template TModel of Event|Item|Role|Section|Subscription
     *
     * @param  Builder<TModel>  $query
     * @return list<array{id: int, name: string}>
     */
    private function options(Builder $query): array
    {
        return array_values($query->get(['id', 'name'])
            ->map(fn (Event|Item|Role|Section|Subscription $model): array => [
                'id' => $model->id,
                'name' => $model->name,
            ])
            ->all());
    }

    /**
     * The list state to return to, so Cancel and Save land back on the page,
     * selection and key date the user came from.
     *
     * @return array<string, string|int>
     */
    private function backQuery(Request $request): array
    {
        return array_filter([
            'page' => $request->integer('page') ?: null,
            'search' => $request->string('search')->trim()->toString() ?: null,
            'filter' => $request->string('filter')->toString() ?: null,
            'sort' => $request->string('sort')->toString() ?: null,
            'year' => $request->integer('year') ?: null,
        ], fn ($value): bool => $value !== null);
    }
}
