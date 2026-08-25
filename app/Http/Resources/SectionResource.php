<?php

namespace App\Http\Resources;

use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Section
 */
class SectionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * `members_count` is not a column; it comes from the withCount('members')
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
            'blsv_id' => $this->blsv_id,
            'blsv_label' => $this->blsv_id === null
                ? null
                : (Section::BLSV_SECTIONS[$this->blsv_id] ?? null),
            'members_count' => (int) $this->getAttribute('members_count'),
            // Sections shared across all clubs (club_id null) are listed here
            // but only a root account may change them.
            'shared' => $this->club_id === null,
            'modifiable' => (bool) $request->user()?->can('update', $this->resource),
            'deletable' => (bool) $request->user()?->can('delete', $this->resource),
        ];
    }
}
