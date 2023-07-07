<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Manufacture;
use App\Models\Order;
use App\Models\Rejection;
use App\Models\Section;
use App\Models\Store;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function section(Request $request)
    {

        $request->has('store_ids') ? $store_ids = $request->get('store_ids') : $store_ids = null;
        $request->has('company') ? $company_id = $request->get('company') : $company_id = null;
        $request->has('max_date') ? $max_date = $request->get('max_date') . ' 23:59:59' : $max_date = date("Y-m-d") . ' 23:59:59';
        $request->has('batch_type') ? $batch_type = $request->get('batch_type') : $batch_type = '%';
        $manufacture_id = $request->manufacture_id ?? null;

        $dates = array();
        $date[] = date("Y-m-d");
        $date[] = date("Y-m-d", strtotime('-3 days'));
        $date[] = date("Y-m-d", strtotime('-4 days'));
        $date[] = date("Y-m-d", strtotime('-7 days'));
        $date[] = date("Y-m-d", strtotime('-8 days'));

        $items = Item::join('batches', 'batches.batch_number', '=', 'items.batch_number')
            ->join('orders', 'items.order_5p', '=', 'orders.id')
            ->join('stations', 'batches.station_id', '=', 'stations.id')
            ->join('sections', 'stations.section', '=', 'sections.id')
            ->searchStore($store_ids)
            ->withManufacture($manufacture_id)
            ->where('batches.status', 2)
            ->where('batches.batch_number', 'LIKE', $batch_type)
            ->where('batches.min_order_date', '<', $max_date)
            ->where('items.item_status', 1)
            ->where('stations.type', '!=', 'Q')
            //->where('orders.order_status', 4)
            ->groupBy('stations.station_name', 'stations.type', 'batches.station_id', 'stations.section', 'stations.station_description', 'items.manufacture_id')
            //->groupBy ( 'orders.order_status' )
            ->orderBy('sections.section_name')
            ->orderBy('stations.type', 'ASC')
            ->orderBy('stations.station_description', 'ASC')
            ->selectRaw("
										    items.manufacture_id,
											SUM(items.item_quantity) as items_count, 
											count(items.id) as lines_count, 
											stations.station_name,
											stations.station_description,
											stations.type,
											batches.station_id,
											stations.section as section_id,
											sections.section_name,
											DATE(MIN(orders.order_date)) as earliest_order_date,
											DATE(MIN(batches.change_date)) as earliest_scan_date,
											COUNT(IF(orders.order_date >= '{$date[1]} 00:00:00', items.id, NULL)) as order_1,
											COUNT(IF(orders.order_date >= '{$date[3]} 00:00:00' AND orders.order_date <= '{$date[2]} 23:59:59', items.id, NULL)) as order_2,
											COUNT(IF(orders.order_date <= '{$date[4]} 23:59:59', items.id, NULL)) as order_3,
											COUNT(IF(batches.change_date >= '{$date[1]} 00:00:00', items.id, NULL)) as scan_1,
											COUNT(IF(batches.change_date >= '{$date[3]} 00:00:00' AND batches.change_date <= '{$date[2]} 23:59:59', items.id, NULL)) as scan_2,
											COUNT(IF(batches.change_date <= '{$date[4]} 23:59:59', items.id, NULL)) as scan_3
											")
            ->get();



        $qc = Item::join('batches', 'batches.batch_number', '=', 'items.batch_number')
            ->join('orders', 'items.order_5p', '=', 'orders.id')
            ->join('stations', 'batches.station_id', '=', 'stations.id')
            ->searchStore($store_ids)
            ->withManufacture($manufacture_id)
            ->where('batches.status', 2)
            ->where('batches.batch_number', 'LIKE', $batch_type)
            ->where('batches.min_order_date', '<', $max_date)
            ->where('items.item_status', 1)
            ->where('stations.type', 'Q')
            //->where('orders.order_status', 4)
            ->groupBy('stations.station_name', 'stations.type', 'batches.station_id', 'stations.section', 'stations.station_description', 'items.manufacture_id')
            //->groupBy ( 'orders.order_status' )
            ->orderBy('stations.station_description', 'ASC')
            ->selectRaw("
											SUM(items.item_quantity) as items_count, 
											count(items.id) as lines_count, 
											stations.station_name,
											stations.station_description,
											stations.type,
											batches.station_id,
											DATE(MIN(orders.order_date)) as earliest_order_date,
											DATE(MIN(batches.change_date)) as earliest_scan_date,
											COUNT(IF(orders.order_date >= '{$date[1]} 00:00:00', items.id, NULL)) as order_1,
											COUNT(IF(orders.order_date >= '{$date[3]} 00:00:00' AND orders.order_date <= '{$date[2]} 23:59:59', items.id, NULL)) as order_2,
											COUNT(IF(orders.order_date <= '{$date[4]} 23:59:59', items.id, NULL)) as order_3,
											COUNT(IF(batches.change_date >= '{$date[1]} 00:00:00', items.id, NULL)) as scan_1,
											COUNT(IF(batches.change_date >= '{$date[3]} 00:00:00' AND batches.change_date <= '{$date[2]} 23:59:59', items.id, NULL)) as scan_2,
											COUNT(IF(batches.change_date <= '{$date[4]} 23:59:59', items.id, NULL)) as scan_3
											")
            ->get();

        $backorders = Item::join('orders', 'items.order_5p', '=', 'orders.id')
            ->join('batches', 'items.batch_number', '=', 'batches.batch_number')
            ->join('sections', 'batches.section_id', '=', 'sections.id')
            ->searchStore($store_ids)
            ->withManufacture($manufacture_id)
            ->where('batches.min_order_date', '<', $max_date)
            ->where('batches.batch_number', 'LIKE', $batch_type)
            ->where('items.item_status', 4)
            ->where('items.is_deleted', '0')
            ->groupBy('batches.section_id')
            ->selectRaw("
											SUM(items.item_quantity) as items_count, 
											count(items.id) as lines_count, 
											batches.section_id,
											sections.section_name,
											DATE(MIN(orders.order_date)) as earliest_order_date,
											DATE(MIN(batches.change_date)) as earliest_scan_date,
											COUNT(IF(orders.order_date >= '{$date[1]} 00:00:00', items.id, NULL)) as order_1,
											COUNT(IF(orders.order_date >= '{$date[3]} 00:00:00' AND orders.order_date <= '{$date[2]} 23:59:59', items.id, NULL)) as order_2,
											COUNT(IF(orders.order_date <= '{$date[4]} 23:59:59', items.id, NULL)) as order_3,
											COUNT(IF(batches.change_date >= '{$date[1]} 00:00:00', items.id, NULL)) as scan_1,
											COUNT(IF(batches.change_date >= '{$date[3]} 00:00:00' AND batches.change_date <= '{$date[2]} 23:59:59', items.id, NULL)) as scan_2,
											COUNT(IF(batches.change_date <= '{$date[4]} 23:59:59', items.id, NULL)) as scan_3
											")
            ->get();
        $rejects = Item::join('rejections', 'items.id', '=', 'rejections.item_id')
            ->join('orders', 'items.order_5p', '=', 'orders.id')
            ->join('batches', 'items.batch_number', '=', 'batches.batch_number')
            ->join('sections', 'batches.section_id', '=', 'sections.id')
            ->searchStore($store_ids)
            ->withManufacture($manufacture_id)
            ->where('batches.min_order_date', '<', $max_date)
            ->where('batches.batch_number', 'LIKE', $batch_type)
            ->where('items.is_deleted', '0')
            ->where('rejections.complete', '0')
            ->whereNotIn('rejections.graphic_status', [4, 5]) // exclude CS rejects
            ->searchStatus('rejected')
            ->groupBy('batches.section_id', 'rejections.graphic_status')
            ->selectRaw("
										 SUM(items.item_quantity) as items_count, 
										 count(items.id) as lines_count, 
										 rejections.graphic_status,
										 batches.section_id,
										 sections.section_name,
										 DATE(MIN(orders.order_date)) as earliest_order_date,
										 COUNT(IF(orders.order_date >= '{$date[1]} 00:00:00', items.id, NULL)) as order_1,
										 COUNT(IF(orders.order_date >= '{$date[3]} 00:00:00' AND orders.order_date <= '{$date[2]} 23:59:59', items.id, NULL)) as order_2,
										 COUNT(IF(orders.order_date <= '{$date[4]} 23:59:59', items.id, NULL)) as order_3,
										 COUNT(IF(batches.change_date >= '{$date[1]} 00:00:00', items.id, NULL)) as scan_1,
										 COUNT(IF(batches.change_date >= '{$date[3]} 00:00:00' AND batches.change_date <= '{$date[2]} 23:59:59', items.id, NULL)) as scan_2,
										 COUNT(IF(batches.change_date <= '{$date[4]} 23:59:59', items.id, NULL)) as scan_3
										 ")
            ->get(); //dd($rejects);


        $WAP = Item::join('orders', 'items.order_5p', '=', 'orders.id')
            ->where('items.item_status', 9)
            ->where('items.is_deleted', '0')
            ->where('orders.order_status', 4) // exclude address holds
            ->where('items.batch_number', 'LIKE', $batch_type)
            ->searchStore($store_ids)
            ->withManufacture($manufacture_id)
            ->where('orders.order_date', '<', $max_date)
            ->selectRaw("
											SUM(items.item_quantity) as items_count, 
											count(items.id) as lines_count, 
											DATE(MIN(orders.order_date)) as earliest_order_date,
											COUNT(IF(orders.order_date >= '{$date[1]} 00:00:00', items.id, NULL)) as order_1,
											COUNT(IF(orders.order_date >= '{$date[3]} 00:00:00' AND orders.order_date <= '{$date[2]} 23:59:59', items.id, NULL)) as order_2,
											COUNT(IF(orders.order_date <= '{$date[4]} 23:59:59', items.id, NULL)) as order_3
											")
            ->first();


        $CS_rejects = Item::join('rejections', 'items.id', '=', 'rejections.item_id')
            ->join('orders', 'items.order_5p', '=', 'orders.id')
            ->searchStore($store_ids)
            ->withManufacture($manufacture_id)
            ->where('orders.order_date', '<', $max_date)
            ->where('items.is_deleted', '0')
            ->where('items.batch_number', 'LIKE', $batch_type)
            ->where('rejections.complete', '0')
            ->whereIn('rejections.graphic_status', [4, 5])
            ->searchStatus('rejected')
            ->groupBy('rejections.graphic_status')
            ->selectRaw("
										 SUM(items.item_quantity) as items_count, 
										 count(items.id) as lines_count, 
										 rejections.graphic_status,
										 DATE(MIN(orders.order_date)) as earliest_order_date,
										 COUNT(IF(orders.order_date >= '{$date[1]} 00:00:00', items.id, NULL)) as order_1,
										 COUNT(IF(orders.order_date >= '{$date[3]} 00:00:00' AND orders.order_date <= '{$date[2]} 23:59:59', items.id, NULL)) as order_2,
										 COUNT(IF(orders.order_date <= '{$date[4]} 23:59:59', items.id, NULL)) as order_3")
            ->get();

        $CS = Item::join('orders', 'items.order_5p', '=', 'orders.id')
            ->searchStore($store_ids)
            ->withManufacture($manufacture_id)
            ->where('orders.order_date', '<', $max_date)
            ->where('orders.order_status', '>', 9)
            ->where('orders.is_deleted', '0')
            ->where('items.batch_number', 'LIKE', $batch_type)
            ->groupBy('orders.order_status')
            ->selectRaw("
											orders.order_status,
											count(DISTINCT orders.id) as orders_count,
											SUM(items.item_quantity) as items_count, 
											count(items.id) as lines_count, 
											DATE(MIN(orders.order_date)) as earliest_order_date,
											COUNT(IF(orders.order_date >= '{$date[1]} 00:00:00', items.id, NULL)) as order_1,
											COUNT(IF(orders.order_date >= '{$date[3]} 00:00:00' AND orders.order_date <= '{$date[2]} 23:59:59', items.id, NULL)) as order_2,
											COUNT(IF(orders.order_date <= '{$date[4]} 23:59:59', items.id, NULL)) as order_3
											")
            ->get();


        $sections_result = Section::get();
        $sections = array();
        $section_totals = array();

        $sections[0] = '0';
        $section_totals[0] = array(
            'lines' => 0, 'qty' => 0, 'order_1' => 0,
            'order_2' => 0, 'order_3' => 0, 'scan_1' => 0, 'scan_2' => 0, 'scan_3' => 0
        );

        foreach ($sections_result as $section) {
            $sections[$section->id] = $section->section_name;
            $section_totals[$section->section_name] = array(
                'lines' => 0, 'qty' => 0, 'order_1' => 0,
                'order_2' => 0, 'order_3' => 0, 'scan_1' => 0, 'scan_2' => 0, 'scan_3' => 0
            );
        }

        $unbatched = Item::join('orders', 'items.order_5p', '=', 'orders.id')
            ->searchStore($store_ids)
            ->withManufacture($manufacture_id)
            ->where('orders.order_date', '<', $max_date)
            ->whereNull('items.tracking_number')
            ->where('items.batch_number', '=', '0')
            ->where('items.batch_number', 'LIKE', $batch_type)
            ->where('items.item_status', '=', '1')
            ->whereIn('orders.order_status', [4, 11, 12, 7, 9])
            ->where('orders.is_deleted', '0')
            ->where('items.is_deleted', '0')
            ->groupBy('items.id')
            ->selectRaw("
												items.id, orders.order_date, items.item_quantity,
												SUM(items.item_quantity) as items_count, 
												count(items.id) as lines_count,
												DATE(MIN(orders.order_date)) as earliest_order_date,
												COUNT(IF(orders.order_date >= '{$date[1]} 00:00:00', items.id, NULL)) as order_1,
												COUNT(IF(orders.order_date >= '{$date[3]} 00:00:00' AND orders.order_date <= '{$date[2]} 23:59:59', items.id, NULL)) as order_2,
												COUNT(IF(orders.order_date <= '{$date[4]} 23:59:59', items.id, NULL)) as order_3
												")
            ->first();


        $today = date("Y-m-d");

        $shipped_today = Item::join('batches', 'items.batch_number', '=', 'batches.batch_number')
            ->join('sections', 'batches.section_id', '=', 'sections.id')
            ->searchStore($store_ids)
            ->withManufacture($manufacture_id)
            ->where('batches.min_order_date', '<', $max_date)
            ->where('batches.batch_number', 'LIKE', $batch_type)
            ->searchTrackingDate($today)
            ->selectRaw('sections.section_name, AVG(DATEDIFF(\'' . $today . '\', items.created_at)) AS avgdays, count(items.id) as count')
            ->groupBy('sections.section_name')
            ->get();
        $rejected_today = Rejection::where('created_at', '>', date("Y-m-d 00:00:00"))->count();

        $graphic_statuses = [];

        foreach (Rejection::graphicStatus() ?? [] as $key => $value) {
            $graphic_statuses[] = [
                'value' => $key,
                'label' => $value,
            ];
        }

        $order_statuses = [];

        foreach (Order::statuses() as $key => $value) {
            $order_statuses[] = [
                'value' => $key,
                'label' => $value,
            ];
        }

        $section = 'start';

        $now = date("F j, Y, g:i a");

        $total = $items->sum('items_count') + $backorders->sum('items_count') +  $rejects->sum('items_count') +
            $CS_rejects->sum('items_count') + $CS->sum('items_count') + $qc->sum('items_count') +
            $unbatched->items_count + $WAP->items_count;


        foreach ($date as $key => $value) {
            if ($value > substr($max_date, 0, 10)) {
                $date[$key] = substr($max_date, 0, 10);
            }
        }

        if ($store_ids == null) {
            $store_link = null;
        } else {
            $store_link = implode(',', $store_ids);
        }

        return response()->json([
            'items' => $items,
            'backorders' => $backorders,
            'rejects' => $rejects,
            'CS_rejects' => $CS_rejects,
            'CS' => $CS,
            'qc' => $qc,
            'unbatched' => $unbatched,
            'WAP' => $WAP,
            'shipped_today' => $shipped_today,
            'rejected_today' => $rejected_today,
            'graphic_statuses' => $graphic_statuses,
            'order_statuses' => $order_statuses,
            'section' => $section,
            'now' => $now,
            'total' => $total,
            'date' => $date,
            'store_link' => $store_link,
            'sections' => $sections,
            'section_totals' => $section_totals,
        ]);
    }

    public function shipDate(Request $request)
    {
        $request->has('start_date') ? $start_date = $request->get('start_date') : $start_date = date("Y-m-d");
        $request->has('end_date') ? $end_date = $request->get('end_date') : $end_date = date("Y-m-d");
        $store_ids = $request->get('store_ids');

        $shipped_today = Item::leftjoin('batches', 'items.batch_number', '=', 'batches.batch_number')
            ->join('shipping', function ($join) use ($start_date, $end_date) {
                $join->on('items.tracking_number', '=', 'shipping.tracking_number')
                    ->where('shipping.transaction_datetime', '>=', $start_date . ' 00:00:00')
                    ->where('shipping.transaction_datetime', '<=', $end_date . ' 23:59:59');
            })
            ->searchStore($store_ids)
            ->groupBy('section_id', 'items.store_id', 'batches.id')
            ->selectRaw('IF(batches.id IS NOT NULL, batches.section_id, 0) as section_num,
																items.store_id, batches.section_id, 
																SUM(items.item_quantity) as item_quantity,
																COUNT(shipping.id) as ship_count,
																SUM(DATEDIFF(shipping.transaction_datetime, items.created_at)) as diff,
																AVG(IF(shipping.id IS NOT NULL, DATEDIFF(shipping.transaction_datetime, items.created_at), NULL)) AS avgdays,
																MAX(IF(shipping.id IS NOT NULL, DATEDIFF(shipping.transaction_datetime, items.created_at), NULL)) AS maxdays')
            ->orderBy('section_id')
            ->get();


        $sections = Section::get()->transform(function ($item) {
            return [
                'value' => $item['id'],
                'label' => $item['section_name'],
            ];
        });
        $sections->prepend([
            'value' => '0',
            'label' => 'Unbatched',
        ]);

        return response()->json([
            'shipped_today' => $shipped_today,
            'sections' => $sections,
        ]);
    }
    public function orderItems(Request $request)
    {
        $request->has('start_date') ? $start_date = $request->get('start_date') : $start_date = date("Y-m-d");
        $request->has('end_date') ? $end_date = $request->get('end_date') : $end_date = date("Y-m-d");
        $request->has('group') ? $group = $request->get('group') : $group = 'stock_no_unique';
        $request->has('limit') && $request->get('limit') != 0 ? $limit = $request->get('limit') : $limit = 25;

        $store_ids = $request->get('store_ids');

        if (is_array($store_ids)) {
            $store_str = implode(',', $store_ids);
        } else {
            $store_str = '';
        }

        set_time_limit(0);
        ini_set('memory_limit', '256M');

        $items = Item::join('orders', 'items.order_5p', '=', 'orders.id')
            ->leftjoin('shipping', 'items.tracking_number', '=', 'shipping.tracking_number')
            ->leftjoin('products', 'items.item_code', '=', 'products.product_model')
            ->where('items.is_deleted', '0')
            ->where('items.item_status', '!=', 6)
            ->selectRaw("items.item_code, products.product_name, products.product_thumb, products.id as product_id,
															SUM(IF(items.item_status = 2, items.item_quantity, 0)) as shipped,
															SUM(IF(items.item_status = 2 && shipping.id IS NOT NULL, 
																			DATEDIFF(shipping.transaction_datetime, orders.order_date), null)) as ship_days,
															MAX(IF(items.item_status = 2 && shipping.id IS NOT NULL, 
																			DATEDIFF(shipping.transaction_datetime, orders.order_date), null)) as maxdays,
															SUM(items.item_quantity) as item_qty
															")
            ->searchStore($store_ids)
            ->searchDate($start_date, $end_date)
            ->groupBy('items.item_code')
            ->orderBy('item_qty', 'DESC')
            ->limit($limit)
            ->get();

        $skus = $items->pluck('item_code');

        $rejects = Item::leftjoin('rejections', 'items.id', '=', 'rejections.item_id')
            ->where('items.is_deleted', '0')
            ->where('items.item_status', '!=', 6)
            ->whereIn('items.item_code', $skus)
            ->selectRaw("items.item_code,COUNT(DISTINCT rejections.id) as count")
            ->searchStore($store_ids)
            ->searchDate($start_date, $end_date)
            ->groupBy('items.item_code')
            ->get();


        return response()->json([
            'items' => $items,
            'rejects' => $rejects,
            'store_str' => $store_str,
        ]);
    }

    public function salesSummary(Request $request)
    {

        $request->has('start_date') ? $start_date = $request->get('start_date') : $start_date = date("Y-m-d");
        $request->has('end_date') ? $end_date = $request->get('end_date') : $end_date = date("Y-m-d");
        $request->has('store_ids') ? $store_ids = $request->get('store_ids') : $store_ids = null;

        $sales = Order::where('orders.is_deleted', '0')
            ->where('orders.order_status', '!=', 8)
            ->selectRaw("store_id, 
															SUM(orders.total) as order_total, 
															SUM(shipping_charge) as shipping_total, 
															COUNT(orders.id) as order_count")
            ->StoreId($request->get('store_ids'))
            ->withinDate($start_date, $end_date)
            ->groupBy('store_id')
            ->orderBy('order_total', 'DESC')
            ->get()->take(10)->map(function ($sale) use ($request, $start_date, $end_date) {
                $sale['item'] = Order::join('items', 'orders.id', '=', 'items.order_5p')
                    ->leftjoin('shipping', 'items.tracking_number', '=', 'shipping.tracking_number')
                    ->leftjoin('batches', 'items.batch_number', '=', 'batches.batch_number')
                    ->leftjoin('sections', 'batches.section_id', '=', 'sections.id')
                    ->where('orders.store_id', $sale['store_id'])
                    ->where('orders.is_deleted', '0')
                    ->where('items.is_deleted', '0')
                    ->where('orders.order_status', '!=', 8)
                    ->where('items.item_status', '!=', 6)
                    ->selectRaw("orders.store_id, batches.section_id,
                                                                (CASE WHEN (items.batch_number = '0') THEN 'Unbatched'
                                                                            WHEN (batches.section_id IS NULL or sections.id IS NULL) THEN 'Invalid Section'
                                                                            ELSE sections.section_name
                                                                    END) as header,
                                                                SUM(IF(items.item_status = 2, items.item_quantity, 0)) as shipped,
                                                                SUM(IF(items.item_status = 2 && shipping.id IS NOT NULL, 
                                                                                DATEDIFF(shipping.transaction_datetime, orders.order_date), null)) as ship_days,
                                                                SUM(items.item_quantity) as item_qty")
                    ->StoreId($request->get('store_ids'))
                    ->withinDate($start_date, $end_date)
                    ->groupBy('orders.store_id', 'batches.section_id', 'items.batch_number')
                    ->orderBy('item_qty', 'DESC')
                    ->get();
                return $sale;
            });

        $total_amount = $sales->sum('order_total');

        $sections = Section::get()->transform(function ($item) {
            return [
                'value' => $item['id'],
                'label' => $item['section_name'],
            ];
        });

        return response()->json([
            'sales' => $sales,
            'sections' => $sections,
            'total_amount' => $total_amount,
        ]);
    }

    public function mustShipReport(Request $request)
    {
        $store_id = $request->get('store_id');

        $orders = Order::with('items.batch', 'items.batch.station', 'store', 'customer')
            ->where('is_deleted', '0')
            ->whereNotIn('order_status', [6, 8, 10])
            ->whereNotNull('ship_date')
            ->orderBy('ship_date', 'ASC')
            ->storeId($store_id)
            ->get();

        $statuses = Order::statuses(0);

        return response()->json($orders, 200);
    }

    public function manufactureOption()
    {
        $data = Manufacture::get()->transform(function ($item) {
            return [
                'value' => $item['id'],
                'label' => $item['name'],
            ];
        });
        $data->prepend([
            'value' => '',
            'label' => 'Select Manufacture',
        ]);
        return $data;
    }
    public function storeOption()
    {
        foreach (Store::list('%', '%', 'none') as $key => $value) {
            $data[] = [
                'value' => $key,
                'label' => $value,
            ];
        }
        return $data;
    }
    public function companyOption()
    {
        foreach (Store::$companies as $key => $value) {
            $data[] = [
                'value' => $key,
                'label' => $value,
            ];
        }
        return $data;
    }
}
