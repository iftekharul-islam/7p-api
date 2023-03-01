<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ActivityCollection extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $causer = $this->causer->load('employee', 'details')->only('id', 'name', 'avatar_url', 'details', 'employee');

        return [
            'id' => $this->id,
            'log_name' => $this->log_name,
            'message' => __('activities.' . $this->log_name . '.' . ($request->self ? 'self.' : null) . $this->event),
            'event' => $this->event,
            'causer' => $causer,
            'properties' => $this->properties,
            'created_at' => $this->created_at,
        ];
    }
}
