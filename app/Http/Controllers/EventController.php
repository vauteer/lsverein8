<?php

namespace App\Http\Controllers;

use App\Http\Requests\EventStoreRequest;
use App\Http\Requests\EventUpdateRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    private const int PER_PAGE = 15;

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        return Inertia::render('events/Index', [
            'events' => EventResource::collection(
                Event::query()
                    ->withCount('members')
                    ->when($search !== '', fn (Builder $query) => $query
                        ->where('name', 'like', "%{$search}%"))
                    ->orderBy('name')
                    ->orderBy('id')
                    ->paginate(self::PER_PAGE)
                    ->withQueryString()
            ),
            'filters' => ['search' => $search],
            'canCreate' => $request->user()->can('create', Event::class),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('events/Create');
    }

    public function store(EventStoreRequest $request): RedirectResponse
    {
        $event = Event::create([
            ...$request->validated(),
            'club_id' => currentClubId(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event created.')]);

        return to_route('events.index', ['page' => $this->pageOf($event)]);
    }

    public function edit(Request $request, Event $event): Response
    {
        return Inertia::render('events/Edit', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
            ],
            'deletable' => $request->user()->can('delete', $event),
            'backPage' => $request->integer('page') ?: null,
            'backSearch' => $request->string('search')->trim()->toString() ?: null,
        ]);
    }

    public function update(EventUpdateRequest $request, Event $event): RedirectResponse
    {
        $event->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event updated.')]);

        return to_route('events.index', ['page' => $this->pageOf($event)]);
    }

    public function destroy(Event $event): RedirectResponse
    {
        $page = $this->pageOf($event);

        $event->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event deleted.')]);

        return to_route('events.index', ['page' => min($page, $this->lastPage())]);
    }

    /**
     * The index page on which the given event appears.
     */
    private function pageOf(Event $event): int
    {
        $position = Event::query()
            ->where(fn (Builder $query) => $query
                ->where('name', '<', $event->name)
                ->orWhere(fn (Builder $inner) => $inner
                    ->where('name', $event->name)
                    ->where('id', '<=', $event->id)))
            ->count();

        return max(1, (int) ceil($position / self::PER_PAGE));
    }

    /**
     * The last page of the event index for the current club.
     */
    private function lastPage(): int
    {
        return max(1, (int) ceil(Event::query()->count() / self::PER_PAGE));
    }
}
