<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryAdjustment extends Model
{
    use HasFactory;
    protected $table = "inventory_adjustments";

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class, 'stock_no_unique', 'stock_no_unique');
    }

    public function getTypeAttribute($value)
    {
        if ($value == 1) {
            return 'Item Shipped';
        } elseif ($value == 2) {
            return 'Updated from inventory list';
        } elseif ($value == 3) {
            return 'Manually updated';
        } elseif ($value == 4) {
            return 'Manually adjusted';
        } elseif ($value == 5) {
            return 'Production Reject';
        } elseif ($value == 6) {
            return 'Received';
        } elseif ($value == 7) {
            return 'Refused';
        } elseif ($value == 8) {
            return 'Production Reject';
        } elseif ($value == 9) {
            return 'Production Reject';
        } else {
            return 'Unknown';
        }
    }

    public function scopeSearchStockNumber($query, $stock_no_unique)
    {
        $stock_no_unique = trim($stock_no_unique);

        if (empty($stock_no_unique)) {
            return;
        }

        return $query->where('stock_no_unique', $stock_no_unique);
    }

    public static function shipItem($child_sku, $id, $item_quantity, $tracking)
    {
        $units = InventoryUnit::where('child_sku', $child_sku)->get();

        if (!$units) {
            return 0;
        }

        $succeeded = 1;

        foreach ($units as $unit) {
            $qty = $unit->unit_qty * $item_quantity;
            $stock_no = $unit->stock_no_unique;

            $result = self::adjustInventory(1, $stock_no, $qty, $tracking, $id);

            if ($result == 0) {
                $succeeded = 0;
            }
        }

        return $succeeded;
    }

    public static function adjustInventory($type, $stock_no, $qty, $note = null, $identifier = null)
    {

        if ($stock_no == 'ToBeAssigned' || $stock_no == null) {
            return 0;
        }

        $inventory = Inventory::with('last_product')->where('stock_no_unique', $stock_no)->first();

        if (!$inventory) {
            return 0;
        }

        switch ($type) {
            case 1: //ship
                $date = false;
                $new_qty = $inventory->qty_on_hand - $qty;
                $adjustment_qty = $qty * -1;
                break;
            case 2: //inventory list
            case 3: //update
                $date = true;
                $new_qty = $qty;
                $adjustment_qty = $qty - $inventory->qty_on_hand;
                break;
            case 4: //adjust
                $date = true;
                $new_qty = $inventory->qty_on_hand + $qty;
                $adjustment_qty = $qty;
                break;
            case 5: //reject from adjustments screen
            case 7: //refuse
            case 8: //reject from production
            case 9: //reject whole batch
                $date = false;
                $new_qty = $inventory->qty_on_hand - $qty;
                $adjustment_qty = $qty * -1;
                break;
            case 6: //receive
                $date = false;
                $new_qty = $inventory->qty_on_hand + $qty;
                $adjustment_qty = $qty;

                if (count($inventory->purchase_products) > 0) {
                    $inventory->last_cost = $inventory->purchase_products->first()->price;
                }

                break;
            default:
                return 0;
                break;
        }

        if ($new_qty < 0) {
            $new_qty = 0;
        }

        if ($new_qty >= 0 && $new_qty != $inventory->qty_on_hand) {

            $inventory->qty_on_hand = $new_qty;

            if ($date) {
                $inventory->qty_user_id = auth()->user()->id;
                $inventory->qty_date = date("Y-m-d H:i:s");
            }
        }

        $inventory->qty_av = $inventory->qty_on_hand - $inventory->qty_alloc;
        $inventory->until_reorder = $inventory->qty_av - $inventory->min_reorder;

        $inventory->save();

        if ($adjustment_qty != 0) {

            $adjustment = new InventoryAdjustment();
            $adjustment->stock_no_unique = $stock_no;
            $adjustment->type = $type;
            $adjustment->quantity = $adjustment_qty;
            $adjustment->user_id = auth()->user()->id;
            $adjustment->note = $note;
            $adjustment->identifier = $identifier;
            $adjustment->save();

            return $adjustment->id;
        }

        return 1;
    }
}
