<?php

namespace App\Http\Controllers;

use App\Http\Requests\SectionStoreRequest;
use App\Http\Requests\SectionUpdateRequest;
use App\Http\Resources\SectionResource;
use App\Models\Section;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SectionController extends Controller
{
    private const int PER_PAGE = 15;

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        return Inertia::render('sections/Index', [
            'sections' => SectionResource::collection(
                Section::query()
                    ->withCurrentMemberCount()
                    ->when($search !== '', fn (Builder $query) => $query
                        ->where('name', 'like', "%{$search}%"))
                    ->orderBy('name')
                    ->orderBy('id')
                    ->paginate(self::PER_PAGE)
                    ->withQueryString()
            ),
            'filters' => ['search' => $search],
            'canCreate' => $request->user()->can('create', Section::class),
            'blsv' => currentClub()->blsv_member,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('sections/Create', $this->formOptions());
    }

    public function store(SectionStoreRequest $request): RedirectResponse
    {
        $section = Section::create([
            ...$request->validated(),
            'club_id' => currentClubId(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Section created.')]);

        return to_route('sections.index', ['page' => $this->pageOf($section)]);
    }

    public function edit(Request $request, Section $section): Response
    {
        return Inertia::render('sections/Edit', [
            ...$this->formOptions(),
            'section' => [
                'id' => $section->id,
                'name' => $section->name,
                'blsv_id' => $section->blsv_id,
            ],
            'deletable' => $request->user()->can('delete', $section),
            'backPage' => $request->integer('page') ?: null,
            'backSearch' => $request->string('search')->trim()->toString() ?: null,
        ]);
    }

    public function update(SectionUpdateRequest $request, Section $section): RedirectResponse
    {
        $section->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Section updated.')]);

        return to_route('sections.index', ['page' => $this->pageOf($section)]);
    }

    public function destroy(Section $section): RedirectResponse
    {
        $page = $this->pageOf($section);

        $section->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Section deleted.')]);

        return to_route('sections.index', ['page' => min($page, $this->lastPage())]);
    }

    /**
     * The BLSV section numbers are only offered to clubs that are a member of
     * the BLSV; for everybody else the field is hidden and prohibited.
     *
     * @return array{blsvSections: list<array{id: int|string, name: string}>|null}
     */
    private function formOptions(): array
    {
        return [
            'blsvSections' => currentClub()->blsv_member
                ? optionsFromArray(Section::BLSV_SECTIONS)
                : null,
        ];
    }

    /**
     * The index page on which the given section appears.
     */
    private function pageOf(Section $section): int
    {
        $position = Section::query()
            ->where(fn (Builder $query) => $query
                ->where('name', '<', $section->name)
                ->orWhere(fn (Builder $inner) => $inner
                    ->where('name', $section->name)
                    ->where('id', '<=', $section->id)))
            ->count();

        return max(1, (int) ceil($position / self::PER_PAGE));
    }

    /**
     * The last page of the section index for the current club.
     */
    private function lastPage(): int
    {
        return max(1, (int) ceil(Section::query()->count() / self::PER_PAGE));
    }
}
