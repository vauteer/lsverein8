<?php

namespace App\Http\Controllers;

use App\Enums\Gender;
use App\Enums\MemberFilter;
use App\Enums\MemberSort;
use App\Enums\PaymentMethod;
use App\Http\Requests\MemberResignRequest;
use App\Http\Requests\MemberStoreRequest;
use App\Http\Requests\MemberUpdateRequest;
use App\Http\Resources\MemberResource;
use App\Models\Club;
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
    private const int PER_PAGE = 15;

    /**
     * How far back the year picker reaches. The key date drives every age and
     * membership calculation, so "how did the club look in 2019" is a
     * question the list can answer.
     */
    private const int YEARS_BACK = 10;

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
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('members/Create', [
            ...$this->formOptions(),
            'sections' => $this->options(Section::query()->orderBy('name')),
            'subscriptions' => $this->options(Subscription::query()->orderBy('name')),
            'today' => now()->format('Y-m-d'),
            'backQuery' => $this->backQuery($request),
        ]);
    }

    public function store(MemberStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $clubId = currentClubId();

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

        return to_route('members.index', $this->backQuery($request));
    }

    public function show(Request $request, Member $member): Response
    {
        $member->load(['memberships', 'sections', 'roles', 'subscriptions', 'events', 'items']);

        return Inertia::render('members/Show', [
            'member' => [
                ...$this->readableAttributes($member),
                'memberships' => $this->rangeRows($member->memberships),
                'sections' => $this->rangeRows($member->sections),
                'roles' => $this->rangeRows($member->roles),
                'items' => $this->rangeRows($member->items),
                'events' => array_values($member->events
                    ->map(fn (Event $event): array => [
                        'name' => $event->name,
                        'date' => formatDate($event->pivot->date),
                        'memo' => $event->pivot->memo,
                    ])
                    ->all()),
                'subscriptions' => array_values($member->subscriptions
                    ->map(fn (Subscription $subscription): array => [
                        'name' => $subscription->name,
                        'amount_label' => $subscription->amountLabel(),
                        'memo' => $subscription->pivot->memo,
                    ])
                    ->all()),
            ],
            'modifiable' => $request->user()->can('update', $member),
            'showsFinances' => (bool) $request->user()->hasAdminRights(),
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
                'payment_method' => $member->payment_method->value,
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
            'deletable' => $request->user()->can('delete', $member),
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
     * section on the given date. The member and their history stay.
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

        return to_route('members.edit', [$member, ...$this->backQuery($request)]);
    }

    public function destroy(Request $request, Member $member): RedirectResponse
    {
        $member->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member deleted.')]);

        return to_route('members.index', $this->backQuery($request));
    }

    /**
     * Everything the index needs to answer "which members, seen from when, in
     * what order".
     *
     * Setting `Member::$_keyDate` here is the load-bearing side effect: every
     * age, membership and honour calculation downstream reads it, including
     * the ones inside MemberResource.
     *
     * @return array{query: Builder<Member>, search: string, filter: string, sort: MemberSort, year: int}
     */
    private function selection(Request $request): array
    {
        $search = $request->string('search')->trim()->toString();
        $filter = $request->string('filter')->toString() ?: MemberFilter::Members->value;
        $sort = MemberSort::tryFrom($request->string('sort')->toString()) ?? MemberSort::Name;
        $year = $this->resolveYear($request);

        Member::$_keyDate = $year === now()->year
            ? now()->endOfDay()
            : Date::create($year, 12, 31)->endOfDay();

        $query = Member::query();

        if ($search !== '') {
            $query->like($search);
        }

        $this->applyFilter($filter, $query, $request);
        $sort->apply($query);

        return [
            'query' => $query,
            'search' => $search,
            'filter' => $filter,
            'sort' => $sort,
            'year' => $year,
        ];
    }

    /**
     * Narrow the query to the chosen selection.
     *
     * A filter naming a row that no longer exists, or one a non-admin is not
     * offered, falls back to the default rather than 404ing: these values live
     * in bookmarks and in the back button.
     *
     * @param  Builder<Member>  $query
     */
    private function applyFilter(string $filter, Builder $query, Request $request): void
    {
        $fixed = MemberFilter::tryFrom($filter);

        if ($fixed !== null && $fixed->isVisibleTo($request->user())) {
            $fixed->apply($query);

            return;
        }

        if (preg_match('/^(section|role|ever_role|event|item|ever_item|subscription|payment)_(.+)$/', $filter, $match) !== 1) {
            MemberFilter::Members->apply($query);

            return;
        }

        [, $kind, $key] = $match;
        $id = (int) $key;

        match ($kind) {
            'section' => $query->members()->inSections($id),
            'role' => $query->members()->hasRole($id),
            'ever_role' => $query->everRole($id),
            'event' => $query->hadEvent($id),
            'item' => $query->members()->hasItem($id),
            'ever_item' => $query->members()->everItem($id),
            'subscription' => $query->members()->hasSubscription($id),
            'payment' => $this->applyPaymentFilter($key, $query),
        };
    }

    /**
     * @param  Builder<Member>  $query
     */
    private function applyPaymentFilter(string $key, Builder $query): void
    {
        $method = PaymentMethod::tryFrom($key);

        if ($method === null) {
            MemberFilter::Members->apply($query);

            return;
        }

        $query->members()->paymentMethods($method);
    }

    /**
     * The selections built from the club's own rows: one per section, role,
     * honour, item, subscription and payment method.
     *
     * lsverein7 had these too but never listed them — they only worked if you
     * typed `?filter=hasSection_3` by hand, or arrived from a link elsewhere
     * in the app. The section block was commented out in its source.
     *
     * @return list<array{id: string, name: string}>
     */
    private function dynamicFilters(): array
    {
        $options = [];

        foreach (Section::query()->orderBy('name')->get() as $section) {
            $options[] = ['id' => "section_{$section->id}", 'name' => __('Section: :name', ['name' => $section->name])];
        }

        foreach (Role::query()->orderBy('name')->get() as $role) {
            $options[] = ['id' => "role_{$role->id}", 'name' => __('Role: :name (current)', ['name' => $role->name])];
            $options[] = ['id' => "ever_role_{$role->id}", 'name' => __('Role: :name (ever)', ['name' => $role->name])];
        }

        foreach (Event::query()->orderBy('name')->get() as $event) {
            $options[] = ['id' => "event_{$event->id}", 'name' => __('Honour: :name', ['name' => $event->name])];
        }

        foreach (Subscription::query()->orderBy('name')->get() as $subscription) {
            $options[] = ['id' => "subscription_{$subscription->id}", 'name' => __('Subscription: :name', ['name' => $subscription->name])];
        }

        // Only where the club keeps an inventory at all, matching ItemPolicy.
        if (currentClub()->use_items) {
            foreach (Item::query()->orderBy('name')->get() as $item) {
                $options[] = ['id' => "item_{$item->id}", 'name' => __('Item: :name (current)', ['name' => $item->name])];
                $options[] = ['id' => "ever_item_{$item->id}", 'name' => __('Item: :name (ever)', ['name' => $item->name])];
            }
        }

        foreach (PaymentMethod::cases() as $method) {
            $options[] = ['id' => "payment_{$method->value}", 'name' => __('Pays by: :name', ['name' => $method->label()])];
        }

        return $options;
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

    private function resolveYear(Request $request): int
    {
        $year = $request->integer('year') ?: now()->year;
        $current = now()->year;

        return max($current - self::YEARS_BACK, min($current, $year));
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
     * @return array{genders: list<array{id: string, name: string}>, paymentMethods: list<array{id: string, name: string}>}
     */
    private function formOptions(): array
    {
        return [
            'genders' => Gender::options(),
            'paymentMethods' => PaymentMethod::options(),
        ];
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
     * Pivot rows carrying a from/to range, as the detail page lists them.
     *
     * The four relations that have one all name their row `name` and all carry
     * from/to/memo, so a single mapper covers them. The union is a template
     * bound rather than the declared type because Eloquent's Collection is
     * invariant in TModel: `Collection<int, Club>` is not a
     * `Collection<int, Club|Item|Role|Section>`.
     *
     * @template TRelated of Club|Item|Role|Section
     *
     * @param  Collection<int, TRelated>  $related
     * @return list<array{name: string, range: string, memo: string|null}>
     */
    private function rangeRows(Collection $related): array
    {
        return array_values($related
            ->map(fn (Club|Item|Role|Section $model): array => [
                'name' => $model->name,
                'range' => getRange(
                    $model->pivot->from->toDateString(),
                    $model->pivot->to?->toDateString()
                ),
                'memo' => $model->pivot->memo,
            ])
            ->all());
    }

    /**
     * @param  Builder<Section>|Builder<Subscription>  $query
     * @return list<array{id: int, name: string}>
     */
    private function options(Builder $query): array
    {
        return array_values($query->get(['id', 'name'])
            ->map(fn (Section|Subscription $model): array => [
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
