<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BatchScan extends Model
{
    public function setInDateAttribute($value)
    {

        Batch::where('batch_number', $this->attributes['batch_number'])
            ->update([
                'change_date' => $value
            ]);

        $this->attributes['in_date'] = $value;
    }

    public function setOutDateAttribute($value)
    {

        Batch::where('batch_number', $this->attributes['batch_number'])
            ->update([
                'change_date' => $value
            ]);

        $this->attributes['out_date'] = $value;
    }

    public function batch()
    {
        return $this->belongsTo('App\Batch', 'batch_number', 'batch_number');
    }

    public function station()
    {
        return $this->belongsTo('App\Station', 'station_id', 'id');
    }

    public function in_user()
    {
        return $this->belongsTo(User::class, 'in_user_id', 'id');
    }

    public function out_user()
    {
        return $this->belongsTo('App\User', 'out_user_id', 'id');
    }

    public function scopeSearchStation($query, $station_id)
    {
        if (!$station_id) {
            return;
        }

        return $query->where('station_id', $station_id);
    }

    public function scopeSearchInUser($query, $user_id)
    {
        if (!$user_id) {
            return;
        }

        return $query->where('in_user_id', $user_id);
    }

    public function scopeSearchOutUser($query, $user_id)
    {
        if (!$user_id) {
            return;
        }

        return $query->where('out_user_id', $user_id);
    }
}
