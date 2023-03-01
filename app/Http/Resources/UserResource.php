<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;


class UserResource extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $role = $this->roles()
            ->with('permissions')
            ->first();
        $permissions = $role->permissions ?? collect([]);

        return [
            'id' => $this->id,
            'avatar' => $this->avatar_url,
            'name' => $this->name ?? 'ADMIN',
            'email' => $this->email,
            'designation' => $this->designation->name,
            'status' => $this->status,
            'role_id' => $role->id ?? "",
            'role' => $role->name ?? "",
            'permissions' => $permissions->map(fn ($perm) => $perm->name),
            'details' => $this->details
        ];
    }
}
