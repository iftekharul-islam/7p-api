<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
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
        return $this->hasOne(Vendor::class, 'id', 'vendor_id');
    }

    /**
     * Get the user associated with the Product
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function stock()
    {
        return $this->hasOne(Stock::class, 'id', 'stock_id');
    }
}
