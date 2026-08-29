<?php

namespace App\Http\Controllers\Members;

use App\Http\Controllers\Controller;
use App\Http\Requests\Members\MemberSectionRequest;
use App\Models\Member;
use App\Models\MemberSection;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
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
        $validated = $request->validated();
        $pivot = $this->rowOf($member, $row);

        // Closing the row is what would leave the member with none; moving its
        // start or editing the note cannot.
        if ($this->closes($validated['to'] ?? null) && $this->isLastActiveSection($member, $pivot)) {
            throw ValidationException::withMessages([
                'to' => $this->lastSectionMessage(),
            ]);
        }

        $pivot->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Section updated.')]);

        return back();
    }

    public function destroy(Member $member, int $row): RedirectResponse
    {
        $pivot = $this->rowOf($member, $row);

        if ($this->isLastActiveSection($member, $pivot)) {
            // A toast, not a validation error: deleting a row goes through a
            // confirmation dialog with no field to hang a message on.
            Inertia::flash('toast', ['type' => 'error', 'message' => $this->lastSectionMessage()]);

            return back();
        }

        $pivot->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Section removed.')]);

        return back();
    }

    /**
     * Whether this row is the only section the member is currently in, in a
     * club where that is not allowed to happen.
     *
     * A BLSV club reports its members section by section — somebody counted in
     * the yearly Meldung has to sit in one, and a member in none would simply
     * be missing from the file (Club::getBLSVStatistic() builds it per
     * section). So the club must be a blsv_member, the member must still be
     * one — isMember() reads that the way memberIds() does, which is what the
     * Meldung is built from, so the dead and the not-yet-joined are out — and
     * this must be the last row keeping it true.
     *
     * "Active" is `to IS NULL OR to >= today`, the same reading inRange() and
     * the statistic use — a row ending today still counts today.
     */
    private function isLastActiveSection(Member $member, MemberSection $row): bool
    {
        if (! currentClub()->blsv_member || ! $this->isActive($row->to) || ! $member->isMember()) {
            return false;
        }

        $today = Date::now()->startOfDay();

        return ! MemberSection::query()
            ->where('member_id', $member->id)
            ->whereKeyNot($row->id)
            ->where(fn ($query) => $query->whereNull('to')->orWhere('to', '>=', $today))
            ->exists();
    }

    private function closes(?string $to): bool
    {
        return ! $this->isActive($to === null ? null : Date::parse($to));
    }

    private function isActive(?CarbonInterface $to): bool
    {
        return $to === null || $to->gte(Date::now()->startOfDay());
    }

    private function lastSectionMessage(): string
    {
        return __('A member of a BLSV club has to be in at least one section. Add the new one first, or end the membership instead.');
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
