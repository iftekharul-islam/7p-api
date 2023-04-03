<?php

namespace App\Models;

use App\Http\Controllers\InventoryController;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

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


    public function options()
    {
        return $this->hasMany('App\Option', 'stock_number', 'stock_no_unique');
    }

    public function inventoryUnitRelation()
    {
        return $this->hasMany(InventoryUnit::class, 'stock_no_unique', 'stock_no_unique');
    }

    public function adjustments()
    {
        return $this->hasMany('App\InventoryAdjustment', 'stock_no_unique', 'stock_no_unique')->orderBy('created_at', 'DESC');
    }

    public function purchase_products()
    {
        return $this->hasMany(PurchasedProduct::class, 'stock_no', 'stock_no_unique')->latest();
    }

    public function last_product()
    {
        return $this->hasOne(PurchasedInvProduct::class, 'stock_no', 'stock_no_unique')
            ->latest();
    }

    public function qty_user()
    {
        return $this->belongsTo(User::class, 'qty_user_id', 'id');
    }

    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id', 'id');
    }

    public function scopeSearchCriteria($query, $search_for, $search_in, $operator = null)
    {

        $search_for = trim($search_for);

        if ($search_for === null && !strpos($operator, 'blank')) {
            return;
        }

        if (in_array($search_in, array_keys(InventoryController::$search_in))) {

            if ($search_in == 'stock_no_unique' && strpos($search_for, ',') && $operator == 'in') {
                return $query->whereIn($search_in, explode(',', $search_for));
            } else if ($search_in == 'stock_no_unique' && strpos($search_for, ',') && $operator == 'not_in') {
                return $query->whereNotIn($search_in, explode(',', $search_for));
            }

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

            if ('child_sku' == $search_in) {

                return $query->whereHas('inventoryUnitRelation', function ($query) use ($search_for, $op) {
                    $query->where('child_sku', $op, $search_for);
                });
            } else {

                return $query->where($search_in, $op, $search_for);
            }
        }

        return;
    }

    public function scopeSearchSection($query, $section)
    {
        if ($section == '' || null === $section || $section == []) {
            return;
        }

        if ($section == 'blank') {
            return $query->doesntHave('section')
                ->orWhere('section_id', '0')
                ->orWhereNull('section_id');
        }

        if (is_array($section)) {
            return $query->whereIn('section_id', $section);
        }

        return $query->where('section_id', $section);
    }

    public function scopeSearchVendor($query, $vendor)
    {
        if ($vendor == '') {
            return;
        }

        if ($vendor == 'blank') {
            return $query->doesntHave('last_product');
        }

        return $query->whereHas('last_product', function ($q) use ($vendor) {
            return $q->where('vendor_id', $vendor);
        });
    }
}
