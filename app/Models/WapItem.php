<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WapItem extends Model
{
    use HasFactory;

    protected $table = 'wap_items';

    public function items()
    {
        return $this->belongsTo(Item::class, 'item_id', 'id')
            ->orderBy('item_status');
    }

    public function bin()
    {
        return $this->belongsTo(Wap::class, 'bin_id', 'id');
    }
}
