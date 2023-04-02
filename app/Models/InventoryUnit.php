<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryUnit extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = "inventory_units";

    public function inventory ()
    {
        return $this->belongsTo('App\Inventory', 'stock_no_unique', 'stock_no_unique');
    }

    public function items ()
    {
        return $this->hasMany('App\Item', 'child_sku', 'child_sku');
    }

    public function options ()
    {
        return $this->hasMany('App\Option', 'child_sku', 'child_sku');
    }

    public function open_po()
    {
        return $this->hasOne('App\PurchaseProduct', 'stock_no', 'stock_no_unique')
            ->where('balance_quantity', '>', 0)
            ->where('is_deleted', '0')
            ->oldest();
    }
}
