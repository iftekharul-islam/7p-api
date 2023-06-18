<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Wap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WapController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->has('end_date')) {
            $end_date = NULL;

            $bins = Wap::with('order.shippable_items', 'order.store', 'order.items')
                // ->whereHas('order.store', function ($q) {
                //     // $q->where('permit_users', 'like', "%" . auth()->user()->id . "%")
                //     $q->where('is_deleted', '0')
                //         ->where('invisible', '0');
                // })
                ->whereNotNull('wap.order_id')
                ->select('wap.*', DB::raw('(SELECT MAX(created_at) FROM wap_items WHERE wap_items.bin_id = wap.id ) as last,
                                                (SELECT COUNT(*) FROM wap_items WHERE wap_items.bin_id = wap.id ) as item_count'))
                ->orderBy('order_id', 'ASC');
        } else {
            $end_date = $request->get('end_date');

            $bin_list = Order::join('wap', 'wap.order_id', '=', 'orders.id')
                // ->whereHas('store', function ($q) {
                //     $q->where('permit_users', 'like', "%" . auth()->user()->id . "%");
                // })
                ->where('orders.order_date', '<', $end_date)
                ->whereNotNull('wap.order_id')
                ->selectRaw('wap.id')
                ->get()
                ->pluck('id')
                ->toArray();

            $bins = Wap::with('order.shippable_items', 'order.items')
                // ->whereIn('wap.id', $bin_list)
                ->select('wap.*', DB::raw('(SELECT MAX(created_at) FROM wap_items WHERE wap_items.bin_id = wap.id ) as last,
                                                    (SELECT COUNT(*) FROM wap_items WHERE wap_items.bin_id = wap.id ) as item_count'))
                ->orderBy('order_id', 'ASC');
        }

        $sorted_bins = $bins->orderBy('last')->get();
        $bins = $bins->get();

        $statuses = [];
        foreach (Order::statuses() as $key => $value) {
            $statuses[] = [
                'label' => $value,
                'value' => $key,
            ];
        }
        return response()->json([
            'bins' => $bins,
            'sorted_bins' => $sorted_bins,
            'statuses' => $statuses,
        ]);
    }
}
