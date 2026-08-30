<?php

namespace App\Http\Resources;

use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Member
 */
class MemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Everything derived here (`is_member`, `membership_years`, the current
     * sections and roles) is computed from the loaded relations against
     * `Member::getKeyDate()`, which the controller sets from the chosen year.
     * The index must eager-load them or this is one query per row.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isAdmin = (bool) $request->user()?->hasAdminRights();

        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'surname' => $this->surname,
            'first_name' => $this->first_name,
            'full_name' => $this->fullName(),
            'address' => trim("{$this->zipcode} {$this->city}, {$this->street}", ' ,'),
            'gender' => $this->gender->value,
            'birthday' => formatDate($this->birthday),
            'age' => $this->age,
            // Whether they are dead as of the key date, which is what puts the
            // dagger next to the name.
            'gone' => $this->gone(),
            'is_member' => $this->isMember(),
            'membership_years' => $this->membershipYears(),
            'sections' => $this->currentSections(),
            'roles' => $this->currentRoles(),
            // What a member pays and what they have been awarded is a
            // treasurer's business; a read-only account sees neither. Their
            // bank details are not in this resource at all — the edit form is
            // the only place they are sent, and only an admin reaches it.
            'subscriptions' => $isAdmin ? $this->currentSubscriptions() : null,
            'latest_honor' => $isAdmin ? $this->latestHonorName() : null,
            'modifiable' => (bool) $request->user()?->can('update', $this->resource),
        ];
    }
}
