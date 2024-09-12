<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryUnit extends Model
{
    use HasFactory;

    //TODO need to change name from database
    // protected $table = "inventory_unit";

    protected $table = "inventory_unit";

    public function inventory()
    {
        return $this->belongsTo(Inventory::class, 'stock_no_unique', 'stock_no_unique');
    }

    public function items()
    {
        return $this->hasMany('App\Item', 'child_sku', 'child_sku');
    }

    public function options()
    {
        return $this->hasMany('App\Option', 'child_sku', 'child_sku');
    }

    public function open_po()
    {
        return $this->hasOne('App\PurchaseProduct', 'stock_no', 'stock_no_unique')
            ->where('balance_quantity', '>', 0)
            // ->where('is_deleted', '0')
            ->oldest();
    }
}
