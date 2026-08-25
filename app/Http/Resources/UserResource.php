<?php

namespace App\Http\Resources;

use App\Enums\ClubRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * `role` and `last_login_at` are not columns; they come from the
     * User::withRole() and User::withLastLoginAt() scopes the index applies.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = ClubRole::tryFrom((int) $this->getAttribute('role'));

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'locale' => $this->locale?->value,
            'last_login' => $this->getAttribute('last_login_at'),
            'role' => $role?->value,
            'role_label' => $role?->label(),
            'admin' => (bool) $this->admin,
            'avatar' => $this->profileURL(),
            // Root accounts may only be managed by themselves.
            'modifiable' => (bool) $request->user()?->can('update', $this->resource),
            'impersonatable' => (bool) $request->user()?->can('impersonate', $this->resource),
        ];
    }
}
