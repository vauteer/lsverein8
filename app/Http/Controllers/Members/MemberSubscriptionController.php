<?php

namespace App\Http\Controllers\Members;

use App\Http\Controllers\Controller;
use App\Http\Requests\Members\MemberSubscriptionRequest;
use App\Models\Member;
use App\Models\MemberSubscription;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The subscriptions a member holds. No dates at all, which is what a collection
 * run reads to decide who owes what.
 *
 * Every action redirects back rather than to a named route: the caller is the
 * member page, and `back()` re-renders it with its list state intact.
 */
class MemberSubscriptionController extends Controller
{
    public function store(MemberSubscriptionRequest $request, Member $member): RedirectResponse
    {
        $member->subscriptions()->attach($request->validated()['subscription_id'], $request->safe()->except('subscription_id'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscription added.')]);

        return back();
    }

    public function update(MemberSubscriptionRequest $request, Member $member, int $row): RedirectResponse
    {
        $this->rowOf($member, $row)->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscription updated.')]);

        return back();
    }

    public function destroy(Member $member, int $row): RedirectResponse
    {
        $this->rowOf($member, $row)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscription removed.')]);

        return back();
    }

    /**
     * The pivot row, constrained to this member.
     *
     * Route model binding is deliberately not used for it: binding resolves by
     * primary key alone, so another member's row would be found and edited.
     */
    private function rowOf(Member $member, int $row): MemberSubscription
    {
        return MemberSubscription::query()->where('member_id', $member->id)->findOrFail($row);
    }
}
