<?php

namespace App\Models\Scopes;

use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts a model that has no `club_id` of its own to the club the current
 * user is working in, by way of the member it belongs to.
 *
 * `debits` is the only such table: it hangs off `member_id` alone. The
 * subquery is built from `Member::query()`, so Member's own ClubScope is what
 * narrows it — the club is never spelled out twice.
 *
 * @implements Scope<Model>
 */
class MemberClubScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereIn(
            $model->qualifyColumn('member_id'),
            Member::query()->select('members.id')
        );
    }
}
