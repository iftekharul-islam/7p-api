<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Item;
use App\Models\PurchaseProduct;
use Illuminate\Http\Request;

class BackorderController extends Controller
{
    public function index(Request $request)
    {
        if ($request->has('stock_no')) {
            if (!$this->arrivedByStockNo($request->get('stock_no'))) {
                return response()->json([
                    'message' => 'Error encountered while marking ' . $request->get('stock_no') . ' arrived.',
                    'status' => 203,
                ], 203);
            }
        }

        if ($request->has('item_code')) {
            if (!$this->arrivedByItemCode($request->get('item_code'))) {
                return response()->json([
                    'message' => 'Error encountered while marking ' . $request->get('item_code') . ' arrived.',
                    'status' => 203,
                ], 203);
            }
        }
        set_time_limit(0);

        $batched =  Item::leftjoin('inventory_unit', 'items.child_sku', '=', 'inventory_unit.child_sku')
            ->leftjoin('inventories', 'inventory_unit.stock_no_unique', '=', 'inventories.stock_no_unique')
            ->where('items.item_status', 4)
            ->where('items.batch_number', '!=', '0')
            ->where('items.is_deleted', '0')
            ->orderBy('inventories.stock_no_unique')
            ->get();

        $unbatched = Item::leftjoin('inventory_unit', 'items.child_sku', '=', 'inventory_unit.child_sku')
            ->leftjoin('inventories', 'inventory_unit.stock_no_unique', '=', 'inventories.stock_no_unique')
            ->where('items.item_status', 4)
            ->where('items.batch_number', '0')
            ->where('items.is_deleted', '0')
            ->orderBy('inventories.stock_no_unique')
            ->get();

        $batched_stock_nos = array_unique($batched->pluck('stock_no_unique')->all());
        $unbatched_stock_nos = array_unique($unbatched->pluck('stock_no_unique')->all());

        $stock_nos = array_unique(array_merge($batched_stock_nos, $unbatched_stock_nos));

        $purchases = PurchaseProduct::where('balance_quantity', '>', 0)
            ->whereIn('stock_no', $batched_stock_nos)
            ->where('is_deleted', '0')
            ->get();

        return response()->json([
            'batched' => $batched,
            'unbatched' => $unbatched,
            'stock_nos' => $stock_nos,
            'purchases' => $purchases,
        ], 200);
    }

    public function show(Request $request)
    {
        $batch_views = null;
        if ($request->get('search_in') == 'batch_number') {

            if ($request->has('scan_batch')) {
                $batch_list = explode(',', rtrim(trim($request->get('scan_batch')), ','));
            } else {
                $batch_list = explode(',', rtrim(trim($request->get('search_for')), ','));
            }

            $batch_array = array();

            // if ($request->has('scan_batch')) {
            //    $batch_list = explode(',', rtrim(trim($request->get('scan_batch')), ','));


            foreach ($batch_list as $batch) {
                if ($batch == NULL) {
                    continue;
                } else if (substr(trim($batch), 0, 4) == 'BATC') {
                    $batch_array[] = substr(trim($batch), 4);
                } else {
                    $batch_array[] = trim($batch);
                }
            }

            $batch_views = Batch::with('items', 'station')
                ->whereIn('batch_number', $batch_array)
                ->get();
            return response()->json(['data' => $batch_views, 'tab' => 'batch_number'], 200);
        } else if ($request->get('search_in') == 'stock_no_unique') {

            $search_for = $request->get('search_for');

            $items = Item::with('order', 'batch')
                ->leftjoin('inventory_unit', 'items.child_sku', '=', 'inventory_unit.child_sku')
                ->leftjoin('inventories', 'inventory_unit.stock_no_unique', '=', 'inventories.stock_no_unique')
                ->leftjoin('batches', 'items.batch_number', '=', 'batches.batch_number')
                ->leftjoin('stations', 'batches.station_id', '=', 'stations.id')
                ->where('inventory_unit.stock_no_unique', $search_for)
                ->whereIn('item_status', [1, 4])
                ->where('items.is_deleted', '0')
                ->orderby('item_status')
                ->selectRaw('items.item_status, 
                                            inventories.stock_no_unique, inventories.warehouse, inventories.stock_name_discription,
                                            batches.station_id, stations.station_description, stations.type,
                                            sum(items.item_quantity * inventory_unit.unit_qty) as qty')
                ->groupBy('item_status', 'stock_no_unique', 'warehouse', 'stock_name_discription', 'station_id', 'station_description', 'type')
                ->get();

            return response()->json(['data' => $items, 'tab' => 'stock_no_unique'], 200);
        } else {
            return response()->json(['data' => [], 'tab' => 'batch_number'], 200);
        }
    }
}
