<?php

namespace App\Http\Controllers;

use App\Http\Requests\ItemStoreRequest;
use App\Http\Requests\ItemUpdateRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    private const int PER_PAGE = 15;

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        return Inertia::render('items/Index', [
            'items' => ItemResource::collection(
                Item::query()
                    ->withCount('members')
                    ->when($search !== '', fn (Builder $query) => $query
                        ->where('name', 'like', "%{$search}%"))
                    ->orderBy('name')
                    ->orderBy('id')
                    ->paginate(self::PER_PAGE)
                    ->withQueryString()
            ),
            'filters' => ['search' => $search],
            'canCreate' => $request->user()->can('create', Item::class),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('items/Create');
    }

    public function store(ItemStoreRequest $request): RedirectResponse
    {
        $item = Item::create([
            ...$request->validated(),
            'club_id' => currentClubId(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item created.')]);

        return to_route('items.index', ['page' => $this->pageOf($item)]);
    }

    public function edit(Request $request, Item $item): Response
    {
        return Inertia::render('items/Edit', [
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
            ],
            'deletable' => $request->user()->can('delete', $item),
            'backPage' => $request->integer('page') ?: null,
            'backSearch' => $request->string('search')->trim()->toString() ?: null,
        ]);
    }

    public function update(ItemUpdateRequest $request, Item $item): RedirectResponse
    {
        $item->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item updated.')]);

        return to_route('items.index', ['page' => $this->pageOf($item)]);
    }

    public function destroy(Item $item): RedirectResponse
    {
        $page = $this->pageOf($item);

        $item->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item deleted.')]);

        return to_route('items.index', ['page' => min($page, $this->lastPage())]);
    }

    /**
     * The index page on which the given item appears.
     */
    private function pageOf(Item $item): int
    {
        $position = Item::query()
            ->where(fn (Builder $query) => $query
                ->where('name', '<', $item->name)
                ->orWhere(fn (Builder $inner) => $inner
                    ->where('name', $item->name)
                    ->where('id', '<=', $item->id)))
            ->count();

        return max(1, (int) ceil($position / self::PER_PAGE));
    }

    /**
     * The last page of the item index for the current club.
     */
    private function lastPage(): int
    {
        return max(1, (int) ceil(Item::query()->count() / self::PER_PAGE));
    }
}
