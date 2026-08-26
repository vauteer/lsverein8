<?php

namespace App\Http\Controllers\Members;

use App\Http\Controllers\Controller;
use App\Http\Requests\Members\MemberRoleRequest;
use App\Models\Member;
use App\Models\MemberRole;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The roles a member holds, each for a period. `member_role.role_id` is
 * ON DELETE RESTRICT, so a role in use here cannot be deleted from the role CRUD.
 *
 * Every action redirects back rather than to a named route: the caller is the
 * member page, and `back()` re-renders it with its list state intact.
 */
class MemberRoleController extends Controller
{
    public function store(MemberRoleRequest $request, Member $member): RedirectResponse
    {
        $member->roles()->attach($request->validated()['role_id'], $request->safe()->except('role_id'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role added.')]);

        return back();
    }

    public function update(MemberRoleRequest $request, Member $member, int $row): RedirectResponse
    {
        $this->rowOf($member, $row)->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return back();
    }

    public function destroy(Member $member, int $row): RedirectResponse
    {
        $this->rowOf($member, $row)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role removed.')]);

        return back();
    }

    /**
     * The pivot row, constrained to this member.
     *
     * Route model binding is deliberately not used for it: binding resolves by
     * primary key alone, so another member's row would be found and edited.
     */
    private function rowOf(Member $member, int $row): MemberRole
    {
        return MemberRole::query()->where('member_id', $member->id)->findOrFail($row);
    }
}
