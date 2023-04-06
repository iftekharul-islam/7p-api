<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Station extends Model
{
    use HasFactory;

    protected $fillable = [
        'station_name',
        'station_description',
        'station_status',
        'section',
        'type',
    ];

    public function reject_reasons()
    {
        return $this->hasMany('App\RejectionReason', 'station_id', 'id');
    }

    public function getCustomStationNameAttribute()
    {
        return sprintf("%s => %s", $this->station_name, $this->station_description);
    }

    public function section_info()
    {
        return $this->belongsTo(Section::class, 'section', 'id');
    }

    public function route_list()
    {

        return $this->belongsToMany(BatchRoute::class, 'batch_route_station', 'station_id', 'batch_route_id')
            ->where('batch_routes.is_deleted', 0)
            ->orderBy('batch_routes.batch_route_name');
    }

    public function scopeSearchSection($query, $section)
    {
        if (!$section) {
            return;
        }

        return $query->where('section', $section);
    }
}
