<?php

namespace App\Http\Resources;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Item
 */
class ItemResource extends JsonResource
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
            'members_count' => (int) $this->getAttribute('members_count'),
            'ever_members_count' => (int) $this->getAttribute('ever_members_count'),
            'modifiable' => (bool) $request->user()?->can('update', $this->resource),
            'deletable' => (bool) $request->user()?->can('delete', $this->resource),
        ];
    }
}
