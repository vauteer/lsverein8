<?php

namespace App\Http\Resources;

use App\Models\Debit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Debit
 */
class DebitResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'member_name' => $this->member->fullName(),
            'amount' => $this->amount,
            // Formatted here rather than in the table, so the German decimal
            // comma is not hardcoded into a Vue template.
            'amount_label' => $this->amountLabel(),
            'transfer_text' => $this->transfer_text,
            'due_at' => $this->due_at->format('Y-m-d'),
            'due_at_label' => formatDate($this->due_at),
            // Whether this row is taken along by a collection run started
            // today; the index greys out the ones that are not yet due.
            'due' => ! $this->due_at->isAfter(now()->endOfDay()),
            'modifiable' => (bool) $request->user()?->can('update', $this->resource),
            'deletable' => (bool) $request->user()?->can('delete', $this->resource),
        ];
    }
}
