<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Printer extends Model
{

    public function station()
    {
        return $this->belongsTo('App\Station', 'station_id', 'id');
    }

    public static function list()
    {
        return Printer::where('is_deleted', '0')
            ->orderBy('number')
            ->get()
            ->pluck('name', 'number')
            ->toArray();
    }

    public static function configurations($status = '%')
    {
        return Printer::where('is_deleted', '0')
            ->where('status', 'LIKE', $status)
            ->orderBy('number')
            ->get()
            ->pluck('station_id', 'number')
            ->toArray();
    }

    public static function config($number, $station)
    {
        $printer = Printer::where('number', $number)
            ->first();

        if (!$printer) {
            return false;
        }

        if ($station != null && $station != 0) {
            $printer->station_id = $station;
            $printer->status = 'A';
        } else {
            $printer->station_id = null;;
            $printer->status = 'M';
        }

        $printer->user_id = auth()->user()->id;

        try {
            $printer->save();
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }
}
