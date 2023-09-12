<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreItem extends Model
{
    use HasFactory;

    protected $table = 'store_items';

    public function product()
    {
        return $this->hasOne('App\Product', 'product_model', 'parent_sku');
    }

    public function scopeSearchStore($query, $store_id)
    {

        if (!$store_id) {
            return;
        }

        return $query->where('store_id', $store_id);
    }

    public function scopeSearchVendorSku($query, $sku)
    {

        if (!$sku) {
            return;
        }

        return $query->where('vendor_sku', $sku);
    }
}
