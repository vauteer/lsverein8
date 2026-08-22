<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\MemberSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property int $member_id
 * @property int $subscription_id
 * @property string|null $memo
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['member_id', 'subscription_id', 'memo'])]
class MemberSubscription extends Pivot
{
    /** @use HasFactory<MemberSubscriptionFactory> */
    use HasFactory;

    protected $table = 'member_subscription';

    public $incrementing = true;

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
