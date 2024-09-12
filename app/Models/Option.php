<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use library\Helper;

class Option extends Model
{
    protected $table = 'parameter_options';

    // search for is the string text
    // search in is the field name to search, dropdown
    public function scopeSearchIn($query, $search_for = null, $operator, $search_in)
    {
        if ($search_for === null && !strpos($operator, 'blank')) {
            return;
        }

        if (!$search_in) {
            return;
        }

        $search_for = trim($search_for);

        switch ($operator) {
            case 'in':
                $op = 'LIKE';
                $search_for = sprintf("%%%s%%", $search_for);
                break;
            case 'not_in':
                $op = 'NOT LIKE';
                $search_for = sprintf("%%%s%%", $search_for);
                break;
            case 'starts_with':
                $op = 'LIKE';
                $search_for = sprintf("%s%%", $search_for);
                break;
            case 'ends_with':
                $op = 'LIKE';
                $search_for = sprintf("%%%s", $search_for);
                break;
            case 'equals':
                $op = '=';
                // $search_for = $search_for;
                break;
            case 'not_equals':
                $op = '!=';
                // $search_for = $search_for;
                break;
            case 'less_than':
                $op = '<';
                // $search_for = $search_for;
                break;
            case 'greater_than':
                $op = '>';
                // $search_for = $search_for;
                break;
            case 'blank':
                return $this->findBlanks($query, $search_in, 0);
                break;
            case 'not_blank':
                return $this->findBlanks($query, $search_in, 1);
                break;
            default:
                $op = 'LIKE';
                $search_for = sprintf("%%%s%%", $search_for);
                break;
        }

        if ($search_in == 'name') {
            return $query->whereHas('product', function ($q) use ($search_for, $op) {
                return $q->where('products.product_name', $op,  $search_for);
            });
        } else if ($search_in == 'stock_number') {
            return $query->whereHas('inventoryunit_relation', function ($q) use ($search_for, $op) {
                return $q->where('stock_no_unique', $op,  $search_for);
            });
        } else {
            return $query->where('parameter_options.' . $search_in, $op, $search_for);
        }
    }

    private function findBlanks($query, $search_in, $flag = 0)
    {

        if ($search_in == 'name' && $flag == 0) {

            return $query->doesntHave('product');
        } elseif ('name' == $search_in && $flag == 1) {

            return $query->has('product');
        } elseif ('stock_number' == $search_in && $flag == 0) {

            return $query->doesntHave('inventoryunit_relation');
        } elseif ('stock_number' == $search_in && $flag == 1) {

            return $query->has('inventoryunit_relation');
        } elseif ($flag == 0) {

            return $query->whereNull($search_in);
        } elseif ($flag == 1) {

            return $query->whereNotNull($search_in);
        }
    }

    public function scopeSearchRoute($query, $id)
    {
        if (empty($id)) {
            return;
        }

        return $query->where('batch_route_id', $id);
    }

    public function scopeSearchSure3d($query, $id)
    {
        if (empty($id)) {
            return;
        }

        return $query->where('sure3d', $id);
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'parent_sku', 'product_model');
    }

    public function design()
    {
        return $this->hasOne(Design::class, 'StyleName', 'graphic_sku');
    }

    public function route()
    {
        return $this->belongsTo(BatchRoute::class, 'batch_route_id', 'id');
    }

    public function items()
    {
        return $this->hasMany('App\Item', 'child_sku', 'child_sku');
    }

    public function inventoryunit_relation()
    {
        return $this->hasMany(InventoryUnit::class, 'child_sku', 'child_sku');
    }

    public function scopeSearchActive($query, $active)
    {

        if ($active == '0' || $active == null) {
            return;
        }

        if ($active == '1') {
            return $query->whereHas('items', function ($q) {
                return $q->where('item_status', 1)
                    ->where('items.is_deleted', '0');
            });
        } elseif ($active == '2') {
            return $query->whereHas('items', function ($q) {
                return $q->where('item_status', 1)
                    ->where('batch_number', '0')
                    ->where('items.is_deleted', '0');
            });
        }
    }

    public function scopeSearchStatus($query, $status)
    {
        if ($status == null) {
            return;
        }

        if ($status == 'RT') {
            return $query->where('batch_route_id', Helper::getDefaultRouteId());
        } else if ($status == 'SN') {
            return $query->where('inventory_unit.stock_no_unique', 'ToBeAssigned')
                ->orWhereNull('inventory_unit.stock_no_unique');
        } else if ($status == 'TM') {
            return $query->whereIn('graphic_sku', ['NeedGraphicFile', '']);
        } else if ($status == 'TP') {
            return $query->whereHas('design', function ($q) {
                return $q->where('template', '0');
            })
                ->whereNotIn('graphic_sku', ['NeedGraphicFile', '']);
        } else if ($status == 'ST') {
            return $query->whereHas('design', function ($q) {
                return $q->where('template', '1')
                    ->where('xml', '0');
            })
                ->whereNotIn('graphic_sku', ['NeedGraphicFile', '']);
        }
        return;
    }

    public static function getGraphicSKU($item)
    {
        $child_sku = $item->child_sku;
        $option = Option::where('child_sku', $child_sku)->first();
        $graphic_sku = '';
        if (!$option) {
            return $child_sku;
        }

        return $option->graphic_sku;
    }

    public function outputArray()
    {
        if ($this->product) {
            $name = $this->product->product_name;
        } else {
            $name = 'PRODUCT NOT FOUND';
        }

        return [
            'App\Option',
            $this->id,
            url(sprintf('logistics/sku_list?search_for_first=%s&contains_first=in&search_in_first=child_sku', $this->child_sku)),
            'Child SKU: ' . $this->child_sku,
            $name,
            null
        ];
    }
}
