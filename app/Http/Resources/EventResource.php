<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Event
 */
class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * `members_count` is not a column; it comes from the withMemberCount() scope
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
            // Events shared across all clubs (club_id null) are listed here
            // but only a root account may change them.
            'shared' => $this->club_id === null,
            'modifiable' => (bool) $request->user()?->can('update', $this->resource),
            'deletable' => (bool) $request->user()?->can('delete', $this->resource),
        ];
    }
}
