<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubscriptionDebitRequest;
use App\Http\Requests\SubscriptionStoreRequest;
use App\Http\Requests\SubscriptionUpdateRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    private const int PER_PAGE = 15;

    /**
     * Working days a SEPA direct debit has to be submitted ahead of its
     * execution date; carried over from lsverein7.
     */
    private const int SEPA_LEAD_DAYS = 8;

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        return Inertia::render('subscriptions/Index', [
            'subscriptions' => SubscriptionResource::collection(
                Subscription::query()
                    ->withCurrentMemberCount()
                    ->when($search !== '', fn (Builder $query) => $query
                        ->where('name', 'like', "%{$search}%"))
                    ->orderBy('amount')
                    ->orderBy('name')
                    ->paginate(self::PER_PAGE)
                    ->withQueryString()
            ),
            'filters' => ['search' => $search],
            'canCreate' => $request->user()->can('create', Subscription::class),
            ...$this->debitOptions($request),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('subscriptions/Create');
    }

    public function store(SubscriptionStoreRequest $request): RedirectResponse
    {
        $subscription = Subscription::create([
            ...$request->validated(),
            'club_id' => currentClubId(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscription created.')]);

        return to_route('subscriptions.index', ['page' => $this->pageOf($subscription)]);
    }

    public function edit(Request $request, Subscription $subscription): Response
    {
        return Inertia::render('subscriptions/Edit', [
            'subscription' => [
                'id' => $subscription->id,
                'name' => $subscription->name,
                'amount' => $subscription->amount,
                'transfer_text' => $subscription->transfer_text,
                'memo' => $subscription->memo,
            ],
            'deletable' => $request->user()->can('delete', $subscription),
            'backPage' => $request->integer('page') ?: null,
            'backSearch' => $request->string('search')->trim()->toString() ?: null,
        ]);
    }

    public function update(SubscriptionUpdateRequest $request, Subscription $subscription): RedirectResponse
    {
        $subscription->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscription updated.')]);

        return to_route('subscriptions.index', ['page' => $this->pageOf($subscription)]);
    }

    public function destroy(Subscription $subscription): RedirectResponse
    {
        $page = $this->pageOf($subscription);

        $subscription->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscription deleted.')]);

        return to_route('subscriptions.index', ['page' => min($page, $this->lastPage())]);
    }

    /**
     * Collect the selected subscriptions from the members who pay by direct
     * debit, and hand back the SEPA file plus the list of everybody who has
     * to be billed some other way.
     */
    public function debit(SubscriptionDebitRequest $request): Response
    {
        $validated = $request->validated();

        $result = Subscription::debit(
            // array_values: the ids reach Subscription::debit() as a list, and
            // array_map alone would keep gappy keys from a `subscriptions[3]`
            // style payload.
            array_values(array_map(intval(...), $validated['subscriptions'])),
            Date::parse($validated['date']),
        );

        return Inertia::render('subscriptions/Debit', [
            ...$result,
            'executionDate' => formatDate($validated['date']),
            'backPage' => $request->integer('page') ?: null,
            'backSearch' => $request->string('search')->trim()->toString() ?: null,
        ]);
    }

    /**
     * What the collection dialog on the index needs.
     *
     * The list is deliberately not the paginated one behind it: which fees get
     * collected must not depend on the page or the search the user happens to
     * be looking at.
     *
     * @return array{canDebit: bool, debitable: list<array{id: int, name: string, amount_label: string}>, freeCount: int, sepaDate: string|null}
     */
    private function debitOptions(Request $request): array
    {
        if (! $request->user()->can('debit', Subscription::class)) {
            return ['canDebit' => false, 'debitable' => [], 'freeCount' => 0, 'sepaDate' => null];
        }

        $subscriptions = Subscription::query()
            ->orderBy('amount')
            ->orderBy('name')
            ->get();

        // There is nothing to collect from a 0 € fee (honorary members), so it
        // is left out rather than offered and then refused.
        $debitable = $subscriptions->where('amount', '>', 0);

        return [
            'canDebit' => true,
            // array_values, not Collection::values(): where() leaves gaps in
            // the keys, and only array_values() actually types as a list.
            'debitable' => array_values($debitable
                ->map(fn (Subscription $subscription): array => [
                    'id' => $subscription->id,
                    'name' => $subscription->name,
                    'amount_label' => $subscription->amountLabel(),
                ])
                ->all()),
            // Only to explain the gap in the dialog, so nobody hunts for a fee
            // that is missing on purpose.
            'freeCount' => $subscriptions->count() - $debitable->count(),
            // SEPA direct debits have to be announced ahead of the execution
            // date, so the picker does not default to today.
            'sepaDate' => now()->addDays(self::SEPA_LEAD_DAYS)->format('Y-m-d'),
        ];
    }

    /**
     * The index page on which the given subscription appears.
     */
    private function pageOf(Subscription $subscription): int
    {
        $position = Subscription::query()
            ->where(fn (Builder $query) => $query
                ->where('amount', '<', $subscription->amount)
                // (club_id, name) is unique, so name fully breaks an amount
                // tie and no id level is needed to make the order stable.
                ->orWhere(fn (Builder $inner) => $inner
                    ->where('amount', $subscription->amount)
                    ->where('name', '<=', $subscription->name)))
            ->count();

        return max(1, (int) ceil($position / self::PER_PAGE));
    }

    /**
     * The last page of the subscription index for the current club.
     */
    private function lastPage(): int
    {
        return max(1, (int) ceil(Subscription::query()->count() / self::PER_PAGE));
    }
}
