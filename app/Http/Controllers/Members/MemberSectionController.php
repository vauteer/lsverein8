<?php

namespace App\Http\Controllers\Members;

use App\Http\Controllers\Controller;
use App\Http\Requests\Members\MemberSectionRequest;
use App\Models\Member;
use App\Models\MemberSection;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The sections a member belongs to, each for a period. The same section may
 * appear twice with different ranges, which is why rows are addressed by pivot
 * id rather than by section id.
 *
 * Every action redirects back rather than to a named route: the caller is the
 * member page, and `back()` re-renders it with its list state intact.
 */
class MemberSectionController extends Controller
{
    public function store(MemberSectionRequest $request, Member $member): RedirectResponse
    {
        $member->sections()->attach($request->validated()['section_id'], $request->safe()->except('section_id'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Section added.')]);

        return back();
    }

    public function update(MemberSectionRequest $request, Member $member, int $row): RedirectResponse
    {
        $this->rowOf($member, $row)->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Section updated.')]);

        return back();
    }

    public function destroy(Member $member, int $row): RedirectResponse
    {
        $this->rowOf($member, $row)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Section removed.')]);

        return back();
    }

    /**
     * The pivot row, constrained to this member.
     *
     * Route model binding is deliberately not used for it: binding resolves by
     * primary key alone, so another member's row would be found and edited.
     */
    private function rowOf(Member $member, int $row): MemberSection
    {
        return MemberSection::query()->where('member_id', $member->id)->findOrFail($row);
    }
}
