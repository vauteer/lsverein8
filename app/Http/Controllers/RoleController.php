<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleStoreRequest;
use App\Http\Requests\RoleUpdateRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    private const int PER_PAGE = 15;

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        return Inertia::render('roles/Index', [
            'roles' => RoleResource::collection(
                Role::query()
                    ->withCount('members')
                    ->when($search !== '', fn (Builder $query) => $query
                        ->where('name', 'like', "%{$search}%"))
                    ->orderBy('name')
                    ->orderBy('id')
                    ->paginate(self::PER_PAGE)
                    ->withQueryString()
            ),
            'filters' => ['search' => $search],
            'canCreate' => $request->user()->can('create', Role::class),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('roles/Create');
    }

    public function store(RoleStoreRequest $request): RedirectResponse
    {
        $role = Role::create([
            ...$request->validated(),
            'club_id' => currentClubId(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return to_route('roles.index', ['page' => $this->pageOf($role)]);
    }

    public function edit(Request $request, Role $role): Response
    {
        return Inertia::render('roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
            ],
            'deletable' => $request->user()->can('delete', $role),
            'backPage' => $request->integer('page') ?: null,
            'backSearch' => $request->string('search')->trim()->toString() ?: null,
        ]);
    }

    public function update(RoleUpdateRequest $request, Role $role): RedirectResponse
    {
        $role->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('roles.index', ['page' => $this->pageOf($role)]);
    }

    public function destroy(Role $role): RedirectResponse
    {
        $page = $this->pageOf($role);

        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('roles.index', ['page' => min($page, $this->lastPage())]);
    }

    /**
     * The index page on which the given role appears.
     */
    private function pageOf(Role $role): int
    {
        $position = Role::query()
            ->where(fn (Builder $query) => $query
                ->where('name', '<', $role->name)
                ->orWhere(fn (Builder $inner) => $inner
                    ->where('name', $role->name)
                    ->where('id', '<=', $role->id)))
            ->count();

        return max(1, (int) ceil($position / self::PER_PAGE));
    }

    /**
     * The last page of the role index for the current club.
     */
    private function lastPage(): int
    {
        return max(1, (int) ceil(Role::query()->count() / self::PER_PAGE));
    }
}
