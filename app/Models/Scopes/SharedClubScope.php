<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts a model to the current club, but also lets through the rows shared
 * across all clubs (club_id is null), such as the default roles and events.
 *
 * @implements Scope<Model>
 */
class SharedClubScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where(function (Builder $query) use ($model): void {
            $query->whereNull($model->qualifyColumn('club_id'))
                ->orWhere($model->qualifyColumn('club_id'), currentClubId());
        });
    }
}
