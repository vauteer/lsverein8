<?php

namespace App\Http\Controllers\Members;

use App\Http\Controllers\Controller;
use App\Http\Requests\Members\MemberItemRequest;
use App\Models\ItemMember;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The inventory issued to a member for a period. Only reachable where the club
 * keeps an inventory at all; the routes carry ItemPolicy for that.
 *
 * Every action redirects back rather than to a named route: the caller is the
 * member page, and `back()` re-renders it with its list state intact.
 */
class MemberItemController extends Controller
{
    public function store(MemberItemRequest $request, Member $member): RedirectResponse
    {
        $member->items()->attach($request->validated()['item_id'], $request->safe()->except('item_id'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item issued.')]);

        return back();
    }

    public function update(MemberItemRequest $request, Member $member, int $row): RedirectResponse
    {
        $this->rowOf($member, $row)->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Issued item updated.')]);

        return back();
    }

    public function destroy(Member $member, int $row): RedirectResponse
    {
        $this->rowOf($member, $row)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item returned.')]);

        return back();
    }

    /**
     * The pivot row, constrained to this member.
     *
     * Route model binding is deliberately not used for it: binding resolves by
     * primary key alone, so another member's row would be found and edited.
     */
    private function rowOf(Member $member, int $row): ItemMember
    {
        return ItemMember::query()->where('member_id', $member->id)->findOrFail($row);
    }
}
