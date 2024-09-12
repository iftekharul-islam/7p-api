<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;

    public static $companies = ['null' => 'Select Company', '0' => 'Monogramonline', '1' => 'Natico', '2' => 'PWS', '3' => 'Dropship'];

    public static function inputOptions()
    {
        return [
            '0' => 'None',
            '1' => 'API / FTP (EDI Class)',
            '2' => 'WebHook',
            '3' => 'File Import'
        ];
    }

    public static function batchOptions()
    {
        return [
            '0' => 'Together with other stores',
            '1' => 'Separately',
            '2' => 'Separately at Import'
        ];
    }

    public static function notifyOptions()
    {
        return [
            '0' => 'None',
            '1' => 'API / FTP (EDI Class)',
            '2' => 'E-mail to Customer',
            '3' => 'E-mail and API',
            '4' => 'Export File'
        ];
    }

    public static function qcOptions()
    {
        return [
            '0' => 'Normal QC',
            '1' => 'QC by Shipping Admin'
        ];
    }

    public static function list($batch = '%', $company = '%', $prepend = ['', ''])
    {
        $array =  Store::where('is_deleted', '0')
            ->where('batch', 'LIKE', $batch)
            ->where('company', 'LIKE', $company)
            ->where('invisible', '0')
            ->orderBy('sort_order')
            // ->where('permit_users', 'like', "%" . auth()->user()->id . "%")
            ->get()
            ->pluck('store_name', 'store_id');

        return $array;
    }

    public function store_items()
    {
        return $this->hasMany(StoreItem::class, 'store_id', 'store_id')
            ->where('is_deleted', '0');
    }
}
