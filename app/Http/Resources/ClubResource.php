<?php

namespace App\Http\Resources;

use App\Models\Club;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Club
 */
class ClubResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Bank details are deliberately absent: the index is a list, and the IBAN
     * only belongs on the form of a club the viewer may actually edit.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'city' => $this->city,
            'logo_url' => $this->logoURL(),
            'blsv_member' => $this->blsv_member,
            // Current members only, matching the member list's default
            // selection — which the number links to, but only on the row of
            // the club the viewer is actually working in.
            'members_count' => (int) $this->getAttribute('members_count'),
            'users_count' => (int) $this->getAttribute('users_count'),
            'current' => $this->id === currentClubId(),
            'modifiable' => (bool) $request->user()?->can('update', $this->resource),
            'deletable' => (bool) $request->user()?->can('delete', $this->resource),
            'switchable' => (bool) $request->user()?->can('switchTo', $this->resource),
        ];
    }
}
