<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wap extends Model
{
    use HasFactory;

    protected $table = 'wap';
    //hasmany wapItems
    //wapItems belongsto Wap
    public function items()
    {
        return $this->belongsToMany(Item::class, 'wap_items', 'bin_id', 'item_id')
            ->select('wap_items.created_at')
            ->orderBy('wap_items.created_at', 'DESC');
    }

    public function order()
    {
        return $this->hasOne(Order::class, 'id', 'order_id');
    }

    public static function removeItem($item_id, $order_id)
    {
        if (isset($item_id) && isset($order_id)) {

            WapItem::where('item_id', $item_id)->delete();

            $bin = Wap::where('order_id', $order_id)->first();

            if ($bin) {
                $wap_items = WapItem::where('bin_id', $bin->id)->count();

                if ($wap_items == 0) {
                    Wap::emptyBin($order_id);
                }

                return true;
            } else {

                return false;
            }
        } else {

            return false;
        }
    }

    public static function emptyBin($order_id)
    {
        if (isset($order_id)) {

            $bin = Wap::where('order_id', $order_id)->first();

            if ($bin && count($bin) > 0) {
                WapItem::where('bin_id', $bin->id)->delete();

                $bin->order_id = NULL;
                $bin->save();
            }

            return TRUE;
        } else {
            return FALSE;
        }
    }
}
