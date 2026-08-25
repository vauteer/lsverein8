<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts a model to the current club, but also lets through the rows shared
 * across all clubs (club_id is null), such as the default roles and events.
 *
 * The additive companion to ClubScope: same club restriction, plus the shared
 * rows. The nested closure is load-bearing — without it the orWhere leaks past
 * any other condition on the query and returns other clubs' rows.
 *
 * @implements Scope<Model>
 */
class ClubWithSharedScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where(function (Builder $query) use ($model): void {
            $query->whereNull($model->qualifyColumn('club_id'))
                ->orWhere($model->qualifyColumn('club_id'), currentClubId());
        });
    }
}
