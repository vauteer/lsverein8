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
        $pivot = $this->rowOf($member, $row);

        if ($this->isLastSubscription($member, $pivot)) {
            // A toast, not a validation error: deleting a row goes through a
            // confirmation dialog with no field to hang a message on. Same
            // split as the last-section rule.
            Inertia::flash('toast', ['type' => 'error', 'message' => $this->lastSubscriptionMessage()]);

            return back();
        }

        $pivot->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscription removed.')]);

        return back();
    }

    /**
     * Whether this row is the only subscription a current member holds.
     *
     * Every current member has to hold one, so that what the club bills is the
     * sum over its subscriptions and nobody is invisible in it. Paying nothing
     * is a 0 € subscription — "Familienmitglied" for somebody a family
     * contribution covers, "Beitragsfrei" for an exemption — which names the
     * reason instead of leaving a blank.
     *
     * `member_subscription` carries no dates (unlike sections), so there is no
     * "closing" a row: holding one is the whole state, and only `destroy` can
     * take the last one away.
     *
     * Only while the member is still one — isMember() reads that the way
     * memberIds() does, so the dead and the not-yet-joined are out. After
     * `resign()` the leftover rows have to stay removable, exactly as with
     * sections.
     */
    private function isLastSubscription(Member $member, MemberSubscription $row): bool
    {
        if (! $member->isMember()) {
            return false;
        }

        return ! MemberSubscription::query()
            ->where('member_id', $member->id)
            ->whereKeyNot($row->id)
            ->exists();
    }

    private function lastSubscriptionMessage(): string
    {
        return __('A current member has to hold at least one subscription. Add the new one first, or end the membership instead.');
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
