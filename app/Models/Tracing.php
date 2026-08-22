<?php

namespace App\Models;

use App\Enums\ActionType;
use Carbon\CarbonInterface;
use Database\Factories\TracingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property CarbonInterface $at
 * @property int $user_id
 * @property int $action_type
 * @property int|null $table_type
 * @property int|null $row_id
 * @property string|null $old_values
 */
#[Fillable([
    'at',
    'user_id',
    'action_type',
    'table_type',
    'row_id',
    'old_values',
])]
class Tracing extends Model
{
    /** @use HasFactory<TracingFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<Tracing>  $query
     */
    public function scopeActionType(Builder $query, ActionType $actionType): void
    {
        $query->where('action_type', $actionType);
    }
}
