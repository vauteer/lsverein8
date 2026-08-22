<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ItemMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property int $item_id
 * @property int $member_id
 * @property string|null $memo
 * @property CarbonInterface $from
 * @property CarbonInterface|null $to
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'item_id',
    'member_id',
    'memo',
    'from',
    'to',
])]
class ItemMember extends Pivot
{
    /** @use HasFactory<ItemMemberFactory> */
    use HasFactory;

    protected $table = 'item_member';

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
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
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
