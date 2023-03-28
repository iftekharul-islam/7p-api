<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'product_id',
        'stock_no',
        'vendor_sku',
        'vendor_sku_name',
        'quantity',
        'price',
        'sub_total',
        'eta',
        'receive_date',
        'receive_quantity',
        'balance_quantity',
        'user_id'
    ];
}
