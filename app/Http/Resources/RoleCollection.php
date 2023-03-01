<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RoleCollection extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $user = [];
        foreach ($this->users as $value) {
            $user[] = [
                'value' => $value->id,
                'size' => 'sm',
                'title' => $value->name,
                'img' => $value->avatar_url,
                'clickable' => true
            ];
        }

        return [
            'id' => $this->id,
            'title' => $this->name,
            'totalUsers' => $this->users_count,
            'users' => $user
        ];
    }
}
