<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory;

    public $imageToUse = "not_set";

    public function setBatchNumberAttribute ($value)
    {
        if (!isset($this->attributes['batch_number']) ||
            ($this->attributes['batch_number'] == 0 || $this->attributes['batch_number'] == NULL) &&
            !ctype_alpha( substr($value, 0, 1) )) {

            $this->attributes['batch_number'] = $value;

        } else {

            $id = $this->attributes['id'];

            Batch::note($this->attributes['batch_number'], 0, '3', "Item $id removed from Batch and put into $value");

            if (ctype_alpha( substr($value, 0, 1) )) {
                $old_batch = substr($value, strpos($value, '-') + 1);
            } else {
                $old_batch = $this->attributes['batch_number'];
            }

            $this->attributes['batch_number'] = $value;

            $mindates = Item::join('orders', 'items.order_5p', '=', 'orders.id')
                ->whereIn('items.batch_number', [$old_batch,$value])
                ->where('items.is_deleted', '0')
                ->selectRaw('items.batch_number, MIN(orders.order_date) as min')
                ->groupBy('items.batch_number')
                ->orderBy('items.batch_number', 'DESC')
                ->get();

            $found  = false;

            foreach ($mindates as $mindate) {

                $date_batch = Batch::where('batch_number', $mindate->batch_number)
                    ->first();

                if (count($date_batch) > 0) {
                    $date_batch->min_order_date = $mindate->min;
                    $date_batch->save();

                    if ($date_batch->batch_number == $value) {
                        $found = true;
                    }
                }

            }

            if (!$found) {

                $order = Order::find($this->attributes['order_5p']);

                if ($order) {
                    Batch::where('batch_number', $value)
                        ->update([
                            'min_order_date' => $order->order_date
                        ]);
                }
            }

            Batch::isFinished($old_batch);
        }
    }


    public static function getStatusList ()
    {
        $list = array();

        $list[1] = 'Production';
        $list[2] = 'Shipped';
        $list[3] = 'Rejected';
        $list[4] = 'Back Order';
        $list[5] = 'Refunded';
        $list[6] = 'Cancelled';
        $list[7] = 'Customer Service';
        $list[8] = 'Reshipment';
        $list[9] = 'WAP';

        return $list;
    }


    public function setItemStatusAttribute ($value)
    {

        $value = strtolower($value);

        if ($value == 'production' || $value == '1') {
            $this->attributes['item_status'] = 1;
            $msg = 'Production';
        } elseif ($value == 'shipped' || $value == '2') {
            if ($this->attributes['item_status'] != 8) { // no inventory for reship
                InventoryAdjustment::shipItem($this->attributes['child_sku'], $this->attributes['id'], $this->attributes['item_quantity'], $this->attributes['tracking_number']);
            }
            $this->attributes['item_status'] = 2;
            $msg = 'Shipped';

            Rejection::where('item_id', $this->attributes['id'])
                ->where('complete', '0')
                ->update(['complete' => '1']);

        } elseif ($value == 'rejected' || $value == '3') {
            $this->attributes['item_status'] = 3;
            $msg = 'Rejected';
        } elseif ($value == 'backorder' || $value == 'back order' || $value == '4') {
            $this->attributes['item_status'] = 4;
            $msg = 'Back Order';
        } elseif ($value == 'refunded' || $value == '5') {
            $this->attributes['item_status'] = 5;
            $msg = 'Refunded';
        } elseif ($value == 'cancelled' || $value == 'canceled' || $value == '6') {

            $this->attributes['item_status'] = 6;
            $msg = 'Cancelled';

            if (isset($this->attributes['batch_number']) && $this->attributes['batch_number'] != '0') {
                Batch::isFinished($this->attributes['batch_number']);
            }

            if (isset($this->attributes['id'])) {
                Rejection::where('item_id', $this->attributes['id'])
                    ->where('complete', '0')
                    ->update(['complete' => '1']);
            }

        } elseif ($value == 'customer service' || $value == '7') {
            $this->attributes['item_status'] = 7;
            $msg = 'Customer Service';
        } elseif ($value == 'reship' || $value == 'reshipment' || $value == '8') {
            $this->attributes['item_status'] = 8;
            $msg = 'Reshipment';
        } elseif ($value == 'wap' || $value == '9') {
            $this->attributes['item_status'] = 9;
            $msg = 'WAP';
        } else {
            Log::error('Unrecognized status in setItemStatusAttribute: ' . $value);
            $this->attributes['item_status'] = 0;
            $msg = '?';
        }

        if (isset($this->attributes['id'])) {
            Order::note('Item ' . $this->attributes['id'] . ' status changed to  ' . $msg, $this->attributes['order_5p'], $this->attributes['order_id']);
        }
    }


    public function getItemStatusAttribute ($value)
    {
        if ($value == 1) {
            return 'production';
        } elseif ($value == 2) {
            return 'shipped';
        } elseif ($value == 3) {
            return 'rejected';
        } elseif ($value == 4) {
            return 'back order';
        } elseif ($value == 5) {
            return 'refunded';
        } elseif ($value == 6) {
            return 'cancelled';
        } elseif ($value == 7) {
            return 'customer service';
        } elseif ($value == 8) {
            return 'reshipment';
        } elseif ($value == 9) {
            return 'wap';
        } elseif ($value == 0) {
            return 'default';
        } else {
            return 'Unknown';
        }
    }

    public function getItemThumbAttribute ($value)
    {

        if (substr($value, 0, 31)  != 'http://order.monogramonline.com' && $this->product &&
            substr($this->product->product_thumb, 0, 31)  == 'http://order.monogramonline.com') {

            $product = Product::where('product_model', $this->item_code)
                ->select('product_thumb')
                ->first();

            $thumb = $product->product_thumb;
            $this->item_thumb = $thumb;
            $this->save();
            return $thumb;

        }

        return $value;

    }

    public function scopeSearchStatus ($query, $status)
    {
        if ( !$status || $status == null || $status == []) {
            return $query->where('item_status', '!=', 6);
        }

        if ( $status == 'all') {
            return;
        }

        if (is_array($status)) {
            return $query->whereIn('item_status', $status);
        }

        $status = strtolower($status);

        if($status == 'production' || $status == 1){
            return $query->where('item_status', 1);
        }

        if($status == 'shipped' || $status == 2){
            return $query->where('item_status', 2);
        }

        if($status == 'rejected' || $status == 3){
            return $query->where('item_status', 3);
        }

        if($status == 'back order' || $status == 'backorder' || $status == 4){
            return $query->where('item_status', 4);
        }

        if($status == 'refunded' || $status == 5){
            return $query->where('item_status', 5);
        }

        if($status == 'customer service' || $status == 7){
            return $query->where('item_status', 7);
        }

        if($status == 'reship' || $status == 'reshipment' || $status == 8){
            return $query->where('item_status', 8);
        }

        if($status == 'wap' || $status == 9){
            return $query->where('item_status', 9);
        }

        if($status == 'shippable'){
            return $query->whereIn('item_status', array(1,3,4,9));
        }

        if($status == 'pending'){
            return $query->whereIn('item_status', array(1,3,4));
        }

        if($status == 'default'){
            return $query->where('item_status', 0);
        }

        if($status == 'cancelled' || 'canceled'){
            return $query->whereHas('order', function($q) {
                return $q->where('orders.order_status', '=',  '8');
            });
        }

        Log::error('Unrecognized Status in Item searchStatus: ' . $status);

    }

    public function inventory_unit ()
    {
        return $this->hasMany(InventoryUnit::class, 'child_sku', 'child_sku');
    }

    public function order ()
    {
        return $this->belongsTo('App\Order', 'order_5p')
            ->where('is_deleted', 0);
    }

    public function batch ()
    {
        return $this->belongsTo('App\Batch', 'batch_number', 'batch_number');
    }

    public function spec_sheet ()
    {
        return $this->belongsTo('App\SpecificationSheet', 'item_code', 'product_sku');
    }

    private function tableColumns ()
    {
        $columns = $this->getConnection()
            ->getSchemaBuilder()
            ->getColumnListing($this->getTable());

        return array_slice($columns, 0, -1);
    }

    public function parameter_option ()
    {
        return $this->belongsTo('App\Option', 'child_sku', 'child_sku');
    }

    public function rejection()
    {
        return $this->hasOne('App\Rejection', 'item_id', 'id')
            ->where('rejections.complete', '0');
    }

    public function rejections()
    {
        return $this->hasMany('App\Rejection', 'item_id', 'id')
            //->where('rejections.complete', '0')
            ->latest();
    }

    public function product ()
    {
        /*return $this->belongsTo('App\Product', 'item_id', 'id_catalog')
                    ->where('is_deleted', 0);*/
        return $this->belongsTo(Product::class, 'item_code', 'product_model')
            ->where('is_deleted', 0);
    }

    public function store ()
    {
        return $this->belongsTo('App\Store', 'store_id', 'store_id');
    }

    public function groupedItems ()
    {
        // return $this->hasMany('App\Item', 'batch_number', 'batch_number');
        return $this->hasMany('App\Item', 'batch_number', 'batch_number')
            ->whereNull('tracking_number')
            ->where('batch_number','!=','0')
            ->where('is_deleted', 0);
    }

    public function inventoryunit ()
    {
        return $this->hasMany(InventoryUnit::class, 'child_sku', 'child_sku');
    }

    public function wap_item ()
    {
        return $this->hasOne('App\WapItem', 'item_id', 'id');
    }


    private function commaTrimmer ($string)
    {
        return trim($string, ",");
    }

    private function exploder ($string)
    {
// 		return explode(",", str_replace(" ", "", $this->commaTrimmer($string)));
        return explode(",", trim($string));
    }

    public function shipInfo ()
    {
        #return $this->belongsTo('App\Ship', 'item_id', 'id');
        return $this->hasOne('App\Ship', 'tracking_number', 'tracking_number')
            ->latest();
    }

    public function allChildSkus ()
    {
        return $this->hasMany('App\Option', 'parent_sku', 'item_code')
            ->where('batch_route_id', '!=', 115)
            ->select(['parent_sku', 'parameter_options.child_sku']);
    }

    public function scopeWithinDate ($query, $start_date, $end_date)
    {
        if ( !$start_date ) {
            return;
        }
        $starting = sprintf("%s 00:00:00", $start_date);
        $ending = sprintf("%s 23:59:59", $end_date ? $end_date : $start_date);

        return $query->where('order_date', '>=', $starting)
            ->where('order_date', '<=', $ending);
    }


    public function scopeSearch ($query, $search_for, $search_in)
    {
        if ( !$search_for ) {
            return;
        }

        $values = $this->exploder($search_for);

        if ( $search_in == 'all' ) {

            return;

        } elseif ( $search_in == '5p_order' ) {

            $order_ids = Order::whereIn('id', $values)
                ->get()
                ->pluck('id');

            if ( empty( $order_ids ) ) {
                return $query->where('order_5p', 'not found');;
            }

            return $query->where('order_5p', $order_ids);

        } elseif ( $search_in == 'company' ) {

            $key = array_search(str_replace('%', '', $search_for), Store::$companies);

            return $query->whereHas('store', function($q) use ($key) {
                return $q->where('company', $key);
            });

        } elseif ( $search_in == 'item_id' ) {

            return $query->whereIn('id', $values);

        } elseif ( $search_in == 'customer' ) {

            $customer_ids = Customer::where('ship_full_name', 'REGEXP', implode("|", $values))
                ->orWhere('bill_full_name', 'REGEXP', implode("|", $values))
                ->select('id')
                ->get();

            $order_ids = Order::whereIn('customer_id', $customer_ids)
                ->where('is_deleted', '0')
                ->select('id')
                ->get();

            if ( count($order_ids) ) {
                return $query->whereIn('order_5p', $order_ids);
            } else {
                return $query->whereNull('order_5p');
            }

        } elseif ( $search_in == 'bill_email' ) {

            $customer_ids = Customer::whereIn('bill_email', $values)
                ->where('is_deleted', '0')
                ->select('id')
                ->get();

            $order_ids = Order::whereIn('customer_id', $customer_ids)
                ->where('is_deleted', '0')
                ->select('id')
                ->get();

            if ( count($order_ids) ) {
                return $query->whereIn('order_5p', $order_ids);
            } else {
                return $query->whereNull('order_5p');
            }

        } elseif ( $search_in == 'order' ) {

            return $query->where('items.order_id', 'REGEXP', implode("|", $values));

        } elseif ( $search_in == 'order_date' ) {

            $order_ids = Order::where('order_date', 'REGEXP', implode("|", $values))
                ->where('is_deleted', '0')
                ->get()
                ->pluck('id');

            return $query->whereIn('order_5p', $order_ids);

        } elseif ( $search_in == 'coupon_id' ) {

            $order_ids = Order::where('is_deleted', '0')
                ->where(function ($q) use ($values) {
                    $q->where(DB::raw("TRIM(coupon_id)"), 'LIKE', implode("%|", $values)."%")
                        ->orWhere(DB::raw("TRIM(promotion_id)"), 'LIKE', implode("%|", $values)."%");
                })
                ->get()
                ->pluck('id');

            return $query->whereIn('order_5p', $order_ids);

        } elseif ( $search_in == 'store_id' ) {

            return $query->whereIn('store_id', $values);

        } elseif ( $search_in == 'state' ) {

            $order_ids = Customer::where('ship_state', 'REGEXP', implode("|", $values))
                ->get()
                ->pluck('order_id');
            if ( count($order_ids) ) {
                return $query->whereIn('order_id', $order_ids);
            } else {
                return $query->whereNull('order_5p');
            }

        } elseif ( $search_in == 'description' ) {

            return $query->where('item_description', 'REGEXP', implode("|", $values));

        } elseif ( $search_in == 'item_option' ) {
// 			dd( "%".implode("|", $values)."%");
            return $query->where('item_option', 'LIKE', "%".implode("|", $values)."%");

        } elseif ( $search_in == 'item_code' ) {

            return $query->where('item_code', 'REGEXP', implode("|", $values));

        } elseif ( $search_in == 'child_sku' ) {

            return $query->where('child_sku', 'REGEXP', implode("|", $values));

        } elseif ( $search_in == 'exact_child_sku' ) {

            return $query->where('child_sku', $values);

        } elseif ( $search_in == 'batch' ) {

            if (count($values) == 1 && $values[0] == 'zero') {
                return $query->where('batch_number', '=', 0);
            }

            return $query->whereIn('batch_number', $values);

        } elseif ( $search_in == 'batch_creation_date' ) {

            return $query->whereHas('batch', function($q) use ($values) {
                return $q->where('creation_date', 'REGEXP', implode("|", $values));
            });

        } elseif ( $search_in == 'batch_status' ) {

            return $query->whereHas('batch', function($q) use ($values) {
                return $q->whereIn('status',  $values);
            });

        } elseif ( $search_in == 'order_status' ) {

            return $query->whereHas('order', function($q) use ($values) {
                return $q->whereIn('order_status',  $values);
            });

        } elseif ( $search_in == 'tracking_number' ) {

            if ($values[0] != 'NULL') {
                return $query->whereHas('shipInfo', function($q) use ($values) {
                    return $q->where('shipping_id', 'REGEXP', implode("|", $values));
                });
            } else {
                return $query->whereNull('tracking_number');
            }

        } elseif ( $search_in == 'station_name' ) {

            $station = Station::where('station_name', $values[0])->first();

            if (count($station) == 1) {
                $station_id = $station->id;
            } else {
                $station_id = 0;
            }
            return $query->whereHas('batch', function($q) use ($station_id) {
                return $q->where('station_id', $station_id);
            });
        } elseif ( $search_in == 'station_id' ) {

            $station_id = $values[0];

            return $query->whereHas('batch', function($q) use ($station_id) {
                return $q->where('station_id', $station_id);
            });

        } elseif ( $search_in == 'stock_number' ) {

            return $query->whereHas('inventory_unit', function($q) use ($values) {
                return $q->where('stock_no_unique','REGEXP', implode("|", $values));
            });

        } else {
            return $query->whereNull('order_5p');
        }
    }

    public function scopeUnBatched ($query, $unbatched)
    {
        if ( !$unbatched || $unbatched == 0 ) {
            return;
        }

        return $query->where('batch_number', '0')
            ->whereNull('tracking_number')
            ->where('item_status', 1)
            ->whereHas('order', function($q) {
                return $q->whereIn('orders.order_status',  [4,11,12,7,9]);
            });
    }

    public function scopeSearchBatch ($query, $batch_number)
    {
        if ( !$batch_number ) {
            return;
        }

        return $query->whereIn('batch_number', explode(",", trim($batch_number, ",")));
    }

    public function scopeSearchStore ($query, $store_id)
    {
        if ( !$store_id || $store_id == null || $store_id == []) {
            return;
        }

        if (is_array($store_id)) {

            return $query->whereIn('items.store_id', $store_id);

        } else {

            $values = $this->exploder($store_id);

            return $query->where('items.store_id', 'REGEXP', implode("|", $values));
        }
    }

    public function scopeWithManufacture ($query, $manufacture_id)
    {
        if ( !$manufacture_id ) {
            return;
        }

        return $query->where('items.manufacture_id', $manufacture_id);

    }

    public function scopeSearchTrackingDate ($query, $tracking_date)
    {
        if ( !$tracking_date ) {
            return;
        }
        // postmark_date transaction_datetime
        $tracking = Ship::where('transaction_datetime', 'LIKE', $tracking_date.'%')
            ->get([
                'tracking_number',
            ])
            ->toArray();
        return $query->whereIn('tracking_number', $tracking);
    }

    public function scopeSearchShipDate ($query, $start_date, $end_date)
    {
        if ( !$start_date || !$end_date ) {
            return;
        }

        $start = $start_date . ' 00:00:00';
        $end = $end_date . ' 23:59:59';

        return $query->whereHas('shipInfo', function($q) use ($start, $end) {
            return $q->where('transaction_datetime', '>', $start)
                ->where('transaction_datetime', '<', $end);
        });
    }

    public function scopeSearchOptionText ($query, $option_text)
    {
        if ( !$option_text ) {
            return;
        }
        $trimmed_text = trim($option_text);
// 		$underscored_text = str_replace(" ", "_", $trimmed_text);
// 		return $query->where('item_option', 'LIKE', sprintf("%%%s%%", $underscored_text));
        return $query->where('item_option', 'LIKE', sprintf("%%%s%%", $trimmed_text));
    }

    public function scopeSearchOrderIds ($query, $order_ids)
    {
        if ( !$order_ids ) {
            return;
        }

        $ids = explode(",", trim($order_ids, ","));

        return $query->where('order_id', 'REGEXP', implode("|", $ids));
    }

    public function scopeSearchDate ($query, $start_date, $end_date)
    {
        if ( !$start_date ) {
            return;
        }

        return $query->whereHas('order', function($q) use ($start_date, $end_date) {
            return $q->withinDate($start_date, $end_date);
        });
    }

    public function scopeSearchOrderDate ($query, $start_date, $end_date)
    {
        if ( !$start_date ) {
            return;
        }
        // $starting = sprintf("%s 00:00:00", $start_date);
        // $ending = sprintf("%s 23:59:59", $end_date ? $end_date : $start_date);

        return $query->whereHas('order', function($q) use ($start_date, $end_date) {
            return $q->withinDate($start_date, $end_date);
        });

    }


    public function ScopeSearchBatchDate ($query, $start_date, $end_date)
    {
        if ( !$start_date || ! $end_date ) {
            return;
        }

        $starting = sprintf("%s 00:00:00", $start_date);
        $ending = sprintf("%s 23:59:59", $end_date );

        return $query->whereHas('batch', function($q) use ($starting, $ending) {
            return $q->searchMinChangeDate($starting)
                ->searchMaxChangeDate($ending);
        });

    }


    public static function getTableColumns ()
    {
        return (new static())->tableColumns();
    }

    public function scopeSearchItem ($query, $item_id)
    {
        if ( !$item_id or $item_id == '0' ) {
            return;
        }

        if (substr($item_id, 0, 4) == 'ITEM') {
            $item_id = substr($item_id, 4);
        }

        return $query->where('id', $item_id);
    }

    public function scopeSearchGraphicStatus ($query, $status)
    {

        if ( !$status || $status == '0') {
            return $query->whereHas('rejection', function($q) {
                return $q->where('graphic_status', '!=', 4);
            });
        }

        return $query->whereHas('rejection', function($q) use ($status) {
            return $q->where('graphic_status', $status);
        });
    }

    public function scopeSearchRejectionReason ($query, $reason)
    {

        if ( !$reason || $reason == '0') {
            return;
        }

        return $query->whereHas('rejection', function($q) use ($reason) {
            return $q->where('rejection_reason', $reason);
        });
    }

    public function scopeSearchSection ($query, $section)
    {

        if ( !$section || $section == '0') {
            return;
        }

        return $query->whereHas('batch', function($q) use ($section) {
            return $q->where('batches.section_id', $section);
        });
    }

    public function scopeSearchRejectReason ($query, $reason)
    {

        if ( !$reason || $reason == '0') {
            return;
        }

        return $query->whereHas('rejection', function($q) use ($reason) {
            return $q->where('rejection_reason', $reason);
        });
    }

    public static function scopePendingOrders ($query, $pending)
    {
        if ( !$pending ) {
            return;
        }

        if ($pending == 1) {
            return $query->whereHas('batch', function($q) {
                return $q->searchStatus('active');
            });
        } elseif ($pending == 2) {
            return $query->where('batch_number', '0')
                ->whereIn('item_status', [1,4]);
        }
    }

    public static function backOrderItems()
    {
        $new_items = Inventory::join('inventory_unit', 'inventories.stock_no_unique', '=', 'inventory_unit.stock_no_unique')
            ->join('items', 'inventory_unit.child_sku', '=', 'items.child_sku')
            ->join('orders', 'items.order_5p', '=', 'orders.id')
            ->where('orders.order_status', 4)
            ->whereIn('item_status', [1,4])
            ->where('batch_number', '0')
            ->where('orders.is_deleted', '0')
            ->where('items.is_deleted', '0')
            ->where('inventories.stock_no_unique', '!=', 'ToBeAssigned')
            ->select('items.id', 'items.item_quantity', 'inventories.qty_on_hand', 'inventories.qty_av', 'inventories.stock_no_unique')
            ->orderBy('items.id', 'ASC')
            ->get();

        $stock_nos = $new_items->groupBy('stock_no_unique')->all();

        foreach($stock_nos as $stock_no) {

            if ($stock_no->first()->qty_on_hand < 0) {
                Item::whereIn('id', $stock_no->pluck('id'))
                    ->update(['item_status' => 1]);
            } elseif ($stock_no->first()->qty_av + $stock_no->sum('item_quantity') <= 0) {
                //backorder everything
                Item::whereIn('id', $stock_no->pluck('id'))
                    ->update(['item_status' => 1]);
                // ->update(['item_status' => 4]);

            } elseif ($stock_no->first()->qty_av < 0) {
                //backorder some
                $pending_qty = $stock_no->first()->qty_av + $stock_no->sum('item_quantity');
                //echo 'QTY AV: ' . $stock_no->first()->qty_av . ', QTY_NEW: ' . $stock_no->sum('item_quantity') . ', QTY_PENDING: ' . $pending_qty . ' <BR>';

                foreach($stock_no as $item) {

                    $item = Item::find($item->id);

                    if ($item->item_quantity <= $pending_qty) {
                        $pending_qty = $pending_qty - $item->item_quantity;
                        if ($item->item_status != 1) {
                            $item->item_status = 1;
                            $item->save();
                        }
                    } else {
                        if ($item->item_status != 4) {
                            $item->item_status = 1;
                            // $item->item_status = 4;
                            $item->save();
                        }
                    }
                }
            } elseif ($stock_no->first()->qty_av > 0) {
                //unbackorder everything
                Item::whereIn('id', $stock_no->pluck('id'))
                    ->update(['item_status' => 1]);
            }
        }
    }

    public function outputArray()
    {
        return [  'App\Item',
            $this->id,
            url(sprintf('items?search_for_first=%s&search_in_first=item_id', $this->id)),
            'Item: ' . $this->id,
            $this->item_description,
            $this->item_status
        ];
    }
}
