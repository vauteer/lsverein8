<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ClubUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property int $club_id
 * @property int $user_id
 * @property int $role
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['club_id', 'user_id', 'role'])]
class ClubUser extends Pivot
{
    /** @use HasFactory<ClubUserFactory> */
    use HasFactory;

    protected $table = 'club_user';

    public $incrementing = true;

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
