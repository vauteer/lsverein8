<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * The six relations of a member come in three shapes: a from/to range
 * (memberships, sections, roles, issued items), a single date (honours) and
 * nothing but a memo (subscriptions). Everything they share lives here.
 */
trait MemberRelationRules
{
    /**
     * A from/to range. `to` may be open, which is what "still running" means
     * everywhere in this app — Member::isMember(), currentSections() and the
     * member selections all read a null `to` that way.
     *
     * `after`, not `after_or_equal`: a period lasts at least a day, the same
     * floor MemberResignRequest applies when it closes these rows in bulk.
     * Verified against production first — no row in any of the four tables
     * had `from == to`, so nothing existing becomes unsavable.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function rangeRules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['nullable', 'date', 'after:from'],
            'memo' => ['nullable', 'string', 'max:191'],
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function datedRules(): array
    {
        return [
            'date' => ['required', 'date'],
            'memo' => ['nullable', 'string', 'max:191'],
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function memoRules(): array
    {
        return [
            'memo' => ['nullable', 'string', 'max:191'],
        ];
    }

    /**
     * The rule for the row being pointed at.
     *
     * Scoped by hand: `exists` runs a plain query and does not pick up the
     * model's club scope, so without this a club admin could attach another
     * club's section, role or subscription to their member.
     *
     * `$shared` covers the tables whose `club_id` is nullable — events and
     * roles have installation-wide rows that belong to every club, seeded by
     * insert_events_defaults and insert_roles_defaults. Sections had them too
     * until 2026-08-30, when the column became NOT NULL.
     *
     * @param  class-string<Model>  $model
     * @return array<int, ValidationRule|string>
     */
    protected function belongsToClubRule(string $model, bool $shared = false): array
    {
        return [
            'required',
            'integer',
            Rule::exists($model, 'id')->where(function ($query) use ($shared) {
                $query->where('club_id', currentClubId());

                if ($shared) {
                    $query->orWhereNull('club_id');
                }
            }),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function relationMessages(): array
    {
        return [
            'required' => __(':attribute is required.'),
            'integer' => __(':attribute must be a number.'),
            'date' => __(':attribute must be a valid date.'),
            'exists' => __('The selected :attribute is invalid.'),
            'to.after' => __('The period must last at least one day, so :attribute has to be after the start.'),
            'max' => [
                'string' => __(':attribute may not be longer than :max characters.'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function relationAttributes(): array
    {
        return [
            'from' => __('From'),
            'to' => __('To'),
            'date' => __('Date'),
            'memo' => __('Memo'),
            'section_id' => __('Section'),
            'role_id' => __('Role'),
            'item_id' => __('Item'),
            'event_id' => __('Honour'),
            'subscription_id' => __('Subscription'),
        ];
    }
}
