<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_number',
        'vendor_id',
        'po_date',
        'payment_method',
        'grand_total',
        'o_status',
        'tracking',
        'notes',
        'user_id'
    ];

    /**
     * Get the vendor associated with the Purchase
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function vendor()
    {
        return $this->hasOne(Vendor::class, 'id', 'vendor_id');
    }
}
