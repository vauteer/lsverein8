<?php

namespace App\Http\Controllers;

use App\Http\Requests\DebitCollectRequest;
use App\Http\Requests\DebitStoreRequest;
use App\Http\Requests\DebitUpdateRequest;
use App\Http\Resources\DebitResource;
use App\Models\Debit;
use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

class DebitController extends Controller
{
    private const int PER_PAGE = 15;

    /**
     * Working days a SEPA direct debit has to be submitted ahead of its
     * execution date; the same lead time the subscription collection uses.
     */
    private const int SEPA_LEAD_DAYS = 8;

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        return Inertia::render('debits/Index', [
            'debits' => DebitResource::collection(
                Debit::query()
                    ->with('member')
                    ->when($search !== '', fn (Builder $query) => $query
                        ->where(fn (Builder $inner) => $inner
                            ->where('transfer_text', 'like', "%{$search}%")
                            // Member carries the ClubScope, so this stays
                            // inside the club without saying so again.
                            ->orWhereHas('member', fn (Builder $member) => $member
                                ->where('surname', 'like', "%{$search}%")
                                ->orWhere('first_name', 'like', "%{$search}%"))))
                    ->orderBy('due_at')
                    ->orderBy('id')
                    ->paginate(self::PER_PAGE)
                    ->withQueryString()
            ),
            'filters' => ['search' => $search],
            'canCreate' => $request->user()->can('create', Debit::class),
            ...$this->collectOptions($request),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('debits/Create', [
            'members' => $this->memberOptions(),
            'today' => now()->format('Y-m-d'),
        ]);
    }

    public function store(DebitStoreRequest $request): RedirectResponse
    {
        $debit = Debit::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Debit created.')]);

        return to_route('debits.index', ['page' => $this->pageOf($debit)]);
    }

    public function edit(Request $request, Debit $debit): Response
    {
        return Inertia::render('debits/Edit', [
            'debit' => [
                'id' => $debit->id,
                'member_id' => $debit->member_id,
                'member_name' => $debit->member->fullName(),
                'amount' => $debit->amount,
                'transfer_text' => $debit->transfer_text,
                'due_at' => $debit->due_at->format('Y-m-d'),
            ],
            'members' => $this->memberOptions(),
            'deletable' => $request->user()->can('delete', $debit),
            'backPage' => $request->integer('page') ?: null,
            'backSearch' => $request->string('search')->trim()->toString() ?: null,
        ]);
    }

    public function update(DebitUpdateRequest $request, Debit $debit): RedirectResponse
    {
        $debit->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Debit updated.')]);

        return to_route('debits.index', ['page' => $this->pageOf($debit)]);
    }

    public function destroy(Debit $debit): RedirectResponse
    {
        $page = $this->pageOf($debit);

        $debit->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Debit deleted.')]);

        return to_route('debits.index', ['page' => min($page, $this->lastPage())]);
    }

    /**
     * Collect every debit due on the execution date into one SEPA file and
     * clear the rows it took along.
     *
     * Like the subscription collection, this renders a page rather than
     * redirecting — reloading the result page starts another collection, which
     * is what lsverein7 did too. It is harmless here: the rows are gone, so
     * the second run finds nothing and is turned away below.
     */
    public function collect(DebitCollectRequest $request): Response|RedirectResponse
    {
        $executionDate = Date::parse($request->validated()['date']);

        $collected = Debit::query()->due($executionDate)->count();

        // Without this, generateSepa() would happily write a SEPA file with no
        // payments in it and offer it as a download.
        if ($collected === 0) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('No debit is due on that date.')]);

            return to_route('debits.index', [
                'page' => $request->integer('page') ?: null,
                'search' => $request->string('search')->trim()->toString() ?: null,
            ]);
        }

        return Inertia::render('debits/Collect', [
            ...Debit::debit($executionDate),
            'collected' => $collected,
            'executionDate' => formatDate($executionDate),
        ]);
    }

    /**
     * The members a debit can be booked on: this club's, with a bank account
     * on file.
     *
     * Unlike lsverein7 this is not narrowed to current members. A debit is
     * most useful for somebody who has just left, and a picker that drops the
     * member of a debit already on file cannot save that debit again.
     *
     * @return list<array{id: int, name: string}>
     */
    private function memberOptions(): array
    {
        // array_values, not Collection::all(): an Eloquent collection is keyed
        // by position but typed as a map, and only array_values() is a list.
        return array_values(Member::query()
            ->hasAccount()
            ->orderBy('surname')
            ->orderBy('first_name')
            ->get(['id', 'surname', 'first_name', 'iban'])
            ->map(fn (Member $member): array => [
                'id' => $member->id,
                // The full IBAN, not accountNumber()'s last-12-digits short
                // form: it is what the treasurer checks the picked member
                // against. normalizeIban() groups it in fours the way the club
                // form stores its own, so the display does not depend on
                // whether the row came over from lsverein7 with spaces or not.
                'name' => "{$member->surname} {$member->first_name} (".normalizeIban($member->iban).')',
            ])
            ->all());
    }

    /**
     * What the collection dialog on the index needs.
     *
     * @return array{canCollect: bool, hasDebits: bool, sepaDate: string|null}
     */
    private function collectOptions(Request $request): array
    {
        if (! $request->user()->can('debit', Debit::class)) {
            return ['canCollect' => false, 'hasDebits' => false, 'sepaDate' => null];
        }

        return [
            'canCollect' => true,
            // Deliberately the club's whole stock, not the filtered page: the
            // button must not disappear because a search found nothing.
            'hasDebits' => Debit::query()->exists(),
            // SEPA direct debits have to be announced ahead of the execution
            // date, so the picker does not default to today.
            'sepaDate' => now()->addDays(self::SEPA_LEAD_DAYS)->format('Y-m-d'),
        ];
    }

    /**
     * The index page on which the given debit appears.
     */
    private function pageOf(Debit $debit): int
    {
        $position = Debit::query()
            ->where(fn (Builder $query) => $query
                ->where('due_at', '<', $debit->due_at)
                // Nothing else about a debit is unique, so id is what makes
                // the order stable behind a shared due date.
                ->orWhere(fn (Builder $inner) => $inner
                    ->where('due_at', $debit->due_at)
                    ->where('id', '<=', $debit->id)))
            ->count();

        return max(1, (int) ceil($position / self::PER_PAGE));
    }

    /**
     * The last page of the debit index for the current club.
     */
    private function lastPage(): int
    {
        return max(1, (int) ceil(Debit::query()->count() / self::PER_PAGE));
    }
}
