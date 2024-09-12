<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StationLog extends Model
{
    public function station()
    {
        return $this->belongsTo('App\Station', 'station_id', 'id');
    }

    public function prev_station()
    {
        return $this->belongsTo('App\Station', 'prev_station_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo('App\Item', 'item_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }

    public function scopeSearchStation($query, $station_id)
    {
        if (!$station_id) {
            return;
        }

        return $query->where('station_id', $station_id);
    }

    public function scopeSearchUser($query, $user_id)
    {
        if (!$user_id) {
            return;
        }

        return $query->where('user_id', $user_id);
    }

    public function scopeSearchSKU($query, $sku)
    {
        if (!$sku) {
            return;
        }
        $items = Item::where('item_code', $sku)
            ->get();

        if (!$items->count()) {
            return;
        }
        $item_ids = $items->pluck('id')
            ->toArray();

        return $query->whereIn('item_id', $item_ids);
    }

    public function scopeWithinDate($query, $start_date, $end_date)
    {
        if (!$start_date) {
            return;
        }

        $starting = sprintf("%s", $start_date . ' 00:00:00');
        $ending = sprintf("%s", $end_date ? $end_date . ' 23:59:59' : $start_date . ' 23:59:59');

        return $query->where('created_at', '>=', $starting)
            ->where('created_at', '<=', $ending);
    }

    public function scopeSearchWithinMonthGroupLog($query, $start_date, $end_date)
    {
        if (!$start_date) {
            return;
        }

        $starting = sprintf("%s", $start_date . ' 00:00:00');
        $ending = sprintf("%s", $end_date ? $end_date . ' 23:59:59' : $start_date . ' 23:59:59');

        return $query->where('created_at', '>=', $starting)
            ->where('created_at', '<=', $ending)
            ->groupBy([
                'station_id',
                // uncomment user if user is required
                # 'user_id',
                'created_at',
            ]);
    }
}
