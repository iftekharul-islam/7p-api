<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    protected $fillable = ['*'];

    public function isCancelled()
    {
        $statuses = array_unique($this->items->pluck('item_status')->all());

        if ($statuses != ["cancelled", "shipped"] && $statuses != ["cancelled"]) {
            return;
        }

        if ($statuses == ["cancelled"]) {

            $subtotal = 0;

            foreach ($this->items as $item) {

                $subtotal += $item->item_quantity * $item->item_unit_price;

                Batch::isFinished($item->batch_number);
            }

            $total = $subtotal - $this->coupon_value - $this->promotion_value + $this->gift_wrap_cost +
                $this->insurance + $this->shipping_charge + $this->tax_charge;

            $this->adjustments = $total * -1;
            $this->total = 0;

            if ($this->order_status != 8) {
                $this->order_status = 8;
            }
        }

        if ($statuses == ["cancelled", "shipped"]) {

            $this->order_status = 6;
        }

        $this->save();

        Wap::emptyBin($this->id);

        return;
    }

    public function setOrderStatusAttribute($value)
    {
        $statuses = Order::statuses();

        if (
            array_key_exists('order_status', $this->attributes) &&
            array_key_exists('id', $this->attributes) &&
            $value != $this->attributes['order_status']  &&
            $this->attributes['order_status'] !=  0 &&
            array_key_exists($value, $statuses) &&
            auth()->user() !== null
        ) {

            Order::note('Order Status updated from ' . $statuses[$this->attributes['order_status']] . ' to  ' .
                $statuses[$value], $this->attributes['id']);
        }

        if ($value == 8) {
            $this->isCancelled();
        }

        $this->attributes['order_status'] = $value;
    }

    public static function getStatusFromOrder(int $index)
    {
        $list = [];

        $list[4] =     'TO BE PROCESSED';
        $list[6] =     'SHIPPED';
        $list[7] =     'SHIPPING HOLD';
        $list[8] =     'CANCELLED';
        $list[9] =     'SHIP IN WAP';
        $list[10] = 'RESHIP';
        $list[11] = 'ADDRESS HOLD';
        $list[12] = 'SHIP DATE HOLD';
        $list[15] = 'INCOMPATIBLE HOLD';
        $list[13] = 'PAYMENT HOLD';
        // $list[17] = 'FRAUD HOLD';
        $list[23] = 'OTHER HOLD';

        return $list[$index] ?? "Error";
    }
    public static function statuses($header = 0, $batched = 0, $order_status = NULL)
    {
        if ($header) {
            $list[0] =     'Select Order Status';
        }

        $list[4] =     'TO BE PROCESSED';
        $list[6] =     'SHIPPED';
        $list[7] =     'SHIPPING HOLD';
        $list[8] =     'CANCELLED';
        $list[9] =     'SHIP IN WAP';
        $list[10] = 'RESHIP';
        $list[11] = 'ADDRESS HOLD';
        $list[12] = 'SHIP DATE HOLD';
        $list[15] = 'INCOMPATIBLE HOLD';
        $list[13] = 'PAYMENT HOLD';
        // $list[17] = 'FRAUD HOLD';
        $list[23] = 'OTHER HOLD';

        return $list;
    }

    public function scopeStatus($query, $status)
    {
        if ($status == '0' || null === $status || $status == []) {
            return;
        }

        if ($status == 'not_cancelled') {
            return $query->where('order_status', '!=', 8);
        }

        if (is_array($status)) {
            return $query->whereIn('order_status', $status);
        }

        return $query->where('order_status', $status);
    }

    public static function note($note_text, $order_5p, $order_id = null)
    {
        $note = new Note();
        $note->note_text = $note_text;
        $note->order_5p = $order_5p;
        $note->order_id = $order_id;
        if (auth()->user()) {
            $note->user_id = auth()->user()->id;
        } else {
            $note->user_id = 87;
        }
        $note->save();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id')
            ->where('is_deleted', '0');
    }

    public function wap()
    {
        return $this->belongsTo('App\Wap', 'id', 'order_id');
    }

    public function hold_reason()
    {
        return $this->hasOne(Note::class, 'order_5p', 'id')
            ->where('note_text', 'LIKE', 'OH:%');
        // ->latest()
        // ->limit(1);
    }

    public function items()
    {
        return $this->hasMany(Item::class, 'order_5p', 'id')
            ->where('is_deleted', '0')
            ->selectRaw('items.*, items.item_quantity * items.item_unit_price as item_total_price');
    }

    public function sku_summary()
    {
        return $this->hasMany(Item::class, 'order_5p', 'id')
            ->where('is_deleted', '0')
            ->selectRaw('items.order_5p, items.item_thumb, items.child_sku, SUM(items.item_quantity) as quantity')
            ->groupBy('items.child_sku');
    }

    public function shippable_items()
    {
        return $this->hasMany(Item::class, 'order_5p', 'id')
            ->where('is_deleted', '0')
            ->searchStatus('shippable');
    }

    public function shipping()
    {
        return $this->hasMany('App\Ship', 'order_number', 'id')
            ->where('is_deleted', '0');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, "store_id", "store_id");
    }

    public function order_sub_total()
    {
        return $this->hasMany(Item::class, 'order_5p', 'id')
            ->where('is_deleted', '0')
            ->groupBy('order_5p')
            ->select([
                'order_5p',
                DB::raw('(SUM(item_unit_price * item_quantity)) AS sub_total'),
            ]);
    }

    public function shippingInfo()
    {
        return $this->hasMany('App\Ship', 'order_number', 'order_id');
    }

    public function notes()
    {
        return $this->hasMany(Note::class, 'order_5p', 'id');
    }

    public function scopeStoreId($query, $store_id = null)
    {
        //TODO - need to uncomment to apply permission

        // if ($store_id == 'all' || null === $store_id || $store_id == '' || $store_id == []) {
        //     $store_id = Store::where('permit_users', 'like', "%" . auth()->user()->id . "%")
        //         ->get()->pluck('store_id')->toArray();
        // }

        if ($store_id) {
            if (is_array($store_id)) {
                return $query->whereIn('orders.store_id', $store_id);
            } else {
                return $query->where('orders.store_id', $store_id);
            }
        } else {
            return $query;
        }
    }

    public function scopeSearchShipping($query, $shipping_method)
    {
        if ($shipping_method == 'all' || null === $shipping_method || $shipping_method == '') {
            return;
        }

        $customer_ids = Customer::where('shipping', $shipping_method)
            ->get()
            ->pluck('id');

        return $query->whereIn('customer_id', $customer_ids);
    }

    public static $search_in = [
        'orders.order_id' => 'Store Order#',
        'orders.id'       => '5P#',
        'company'         => 'Company',
        'total'           => 'Order Total',
        'coupon'          => 'Coupon',
        'coupon_amount'   => 'Coupon Amount',
        'customer'        => 'Customer',
        'email'           => 'Email',
        'ship_state'      => 'State',
        'item_desc'       => 'Item Description',
        'shipping_charge' => 'Shipping'
    ];

    public function scopeSearch($query, $search_for, $operator, $search_in)
    {
        if ($search_for == null && !strpos($operator, 'blank')) {
            return;
        }

        if (in_array($search_in, array_keys(static::$search_in))) {
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
                    // case 'blank':
                    // 	return $this->findBlanks($query, $search_in, 0);
                    // 	break;
                    // case 'not_blank':
                    // 	return $this->findBlanks($query, $search_in, 1);
                    // 	break;
                default:
                    $op = 'LIKE';
                    $search_for = sprintf("%%%s%%", $search_for);
                    break;
            }

            if ($search_in == 'orders.order_id') {

                return $query->where(function ($q) use ($op, $search_for) {
                    $q->where('orders.order_id',  $op, $search_for)
                        ->orWhere('purchase_order', $op, $search_for);
                });
            } else if ($search_in == 'customer') {

                return $query->whereHas('customer', function ($q) use ($op, $search_for) {
                    $q->where('ship_full_name', $op, $search_for);
                });
            } else if ($search_in == 'email') {

                return $query->whereHas('customer', function ($q) use ($op, $search_for) {
                    $q->where('bill_email', $op, $search_for);
                });
            } else if ($search_in == 'company') {

                $key = array_search(str_replace('%', '', $search_for), Store::$companies);

                return $query->whereHas('store', function ($q) use ($op, $key) {
                    $q->where('company', $op, $key);
                });
            } else if ($search_in == 'item_desc') {

                return $query->whereHas('items', function ($q) use ($op, $search_for) {
                    $q->where('item_description', $op, $search_for);
                });
            } else if ($search_in == 'coupon') {

                if ($op == 'NOT LIKE' || $op == '!=') {
                    return $query->where(function ($q) use ($op, $search_for) {
                        $q->whereNull('coupon_id')
                            ->orWhere(DB::raw("TRIM(coupon_id)"), $op, $search_for);
                    })
                        ->where(function ($q) use ($op, $search_for) {
                            $q->whereNull('promotion_id')
                                ->orWhere(DB::raw("TRIM(promotion_id)"), $op, $search_for);
                        });
                } else {
                    return $query->where(function ($q) use ($op, $search_for) {
                        $q->where(DB::raw("TRIM(coupon_id)"), $op, $search_for)
                            ->orWhere(DB::raw("TRIM(promotion_id)"), $op, $search_for);
                    });
                }
            } else if ($search_in == 'coupon_amount') {

                if ($op == 'NOT LIKE' || $op == '!=') {
                    return $query->where(function ($q) use ($op, $search_for) {
                        $q->whereNull('coupon_value')
                            ->orWhere('coupon_value', $op, $search_for);
                    })
                        ->where(function ($q) use ($op, $search_for) {
                            $q->whereNull('promotion_value')
                                ->orWhere('promotion_value', $op, $search_for);
                        });
                } else {
                    return $query->where(function ($q) use ($op, $search_for) {
                        $q->where('coupon_value', $op, $search_for)
                            ->orWhere('promotion_value', $op, $search_for);
                    });
                }
            } else if ($search_in == 'ship_state') {

                return $query->whereHas('customer', function ($q) use ($op, $search_for) {
                    $q->where('ship_state', $op, $search_for);
                });
            } else {
                // dd($search_in, $op, $search_for);
                return $query->where($search_in, $op, $search_for);
            }
        }

        return;

        // $replaced = str_replace(" ", "", $search_for);
        // $values = explode(",", trim($replaced, ","));
        // 
        // if ( $search_in == 'store_order' ) {
        // 	$values = array_map(function ($value) {
        // 		return str_ireplace([
        // 			'M-',
        // 			'S-',
        // 		], "", $value);
        // 	}, $values);
        // 
        // 	return $query->where('short_order', 'REGEXP', implode("|", $values));
        // }
        // if ( $search_in == 'five_p_order' ) {
        // 	$values = array_map(function ($value) {
        // 		return intval($value);
        // 	}, $values);
        // 
        // 	return $query->where('id', 'REGEXP', implode("|", $values));
        // }
    }

    public function scopeWithinDate($query, $start_date, $end_date)
    {
        if (!$start_date) {
            return;
        }
        $starting = sprintf("%s 00:00:00", $start_date);
        $ending = sprintf("%s 23:59:59", $end_date ? $end_date : $start_date);

        return $query->where('order_date', '>=', $starting)
            ->where('order_date', '<=', $ending);
    }

    public function outputArray()
    {
        $statuses = Order::statuses();

        return [
            'App\Order',
            $this->id,
            url(sprintf('/orders/details/%s', $this->id)),
            'Order: ' . $this->short_order,
            $this->customer->ship_full_name ?? "",
            $statuses[$this->order_status]
        ];
    }
}
