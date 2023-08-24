<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $table = 'customers';

    public function order()
    {
        return $this->hasMany(Order::class)
            ->where('is_deleted', 0);
    }

    public function getShipFullNameAttribute($value)
    {
        return str_replace('&', '+', $value);
    }

    public function getShipCompanyNameAttribute($value)
    {
        return str_replace('&', '+', $value);
    }
}
