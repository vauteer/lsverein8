<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\MemberSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property int $member_id
 * @property int $section_id
 * @property CarbonInterface $from
 * @property CarbonInterface|null $to
 * @property string|null $memo
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'member_id',
    'section_id',
    'from',
    'to',
    'memo',
])]
class MemberSection extends Pivot
{
    /** @use HasFactory<MemberSectionFactory> */
    use HasFactory;

    protected $table = 'member_section';

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
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function range(): string
    {
        return getRange($this->from, $this->to, 'm.Y');
    }
}
