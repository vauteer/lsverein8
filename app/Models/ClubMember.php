<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ClubMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property int $club_id
 * @property int $member_id
 * @property CarbonInterface $from
 * @property CarbonInterface|null $to
 * @property string|null $memo
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'club_id',
    'member_id',
    'from',
    'to',
    'memo',
])]
class ClubMember extends Pivot
{
    /** @use HasFactory<ClubMemberFactory> */
    use HasFactory;

    protected $table = 'club_member';

    public $incrementing = true;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from' => 'date',
            'to' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function range(): string
    {
        return getRange($this->from, $this->to, 'm.Y');
    }
}
