<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchasedInvProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_no',
        'unit',
        'unit_price',
        'unit_qty',
        'vendor_id',
        'vendor_sku',
        'vendor_sku_name',
        'lead_time_days',
        'user_id',
    ];

    public function purchasedInvProduct_details ()
    {
        return $this->hasMany(Inventory::class, 'stock_no_unique', 'stock_no')
            ->where('is_deleted', '0');
    }

    public function vendor ()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id', 'id')
            ->where('is_deleted', '0');
    }
}
