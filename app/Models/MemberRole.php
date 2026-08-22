<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\MemberRoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property int $member_id
 * @property int $role_id
 * @property CarbonInterface $from
 * @property CarbonInterface|null $to
 * @property string|null $memo
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'member_id',
    'role_id',
    'from',
    'to',
    'memo',
])]
class MemberRole extends Pivot
{
    /** @use HasFactory<MemberRoleFactory> */
    use HasFactory;

    protected $table = 'member_role';

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
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function range(): string
    {
        return getRange($this->from, $this->to, 'm.Y');
    }
}
