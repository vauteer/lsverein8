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
     * `members_count` is not a column; it comes from the
     * withCurrentMemberCount() scope the index applies. It counts the members
     * who are in the section *now*, which is the same set the member list's
     * `section_X` selection shows — the number is a link to it.
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
