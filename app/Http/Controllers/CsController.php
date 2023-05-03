<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Note;
use App\Models\Order;
use App\Models\Rejection;
use App\Models\RejectionReason;
use Illuminate\Http\Request;

class CsController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab') ?? 'address';


        $addresses = Order::with('customer', 'notes')
            ->where('order_status', 11)
            ->where('is_deleted', '0')
            ->get();

        if ($request->has('stock_no_unique')) {
            $backorders = Item::with('order.notes', 'order.customer')
                ->join('inventory_unit', 'items.child_sku', '=', 'inventory_unit.child_sku')
                ->where('items.item_status', 4)
                ->where('items.batch_number', '!=', '0')
                ->where('items.is_deleted', '0')
                ->where('inventory_unit.stock_no_unique', $request->get('stock_no_unique'))
                ->orderBy('items.created_at')
                ->get();
        } else {
            $backorders = Item::join('inventory_unit', 'items.child_sku', '=', 'inventory_unit.child_sku')
                ->join('inventories', 'inventory_unit.stock_no_unique', '=', 'inventories.stock_no_unique')
                ->join('orders', 'items.order_5p', '=', 'orders.id')
                ->where('items.item_status', 4)
                ->where('items.is_deleted', '0')
                ->selectRaw('inventory_unit.stock_no_unique, inventories.stock_name_discription,
																	MIN(orders.order_date) as min_date,COUNT(items.id) as qty')
                ->groupBy('inventory_unit.stock_no_unique')
                ->orderBy('min_date')
                ->get();
        }
        $reship = Order::with('customer', 'items')
            ->where('order_status', 10)
            ->where('is_deleted', '0')
            ->get();
        $incompatible = Order::with('customer', 'store', 'items')
            ->where('order_status', 15)
            ->where('is_deleted', '0')
            ->get();
        $payment = Order::with('customer', 'items', 'store', 'hold_reason')
            ->where('order_status', 13)
            ->where('is_deleted', '0')
            ->get();
        $shipping = Order::with('customer', 'items', 'store', 'hold_reason', 'wap')
            ->whereIn('order_status', [7, 12])
            ->where('is_deleted', '0')
            ->get();
        $other = Order::with('store', 'customer', 'items', 'store', 'hold_reason')
            ->where('order_status', 23)
            ->where('is_deleted', '0')
            ->get();

        $count = [];
        $count['address'] = count($addresses);
        $count['rejects'] = Item::searchStatus('rejected')
            ->searchGraphicStatus(4)
            ->where('is_deleted', '0')
            ->count();
        $count['backorder'] = Item::where('items.item_status', 4)
            ->where('items.batch_number', '!=', '0')
            ->where('items.is_deleted', '0')
            ->count();
        $count['reship'] = count($reship);
        $count['incompatible'] = count($incompatible);
        $count['payment'] = count($payment);
        $count['shipping'] = count($shipping);
        $count['other'] = count($other);

        if ($tab == 'address') {
            $data = $addresses;
        }
        if ($tab == 'rejects') {
            if (!$request->has('rejection_reason') && !$request->has('reject_batch')) {

                $reject_summary = Rejection::join('items', 'rejections.item_id', '=', 'items.id')
                    ->where('items.is_deleted', '0')
                    ->where('items.item_status', 3)
                    ->where('complete', '0')
                    ->where('graphic_status', 4)
                    ->selectRaw('rejection_reason, COUNT(rejections.id) as count')
                    ->groupBy('rejection_reason')
                    ->orderBy('rejections.rejection_reason', 'ASC')
                    ->get();

                $reasons = RejectionReason::getReasons();
            } else {

                $rejects = Item::with('rejection.rejection_reason_info', 'rejection.user', 'rejection.from_station', 'order', 'batch')
                    ->where('is_deleted', '0')
                    ->searchStatus('rejected')
                    ->searchGraphicStatus(4)
                    ->searchRejectionReason($request->get('rejection_reason'))
                    ->searchBatch($request->get('reject_batch'))
                    ->orderBy('id', 'ASC')
                    ->get();

                $reject_batches = array();

                foreach ($rejects as $reject) {
                    $reject_batches[$reject->batch_number][] = $reject;
                }
            }
        }
        if ($tab == 'backorder') {
            $data = $backorders;
        }
        if ($tab == 'reship') {
            $data = $reship;
        }
        if ($tab == 'incompatible') {
            $data = $incompatible;
        }
        if ($tab == 'payment') {
            $data = $payment;
        }
        if ($tab == 'shipping') {
            $data = $shipping;
        }
        if ($tab == 'other') {
            $data = $other;
        }
        if ($tab == 'updates') {
            $data = Note::with('order.store', 'user')
                ->where('note_text', 'LIKE', 'CS:%')
                ->limit(100)
                ->latest()
                ->get();
        }

        return response()->json([
            'data' => $data,
            'count' => $count,
        ]);
    }
}
