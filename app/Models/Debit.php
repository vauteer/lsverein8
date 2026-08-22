<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\DebitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $member_id
 * @property float $amount
 * @property string $transfer_text
 * @property CarbonInterface $due_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'member_id',
    'amount',
    'transfer_text',
    'due_at',
])]
class Debit extends Model
{
    /** @use HasFactory<DebitFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_at' => 'date',
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
     * @param  Builder<Debit>  $query
     */
    #[Scope]
    protected function due(Builder $query, ?CarbonInterface $date): void
    {
        $query->where('due_at', '<=', $date ?? now()->endOfDay());
    }

    /**
     * Collects every debit due on the execution date, hands them to the SEPA
     * generator and clears them.
     *
     * @return array{downloads: array<int, array{name: string, href: string}>}
     */
    public static function debit(CarbonInterface $executionDate): array
    {
        $debits = Debit::query()->due($executionDate)->get()
            ->map(fn (Debit $debit): array => [
                'member_id' => $debit->member_id,
                'amount' => $debit->amount,
                'transfer_text' => $debit->transfer_text,
            ])
            ->all();

        Debit::query()->due($executionDate)->delete();

        return [
            'downloads' => Subscription::generateSepa($debits, $executionDate),
        ];
    }
}
