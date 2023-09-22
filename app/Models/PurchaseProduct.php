<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseProduct extends Model
{
    protected $table = "purchased_products";

    public function product_details()
    {
        return $this->belongsTo('App\PurchasedInvProducts', 'product_id', 'id');
        // ->where('is_deleted', '0');
    }

    public function inventory()
    {
        return $this->belongsTo('App\Inventory', 'stock_no', 'stock_no_unique')
            ->where('is_deleted', '0');
    }

    public function scopeSearchStockName($query, $stock_name)
    {
        $stock_name = trim($stock_name);
        if (empty($stock_name)) {
            return false;
        }

        return $query->where('stock_no', $stock_name)
            ->where('is_deleted', '0');
    }
}
