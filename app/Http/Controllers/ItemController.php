<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Option;
use App\Models\Order;
use App\Models\Store;
use Illuminate\Http\Request;
use library\Helper;
use Ship\Batching;

class ItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $items = [];
        $item_sum = [];
        if (count($request->all()) > 1) {
            //mirror in csv export
            $items = Item::with('order.customer', 'store', 'batch.route', 'shipInfo', 'batch', 'wap_item.bin')
                ->where('is_deleted', '0')
                ->searchStore($request->get('store'))
                ->searchStatus($request->get('status'))
                ->searchSection($request->get('section'))
                ->search($request->get('search_for_first'), $request->get('search_in_first'))
                ->search($request->get('search_for_second'), $request->get('search_in_second'))
                ->searchTrackingDate($request->get('tracking_date'))
                ->searchOrderDate($request->get('start_date'), $request->get('end_date'))
                ->searchBatchDate($request->get('scan_start_date'), $request->get('scan_end_date'))
                ->unBatched($request->get('unbatched'))
                ->latest()
                ->paginate($request->get('perPage', 50));

            $item_sum = Item::where('is_deleted', '0')
                ->searchStore($request->get('store'))
                ->searchStatus($request->get('status'))
                ->searchSection($request->get('section'))
                ->search($request->get('search_for_first'), $request->get('search_in_first'))
                ->search($request->get('search_for_second'), $request->get('search_in_second'))
                ->searchTrackingDate($request->get('tracking_date'))
                ->searchOrderDate($request->get('start_date'), $request->get('end_date'))
                ->searchBatchDate($request->get('scan_start_date'), $request->get('scan_end_date'))
                ->unBatched($request->get('unbatched'))
                ->selectRaw('sum(items.item_quantity) as sum, count(items.id) as count')
                ->first();

            $items->getCollection()->transform(function ($data) {
                $data['item_option'] = Helper::optionTransformer($data->item_option, 1, 0, 0, 1, 0);
                return $data;
            });

            set_time_limit(0);
        }

        $unassignedProductCount = Option::where('batch_route_id', Helper::getDefaultRouteId())->count();

        $unassignedOrderCount = Item::join('parameter_options', 'items.child_sku', '=', 'parameter_options.child_sku')
            ->where('parameter_options.batch_route_id', Helper::getDefaultRouteId())
            ->where('items.is_deleted', '0')
            ->whereIn('items.item_status', [1, 4])
            ->where('items.batch_number', '=', '0')
            ->count();

        $item_sum['unassignedProductCount'] = $unassignedProductCount;
        $item_sum['unassignedOrderCount'] = $unassignedOrderCount;


        return [
            'items' => $items,
            'total' => $item_sum,
        ];
    }

    public function indexGraphic(Request $request)
    {
        $items = [];
        $item_sum = [];

        if (count($request->all()) > 1) {
            //mirror in csv export
            $items = Item::with('order.customer', 'store', 'batch.route', 'shipInfo', 'batch', 'wap_item.bin')
                ->where('is_deleted', '0')
                ->searchStore($request->get('store'))
                ->searchStatus($request->get('status'))
                ->searchSection($request->get('section'))
                ->search($request->get('search_for_first'), $request->get('search_in_first'))
                ->search($request->get('search_for_second'), $request->get('search_in_second'))
                ->searchTrackingDate($request->get('tracking_date'))
                ->searchOrderDate($request->get('start_date'), $request->get('end_date'))
                ->searchBatchDate($request->get('scan_start_date'), $request->get('scan_end_date'))
                ->unBatched($request->get('unbatched'))
                ->latest()
                ->paginate($request->get('perPage', 48));

            $item_sum = Item::where('is_deleted', '0')
                ->searchStore($request->get('store'))
                ->searchStatus($request->get('status'))
                ->searchSection($request->get('section'))
                ->search($request->get('search_for_first'), $request->get('search_in_first'))
                ->search($request->get('search_for_second'), $request->get('search_in_second'))
                ->searchTrackingDate($request->get('tracking_date'))
                ->searchOrderDate($request->get('start_date'), $request->get('end_date'))
                ->searchBatchDate($request->get('scan_start_date'), $request->get('scan_end_date'))
                ->unBatched($request->get('unbatched'))
                ->selectRaw('sum(items.item_quantity) as sum, count(items.id) as count')
                ->first();

            set_time_limit(0);
        }

        $unassignedProductCount = Option::where('batch_route_id', Helper::getDefaultRouteId())->count();

        $unassignedOrderCount = Item::join('parameter_options', 'items.child_sku', '=', 'parameter_options.child_sku')
            ->where('parameter_options.batch_route_id', Helper::getDefaultRouteId())
            ->where('items.is_deleted', '0')
            ->whereIn('items.item_status', [1, 4])
            ->where('items.batch_number', '=', '0')
            ->count();


        $item_sum['unassignedProductCount'] = $unassignedProductCount;
        $item_sum['unassignedOrderCount'] = $unassignedOrderCount;

        return [
            'items' => $items,
            'total' => $item_sum,
        ];
    }

    public function unbatchableItems(Request $request)
    {
        $items = Batching::failures();

        $order_statuses = [];

        foreach (Order::statuses() as $key => $value) {
            $order_statuses[] = [
                'label' => $value,
                'value' => $key,
            ];
        }

        return response()->json([
            'items' => $items,
            'order_statuses' => $order_statuses,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Item $item)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Item $item)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Item $item)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Item $item)
    {
        //
    }

    public function searchOption()
    {
        $search = [];
        foreach (Order::$search_in ?? [] as $key => $value) {
            $search[] = [
                'label' => $value,
                'value' => $key,
            ];
        };
        return $search;
    }

    public function statusOption()
    {
        $statuse = [];
        foreach (Item::getStatusList() ?? [] as $key => $value) {
            $statuse[] = [
                'label' => $value,
                'value' => $key,
            ];
        };
        return $statuse;
    }

    public function storeOption()
    {
        $stores = [];
        foreach (Store::list('%', '%', 'none') ?? [] as $key => $value) {
            $stores[] = [
                'label' => $value,
                'value' => $key,
            ];
        };
        return $stores;
    }
}
