<?php

namespace App\Http\Resources;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * `members_count` is not a column; it comes from the withCurrentMemberCount() scope
     * the index applies, and counts only the current club's members because
     * Member carries the ClubScope.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'amount' => $this->amount,
            // Formatted here rather than in the table, so the German decimal
            // comma is not hardcoded into a Vue template.
            'amount_label' => $this->amountLabel(),
            'transfer_text' => $this->transfer_text,
            'memo' => $this->memo,
            'members_count' => (int) $this->getAttribute('members_count'),
            'modifiable' => (bool) $request->user()?->can('update', $this->resource),
            'deletable' => (bool) $request->user()?->can('delete', $this->resource),
        ];
    }
}
