<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
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
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'designation'=> $this->employee->designation,
            'permissions' => $permissions->map(fn ($perm) => $perm->name),
            'role'=>$this->roles->first()->name ?? '--',
            'role_id'=> $this->roles->first()->id ?? '--'

        ];
    }
}
