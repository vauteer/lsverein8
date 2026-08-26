<?php

namespace App\Http\Controllers\Members;

use App\Http\Controllers\Controller;
use App\Http\Requests\Members\MemberEventRequest;
use App\Models\EventMember;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The honours awarded to a member. A single date, not a range — an honour is
 * given on a day and kept.
 *
 * Every action redirects back rather than to a named route: the caller is the
 * member page, and `back()` re-renders it with its list state intact.
 */
class MemberEventController extends Controller
{
    public function store(MemberEventRequest $request, Member $member): RedirectResponse
    {
        $member->events()->attach($request->validated()['event_id'], $request->safe()->except('event_id'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Honour added.')]);

        return back();
    }

    public function update(MemberEventRequest $request, Member $member, int $row): RedirectResponse
    {
        $this->rowOf($member, $row)->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Honour updated.')]);

        return back();
    }

    public function destroy(Member $member, int $row): RedirectResponse
    {
        $this->rowOf($member, $row)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Honour removed.')]);

        return back();
    }

    /**
     * The pivot row, constrained to this member.
     *
     * Route model binding is deliberately not used for it: binding resolves by
     * primary key alone, so another member's row would be found and edited.
     */
    private function rowOf(Member $member, int $row): EventMember
    {
        return EventMember::query()->where('member_id', $member->id)->findOrFail($row);
    }
}
