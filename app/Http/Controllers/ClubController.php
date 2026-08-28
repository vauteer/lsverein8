<?php

namespace App\Http\Controllers;

use App\Enums\ClubDisplay;
use App\Enums\ClubRole;
use App\Enums\Locale;
use App\Http\Requests\ClubStoreRequest;
use App\Http\Requests\ClubUpdateRequest;
use App\Http\Resources\ClubResource;
use App\Models\Club;
use App\Models\Subscription;
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
                    ->withCount('users')
                    ->withCurrentMemberCount()
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
        $club = Club::create($this->attributes($request));

        $this->applyLogo($club, $request);

        // Without this the creator could not reach the new club at all: it has
        // no users, so nobody but a root account could ever switch into it.
        $request->user()->clubs()->attach($club->id, ['role' => ClubRole::Admin->value]);

        // And without a subscription the club could not take its first member:
        // one is required, and subscriptions have no installation-wide rows to
        // fall back on the way sections do (`subscriptions.club_id` is NOT
        // NULL). A 0 € one is the safe default — it bills nobody anything, and
        // the admin renames or replaces it once the real fees are known.
        Subscription::create([
            'club_id' => $club->id,
            'name' => __('Exempt'),
            'amount' => 0,
        ]);

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
                    'account_owner', 'iban', 'bic', 'sepa',
                    'honor_years',
                ]),
                'display' => $club->display->value,
                'locale' => $club->locale->value,
                'sepa_date' => $club->sepa_date?->format('Y-m-d'),
                'blsv_member' => (bool) $club->blsv_member,
                'use_items' => (bool) $club->use_items,
                'logo_url' => $club->logoURL(),
                'has_logo' => $club->logo !== null,
            ],
            'deletable' => $request->user()->can('delete', $club),
            // A root account reaches this page from the list and goes back to
            // it; a club admin has no list to go back to.
            'listable' => $request->user()->can('viewAny', Club::class),
        ]);
    }

    public function update(ClubUpdateRequest $request, Club $club): RedirectResponse
    {
        $club->update($this->attributes($request));

        $this->applyLogo($club, $request);

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
     * The column values, with the uploaded file kept out. `logo` is fillable
     * and the validated payload holds an UploadedFile, so mass-assigning it
     * would put the object into the column.
     *
     * Defensive rather than load-bearing today: applyLogo() runs straight
     * after and overwrites the column with the real filename, so dropping
     * this except() breaks no test. It stays because that masking depends
     * entirely on the call order, and the write is wrong on its own terms.
     *
     * @return array<string, mixed>
     */
    private function attributes(ClubStoreRequest|ClubUpdateRequest $request): array
    {
        $validated = $request->safe()->except(['logo', 'remove_logo']);

        return [
            ...$validated,
            'iban' => normalizeIban($validated['iban']),
        ];
    }

    /**
     * Store, replace or clear the club logo, then sweep whatever file that
     * left behind. Removing wins over a file sent in the same request.
     */
    private function applyLogo(Club $club, ClubStoreRequest|ClubUpdateRequest $request): void
    {
        if ($request->boolean('remove_logo')) {
            $club->update(['logo' => null]);
        } elseif ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $filename = $file->hashName();
            Club::logoDisk()->putFileAs('logo', $file, $filename);
            $club->update(['logo' => $filename]);
        }

        Club::removeOrphanLogos();
    }

    /**
     * @return array{displayStyles: list<array{id: int, name: string}>, languages: list<array{id: string, name: string}>}
     */
    private function formOptions(): array
    {
        return [
            'displayStyles' => ClubDisplay::options(),
            'languages' => Locale::options(),
        ];
    }
}
