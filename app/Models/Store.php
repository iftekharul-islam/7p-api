<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;

    public static $companies = ['null' => '', '0' => 'Monogramonline', '1' => 'Natico', '2' => 'PWS', '3' => 'Dropship'];

    public static function list($batch = '%', $company = '%', $prepend = ['', ''])
    {
        $array =  Store::where('is_deleted', '0')
            ->where('batch', 'LIKE', $batch)
            ->where('company', 'LIKE', $company)
            ->where('invisible', '0')
            ->orderBy('sort_order')
            ->where('permit_users', 'like', "%" . auth()->user()->id . "%")
            ->get()
            ->pluck('store_name', 'store_id');

        if (isset($prepend) && $prepend != 'none') {
            $array->prepend($prepend[0], $prepend[1]);
        }

        return $array;
    }
}
