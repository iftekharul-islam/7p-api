<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stocks extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_number',
        'description',
        'section_id',
        'weight',
        'order_quantity',
        'minimum_stock_quantity',
        'last_cost',
        'upc',
        'bin',
        'image_url'
    ];
}
