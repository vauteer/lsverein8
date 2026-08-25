<?php

namespace App\Http\Controllers;

use App\Enums\ClubRole;
use App\Http\Requests\ClubStoreRequest;
use App\Http\Requests\ClubUpdateRequest;
use App\Http\Resources\ClubResource;
use App\Models\Club;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClubController extends Controller
{
    private const int PER_PAGE = 15;

    /**
     * The whole installation, so root-only (see ClubPolicy). A club admin has
     * no list; they edit their current club directly.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        return Inertia::render('clubs/Index', [
            'clubs' => ClubResource::collection(
                Club::query()
                    ->withCount(['members', 'users'])
                    ->when($search !== '', fn (Builder $query) => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%"))
                    ->orderBy('name')
                    ->paginate(self::PER_PAGE)
                    ->withQueryString()
            ),
            'filters' => ['search' => $search],
            'canCreate' => $request->user()->can('create', Club::class),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('clubs/Create', $this->formOptions());
    }

    public function store(ClubStoreRequest $request): RedirectResponse
    {
        $club = Club::create([
            ...$request->validated(),
            'iban' => normalizeIban($request->validated()['iban']),
        ]);

        // Without this the creator could not reach the new club at all: it has
        // no users, so nobody but a root account could ever switch into it.
        $request->user()->clubs()->attach($club->id, ['role' => ClubRole::Admin->value]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Club created.')]);

        return to_route('clubs.index');
    }

    public function edit(Request $request, Club $club): Response
    {
        return Inertia::render('clubs/Edit', [
            ...$this->formOptions(),
            'club' => [
                ...$club->only([
                    'id', 'name', 'street', 'zipcode', 'city', 'bank',
                    'account_owner', 'iban', 'bic', 'sepa', 'display',
                    'locale', 'honor_years',
                ]),
                'sepa_date' => $club->sepa_date?->format('Y-m-d'),
                'blsv_member' => (bool) $club->blsv_member,
                'use_items' => (bool) $club->use_items,
                'logo_url' => $club->logoURL(),
            ],
            'deletable' => $request->user()->can('delete', $club),
            // A root account reaches this page from the list and goes back to
            // it; a club admin has no list to go back to.
            'listable' => $request->user()->can('viewAny', Club::class),
        ]);
    }

    public function update(ClubUpdateRequest $request, Club $club): RedirectResponse
    {
        $club->update([
            ...$request->validated(),
            'iban' => normalizeIban($request->validated()['iban']),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Club updated.')]);

        return $request->user()->can('viewAny', Club::class)
            ? to_route('clubs.index')
            : to_route('clubs.edit', $club);
    }

    public function destroy(Club $club): RedirectResponse
    {
        $club->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Club deleted.')]);

        return to_route('clubs.index');
    }

    /**
     * @return array{displayStyles: list<array{id: int|string, name: string}>, languages: list<array{id: int|string, name: string}>}
     */
    private function formOptions(): array
    {
        return [
            'displayStyles' => optionsFromArray(Club::displayStyles(), false),
            'languages' => optionsFromArray(Club::languages(), false),
        ];
    }
}
