<?php

namespace App\Http\Controllers\Members;

use App\Http\Controllers\Controller;
use App\Http\Requests\Members\MembershipRequest;
use App\Models\ClubMember;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Periods of club membership. Eight members in production have more than one,
 * because they left and rejoined — Member::membershipYears() sums them.
 *
 * Every action redirects back rather than to a named route: the caller is the
 * member page, and `back()` re-renders it with its list state intact.
 */
class MembershipController extends Controller
{
    public function store(MembershipRequest $request, Member $member): RedirectResponse
    {
        $member->memberships()->attach(currentClubId(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Membership added.')]);

        return back();
    }

    public function update(MembershipRequest $request, Member $member, int $row): RedirectResponse
    {
        $this->rowOf($member, $row)->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Membership updated.')]);

        return back();
    }

    public function destroy(Member $member, int $row): RedirectResponse
    {
        $this->rowOf($member, $row)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Membership removed.')]);

        return back();
    }

    /**
     * The pivot row, constrained to this member.
     *
     * Route model binding is deliberately not used for it: binding resolves by
     * primary key alone, so another member's row would be found and edited.
     */
    private function rowOf(Member $member, int $row): ClubMember
    {
        return ClubMember::query()->where('member_id', $member->id)->findOrFail($row);
    }
}
