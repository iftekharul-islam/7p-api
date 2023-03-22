<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Products extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_id',
        'name',
        'sku_weight',
        're_order_qty',
        'min_order',
        'adjusment',
        'unit',
        'qty',
        'unit_price',
        'vendor_id',
        'vendor_sku',
        'sku_name',
        'lead_time_days'
    ];

    /**
     * Get the vendor associated with the Products
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function vendor()
    {
        return $this->hasOne(Vendors::class, 'id', 'vendor_id');
    }
}
