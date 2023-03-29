<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inventory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'stock_no_unique',
        'stock_name_discription',
        'section_id',
        'sku_weight',
        're_order_qty',
        'min_reorder',
        'sales_30',
        'sales_90',
        'sales_180',
        'total_sale',
        'qty_on_hand',
        'qty_user_id',
        'qty_date',
        'qty_alloc',
        'qty_av',
        'total_purchase',
        'qty_exp',
        'until_reorder',
        'last_cost',
        'value',
        'vendor_id',
        'upc',
        'wh_bin',
        'warehouse',
        'user_id',
    ];


    public function options ()
    {
        return $this->hasMany('App\Option', 'stock_number', 'stock_no_unique');
    }

    public function inventoryUnitRelation ()
    {
        return $this->hasMany('App\InventoryUnit', 'stock_no_unique', 'stock_no_unique');
    }

    public function adjustments ()
    {
        return $this->hasMany('App\InventoryAdjustment', 'stock_no_unique', 'stock_no_unique')->orderBy('created_at', 'DESC');
    }

    public function purchase_products ()
    {
        return $this->hasMany(PurchaseProduct::class, 'stock_no', 'stock_no_unique')->latest();
    }

    public function last_product ()
    {
        return $this->hasOne(PurchasedInvProduct::class, 'stock_no', 'stock_no_unique')
            ->where('is_deleted', '0')
            ->latest();
    }

    public function qty_user ()
    {
        return $this->belongsTo(User::class, 'qty_user_id', 'id');
    }

    public function section ()
    {
        return $this->belongsTo(Section::class, 'section_id', 'id');
    }
}
