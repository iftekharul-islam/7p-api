<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\BatchRoute;
use App\Models\Inventory;
use App\Models\Station;
use Illuminate\Http\Request;

class PrintController extends Controller
{
    public function showBatchPrint(Request $request)
    {
        if ($request->has('batch_numbers')) {
            $batch_numbers = $request->get('batch_numbers');
        } else if ($request->has('batch_number')) {
            $batch_numbers = $request->get('batch_number');
        } else {
            if (!$request->has('printed')) {
                return response()->json([
                    'status' => 203,
                    'message' => 'Printed parameter not set',
                ], 203);
            }
            if ($request->has('production_station')) {

                $batch_numbers = Batch::join('items', 'batches.batch_number', '=', 'items.batch_number')
                    // ->join('sections', function($join)
                    // 				{
                    // 						$join->on('batches.section_id', '=', 'sections.id')
                    // 									->where('sections.inventory', '!=', '1')
                    // 									->orWhere(DB::raw('batches.inventory'), '=', '2');
                    // 				})
                    ->leftjoin('inventory_unit', 'items.child_sku', '=', 'inventory_unit.child_sku')
                    ->searchStatus('active')
                    ->searchStation($request->get('station'))
                    ->searchSection($request->get('section'))
                    ->searchStore([$request->get('store')])
                    ->searchType($request->get('type'))
                    ->searchProductionStation($request->get('production_station'))
                    ->searchPrinted($request->get('printed'))
                    //->where('batches.min_order_date', '<', '2017-12-07 00:00:00')
                    ->groupBy('batches.batch_number')
                    //->orderBy(DB::raw("DAY(creation_date)"))
                    //->orderBy('inventory_unit.stock_no_unique')
                    ->orderBy('items.child_sku')
                    ->get()
                    ->pluck('batch_number');
            } else if ($request->has('graphic_dir')) {

                $batch_numbers = Batch::searchStatus('active')
                    // ->join('sections', function($join)
                    // 				{
                    // 						$join->on('batches.section_id', '=', 'sections.id')
                    // 									->where('sections.inventory', '!=', '1')
                    // 									->orWhere(DB::raw('batches.inventory'), '=', '2');
                    // 				})
                    ->searchGraphicDir($request->get('graphic_dir'))
                    ->searchStore($request->get('store'))
                    ->searchType($request->get('type'))
                    ->searchPrinted($request->get('printed'))
                    ->groupBy('batches.batch_number')
                    ->orderBy('batches.batch_number', 'ASC')
                    ->get()
                    ->pluck('batch_number');
            } else {
                return response()->json([
                    'status' => 203,
                    'message' => 'Production Station or Graphic Dir parameter not set',
                ], 203);
            }
        }

        if (empty($batch_numbers) || count($batch_numbers) == 0) {
            return response()->json([
                'status' => 203,
                'message' => 'No Batches Found',
            ], 203);
        }

        $modules = [];
        $date  = date("Y-m-d  H:i:s");

        foreach ($batch_numbers as $batch_number) {
            set_time_limit(0);
            $module = $this->batch_printing_module($batch_number, $date);
            $modules[] = $module->render();
            Batch::note($batch_number, '', '1', 'Summary Printed');
        }

        if ($request->has('production_station')) {
            $title_station = Station::where('id', $request->get('production_station'))->first();
            $title = $title_station->station_name;
        } elseif ($request->has('station')) {
            $title_station = Station::where('id', $request->get('station'))->first();
            $title = $title_station->station_name;
        } else {
            $title = '';
        }

        return response()->json([
            'title' => $title,
            'modules' => $modules,
        ], 200);
    }
    private function batch_printing_module($batch_number, $date = NULL)
    {
        if ($date == NULL) {
            $date  = date("Y-m-d  H:i:s");
        }

        $batch = Batch::with('items.order.customer', 'items.store', 'route.stations_list', 'station', 'items.product', 'items.inventoryunit')
            ->where('batch_number', '=', $batch_number)
            ->latest('creation_date')
            ->first();

        if (!$batch) {
            return view('errors.404');
        }

        $stock = array();

        foreach ($batch->items as $item) {

            if ($item->inventoryunit) {

                foreach ($item->inventoryunit as $unit) {
                    $stockno = $unit->stock_no_unique;

                    if (array_key_exists($stockno, $stock)) {
                        $stock[$stockno] += $item->item_quantity * $unit->unit_qty;
                    } else {
                        $stock[$stockno] = $item->item_quantity * $unit->unit_qty;
                    }
                }
            }
        }

        if (!empty($stock)) {
            $inventory = Inventory::whereIn('stock_no_unique', array_keys($stock))
                ->groupBy('stock_no_unique')
                ->get();
        } else {
            $inventory = NULL;
        }

        $next_station_name = '';
        $station_list = $batch->route->stations_list;
        $grab_next = false;

        foreach ($station_list as $station) {

            if ($grab_next) {
                $grab_next = false;
                $next_station_name = $station->station_name;
                break;
            }
            if ($station->station_name == $batch->station->station_name) {
                $grab_next = true;
            }
        }

        // if ( !empty( $current_station_by_url ) ) {
        // 	$current_station_name = $current_station_by_url;
        // }

        // if ( $current_station_name == '' ) {
        // 	$current_station_name = Helper::getSupervisorStationName();
        // }

        #$bar_code = Helper::getHtmlBarcode($batch_number);
        //$statuses = Helper::getBatchStatusList();
        $route = BatchRoute::with('stations', 'template')
            ->find($batch->batch_route_id);
        $stations = BatchRoute::routeThroughStations($batch->batch_route_id, $batch->station->station_name);

        $count = 1;

        $batch->summary_date = $date;
        $batch->summary_user_id = auth()->user()->id;
        $batch->summary_count = $batch->summary_count + 1;
        $batch->save();

        return view('print.print', compact(
            'batch',
            'inventory',
            'stock',
            // 'batch_status',
            'next_station_name',
            'batch_number',
            'route',
            'stations',
            'count'
        ));
    }
}
