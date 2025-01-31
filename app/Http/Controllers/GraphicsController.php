<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\BatchRoute;
use App\Models\Design;
use App\Models\Item;
use App\Models\Printer;
use App\Models\Rejection;
use App\Models\Section;
use App\Models\Station;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Ship\ApiClient;
use Ship\FileHelper;
use Ship\ImageHelper;
use Ship\Sure3d;
use Ship\Wasatch;

class GraphicsController extends Controller
{
    protected $domain = 'https://7papi.monogramonline.com';
    protected $main_dir = '/media/RDrive/MAIN/';
    protected $sort_root = '/media/RDrive/';
    protected $old_sort_root = '/media/RDrive/';

    protected $csv_dir = '/media/RDrive/5p_batch_csv_export/';
    protected $error_dir = '/media/RDrive/5p_batch_csv_export/Jobs_Error/';
    protected $finished_dir = '/media/RDrive/5p_batch_csv_export/Jobs_Finished/';
    public static $manual_dir = '/media/RDrive/5p_batch_csv_export/MANUAL/';

    // protected $sub_dir = '/media/RDrive/sublimation/'; 
    public static $archive = '/media/RDrive/archive/';
    protected $old_archive = '/media/RDrive/archive';



    protected $printers = [
        'SOFT-1' => 'SOFT-1',
        'SOFT-2' => 'SOFT-2',
        'SOFT-3' => 'SOFT-3',
        'SOFT-4' => 'SOFT-4',
        'SOFT-5' => 'SOFT-5',
        'SOFT-6' => 'SOFT-6',
        'SOFT-7' => 'SOFT-7',
        'SOFT-8' => 'SOFT-8',
        'HARD-1' => 'HARD-1',
        'HARD-2' => 'HARD-2',
        'HARD-3' => 'HARD-3'
    ];
    public function index(Request $request)
    {
        //TODO - uncomment this
        // if (!file_exists($this->csv_dir)) {
        //     return  response()->json([
        //         'status' => 203,
        //         'message' => 'Cannot find csv directory on M: drive'
        //     ], 203);
        // }

        // if (!file_exists($this->error_dir)) {
        //     return  response()->json([
        //         'status' => 203,
        //         'message' => 'Cannot find error directory on M: drive'
        //     ], 203);
        // }

        ini_set('memory_limit', '256M');

        $tab = $request->has('tab') ? $request->tab : 'summary';

        if ($tab == 'summary' || true) {
            $date[] = date("Y-m-d");
            $date[] = date("Y-m-d", strtotime('-3 days'));
            $date[] = date("Y-m-d", strtotime('-4 days'));
            $date[] = date("Y-m-d", strtotime('-7 days'));
            $date[] = date("Y-m-d", strtotime('-8 days'));

            $items = Item::join('batches', 'batches.batch_number', '=', 'items.batch_number')
                ->join('orders', 'items.order_5p', '=', 'orders.id')
                ->join('stations', 'batches.station_id', '=', 'stations.id')
                ->join('sections', 'stations.section', '=', 'sections.id')
                // ->where('batches.status', 2) //TODO - uncomment this
                ->where('items.item_status', 1)
                ->where('stations.type', 'G')
                //->where('orders.order_status', 4)
                ->groupBy('stations.station_name', 'stations.station_description', 'stations.type', 'batches.station_id', 'sections.section_name')
                //->groupBy ( 'orders.order_status' )
                ->orderBy('sections.section_name')
                ->orderBy('stations.station_description', 'ASC')
                ->selectRaw("
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

            $rejects = Item::join('rejections', 'items.id', '=', 'rejections.item_id')
                ->join('orders', 'items.order_5p', '=', 'orders.id')
                ->join('batches', 'items.batch_number', '=', 'batches.batch_number')
                ->join('sections', 'batches.section_id', '=', 'sections.id')
                ->where('items.is_deleted', '0')
                ->where('rejections.complete', '0')
                ->whereNotIn('rejections.graphic_status', [4, 5]) // exclude CS rejects
                ->searchStatus('rejected')
                ->groupBy('batches.section_id', 'rejections.graphic_status',)
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
                ->get();

            $unbatched = Item::join('orders', 'items.order_5p', '=', 'orders.id')
                ->whereNull('items.tracking_number')
                ->where('items.batch_number', '=', '0')
                ->where('items.item_status', '=', '1')
                ->whereIn('orders.order_status', [4, 11, 12, 7, 9])
                ->where('orders.is_deleted', '0')
                ->where('items.is_deleted', '0')
                ->groupBy('items.id', 'orders.order_date', 'items.item_quantity', 'items.batch_number')
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

            $items_count = $items->sum('items_count');
            $rejects_count = $rejects->sum('items_count');
            $unbatched_count = $unbatched ? $unbatched->items_count : 0;
            $total = $items_count + $rejects_count + $unbatched_count;
        } else {
            $items = $unbatched = $rejects = [];
            $total = 0;
        }

        $graphic_statuses = Rejection::graphicStatus();

        $section = 'start';

        $now = date("F j, Y, g:i a");

        $count = array();

        if ($tab == 'to_export') {
            $to_export = $this->toExport();
            $count['to_export'] = count($to_export);
        } else {
            $count['to_export'] = $this->toExport('count');
        }

        // $manual = $this->getManual();
        // $count['manual'] = count($manual);

        // if ($tab == 'exported') {
        //     $exported = $this->exported($manual->pluck('batch_number')->all());
        //     $count['exported'] = count($exported);
        // } else {
        //     $count['exported'] = $this->exported($manual->pluck('batch_number')->all(), 'count');
        // }

        if ($tab == 'error') {
            $error_list = $this->graphicErrors();
            $count['error'] = count($error_list);
        } else {
            $count['error'] = $this->graphicErrors('count');
        }
        $sections = Section::get()->pluck('section_name', 'id');

        return response()->json([
            'to_export' => $to_export ?? [],
            // 'exported' => $exported,
            'error_list' => $error_list ?? [],
            // 'manual' => $manual,
            'sections' => $sections,
            'count' => $count,
            'total' => $total,
            'date' => $date,
            'items' => $items,
            'rejects' => $rejects,
            'unbatched' => $unbatched,
            'now' => $now,
            'section' => $section,
            'graphic_statuses' => $graphic_statuses,
        ], 200);
    }

    private function toExport($action = 'get')
    {
        if ($action == 'get') {
            $batches = Batch::with('itemsCount', 'first_item')
                ->join('stations', 'batches.station_id', '=', 'stations.id')
                ->whereIn('batches.status', [2, 4])
                ->where('stations.type', 'G')
                ->whereNull('export_date')
                ->where('graphic_found', '0')
                ->orderBy('min_order_date')
                ->paginate(50);
        } else if ($action == 'count') {
            $batches = Batch::join('stations', 'batches.station_id', '=', 'stations.id')
                ->whereIn('batches.status', [2, 4])
                ->where('stations.type', 'G')
                ->whereNull('export_date')
                ->where('graphic_found', '0')
                ->count();
        }

        return $batches;
    }

    private function exported($manual, $action = 'get')
    {

        if ($action == 'get') {
            $this->findFiles('exports');

            $batches = Batch::with('itemsCount', 'first_item')
                ->join('stations', 'batches.station_id', '=', 'stations.id')
                ->whereIn('batches.status', [2, 4])
                ->where('stations.type', 'G')
                ->whereNotNull('export_date')
                ->where('graphic_found', '0')
                ->whereNotIn('batch_number', $manual)
                ->orderBy('min_order_date')
                ->get();
        } else if ($action == 'count') {
            $batches = Batch::join('stations', 'batches.station_id', '=', 'stations.id')
                ->whereIn('batches.status', [2, 4])
                ->where('stations.type', 'G')
                ->whereNotNull('export_date')
                ->where('graphic_found', '0')
                ->whereNotIn('batch_number', $manual)
                ->count();
        }

        return $batches;
    }

    private function graphicErrors($action = 'get')
    {

        if ($action == 'get') {

            $error_files = $this->findErrorFiles();

            $batch_numbers = Batch::join('stations', 'batches.station_id', '=', 'stations.id')
                ->whereIn('batches.status', [2, 4])
                ->where('stations.type', 'G')
                ->where('graphic_found', '>', 1)
                ->select('batch_number')
                ->get()
                ->pluck('batch_number')
                ->toArray();

            $batch_numbers = $this->removeSure3d($batch_numbers);

            $batches = Batch::with('items.parameter_option.design')
                ->whereIn('batch_number', $batch_numbers)
                ->orderBy('batches.min_order_date')
                ->get();

            $errors = array();

            foreach ($batches as $batch) {

                $error = array();

                $error['batch'] = $batch;

                $graphic_skus = array();

                if (count($batch->items) == 0) {
                    Log::error('graphicErrors: Batch with zero items ' . $batch->batch_number);
                }

                foreach ($batch->items as $item) {

                    $graphic = array();

                    if ($item->parameter_option && !in_array($item->parameter_option->graphic_sku, $graphic_skus)) {

                        $graphic_skus[] = $item->parameter_option->graphic_sku;

                        $graphic['child_sku'] = $item->child_sku;

                        $graphic['sku'] = $item->parameter_option->graphic_sku;

                        if (!$item->parameter_option->design) {
                            Design::check($item->parameter_option->graphic_sku);
                        }

                        if ($item->parameter_option->design->xml == '1') {
                            $graphic['xml'] = 'Found';
                        } else {
                            $graphic['xml'] = 'Not Found';
                        }

                        if ($item->parameter_option->design->template == '1') {
                            $graphic['template'] = 'Found';
                        } else {
                            $graphic['template'] = 'Not Found';
                        }

                        $error['graphics'][] = $graphic;
                    } else if (!$item->parameter_option) {
                        Log::error('Parameter option not found ' . $batch->batch_number . ',' . $item->id);
                    }
                }

                if (array_key_exists($batch->batch_number, $error_files)) {
                    $error['in_dir'] = 'Found';
                } else {
                    $error['in_dir'] = 'Not Found';
                }

                $errors[] = $error;
                $error = null;
            }
        } else if ($action == 'count') {

            $errors = Batch::join('stations', 'batches.station_id', '=', 'stations.id')
                ->whereIn('batches.status', [2, 4])
                ->where('stations.type', 'G')
                ->where('graphic_found', '>', 1)
                ->count();
        }

        return $errors;
    }

    private function getManual($return_type = 'batches')
    {
        $manual_list = array_diff(scandir(self::$manual_dir), array('..', '.'));

        $batch_numbers = array();

        if ($return_type == 'list') {

            foreach ($manual_list as $dir) {

                $batch_numbers[$this->getBatchNumber($dir)] = self::$manual_dir . $dir;
            }

            $batch_numbers = $this->removeSure3d($batch_numbers);

            return $batch_numbers;
        } else {

            foreach ($manual_list as $dir) {

                $batch_numbers[] = $this->getBatchNumber($dir);
            }

            $batch_numbers = $this->removeSure3d($batch_numbers);

            $batches = Batch::with('itemsCount', 'first_item', 'items')
                ->join('stations', 'batches.station_id', '=', 'stations.id')
                ->whereIn('batches.status', [2, 4])
                ->where('stations.type', 'G')
                ->whereIn('batch_number', $batch_numbers)
                ->orderBy('min_order_date')
                ->get();


            return $batches;
        }
    }

    private function removeSure3d($batch_numbers)
    {

        $items = Item::where('item_status', 1)
            ->whereNull('sure3d')
            ->where('is_deleted', '0')
            ->whereIn('batch_number', $batch_numbers)
            ->where('item_option', 'LIKE', '%Custom_EPS_download_link%')
            ->get();

        $sure3d_batches = array();

        foreach ($items as $item) {

            if (!in_array(substr($item->batch_number, 0, 1), ['R', 'X'])) {

                $options = json_decode($item->item_option, true);

                if (isset($options["Custom_EPS_download_link"]) && $item->sure3d == null) {
                    $item->sure3d = $options["Custom_EPS_download_link"];
                    $item->save();
                    $sure3d_batches[] = $item->batch_number;
                }
            }
        }

        $sure3d_batches = array_unique($sure3d_batches);

        foreach ($sure3d_batches as $batch) {

            $result = Batch::export($batch);

            if (!isset($result['error'])) {
                unset($batch_numbers[array_search($batch, $batch_numbers)]);
            }
        }

        return $batch_numbers;
    }

    public function sentToPrinter(Request $request)
    {
        $dates = array();
        $date[] = date("Y-m-d");
        $date[] = date("Y-m-d", strtotime('-3 days'));
        $date[] = date("Y-m-d", strtotime('-4 days'));
        $date[] = date("Y-m-d", strtotime('-7 days'));
        $date[] = date("Y-m-d", strtotime('-8 days'));

        if ($request->all() == []) {

            $summary = Batch::join('stations', 'batches.station_id', '=', 'stations.id')
                ->whereIn('batches.status', [2, 4])
                ->whereHas('store', function ($q) {
                    $q->where('permit_users', 'like', "%" . auth()->user()->id . "%");
                })
                ->where('stations.type', 'G')
                ->where('graphic_found', '1')
                ->where('to_printer', '!=', '0')
                ->selectRaw("
  											to_printer,
  											count(DISTINCT batches.id) as batch_count,
                        COUNT(IF(to_printer_date >= '{$date[1]} 00:00:00', batches.id, NULL)) as group_1,
  											COUNT(IF(to_printer_date >= '{$date[3]} 00:00:00' AND to_printer_date <= '{$date[2]} 23:59:59', batches.id, NULL)) as group_2,
  											COUNT(IF(to_printer_date <= '{$date[4]} 23:59:59', batches.id, NULL)) as group_3
  											")
                ->groupBy('to_printer')
                ->get();

            return response()->json([
                'status' => 200,
                'data' => ['summary' => $summary],
            ], 200);
        } else {

            $op = '!=';
            $printer = '0';

            if ($request->has('printer') && $request->get('printer') != '') {
                $op = '=';
                $printer = $request->get('printer');
            }

            $date_1 = '2016-06-01';
            $date_2 = $date[0];

            if ($request->has('date')) {
                if ($request->get('date') == 1) {
                    $date_1 = $date[1];
                } else if ($request->get('date') == 2) {
                    $date_1 = $date[3];
                    $date_2 = $date[2];
                } else if ($request->get('date') == 3) {
                    $date_2 = $date[4];
                } else {
                    Log::error('Sent to Printer: Error unrecognized date ' . $request->get('date'));
                }
            }

            $to_printer = Batch::with('itemsCounts', 'first_item', 'store')
                ->join('stations', 'batches.station_id', '=', 'stations.id')
                // ->whereHas('store', function ($q) {
                //     $q->where('permit_users', 'like', "%" . auth()->user()->id . "%");
                // })
                ->whereIn('batches.status', [2, 4])
                ->where('stations.type', 'G')
                ->where('graphic_found', '1')
                ->where('to_printer', $op, $printer)
                ->where('to_printer_date', '>=', $date_1 . ' 00:00:00')
                ->where('to_printer_date', '<=', $date_2 . ' 23:59:59')
                ->selectRaw('batches.*, stations.*, datediff(CURDATE(), to_printer_date) as days')
                ->orderBy('to_printer_date', 'ASC')
                ->get();

            $batch_numbers = $to_printer->pluck('batch_number');

            $w = new Wasatch;
            $batch_queue = array();

            foreach ($batch_numbers as $batch_number) {
                $batch_queue[$batch_number] = $w->notInQueue($batch_number);
            }

            $total_items = Item::where('is_deleted', '0')
                ->whereIn('batch_number', $batch_numbers)
                ->count();

            $scans = [];
            foreach ($to_printer as $batch) {
                $data = Batch::lastScan($batch->batch_number);
                $scans[$batch->batch_number] = $data['username'];
            }

            return response()->json([
                'status' => 200,
                'data' => [
                    'to_printer' => $to_printer,
                    'batch_queue' => $batch_queue,
                    'total_items' => $total_items,
                    'scans' => $scans,
                ],
            ], 200);
        }
    }

    public function showBatchSummaries()
    {

        // if (auth()->user()->id != 83) {
        //   return 'Please try again later';
        // }


        $production = Batch::with('production_station', 'store')
            ->join('sections', function ($join) {
                $join->on('batches.section_id', '=', 'sections.id')
                    ->where('sections.inventory', '!=', '1')
                    ->orWhere(DB::raw('batches.inventory'), '=', '2');
            })
            ->where('batches.is_deleted', '0')
            ->selectRaw('batches.id, production_station_id, section_id, store_id, if(substr(batch_number,1,1) = "R", "Reject", "") as type, count(batches.id) as count')
            ->searchStatus('active')
            ->searchPrinted('0')
            ->groupBy('section_id')
            ->groupBy('production_station_id')
            ->groupBy('store_id')
            ->groupBy('type') //->toSql();
            ->get();
        // dd($production);

        $graphics = Batch::with('store')
            ->join('batch_routes', 'batches.batch_route_id', '=', 'batch_routes.id')
            ->where('batches.is_deleted', '0')
            ->selectRaw('batches.id, batch_route_id, batch_routes.graphic_dir, store_id, if(substr(batch_number,1,1) = "R", "Reject", "") as type, count(batches.id) as count')
            ->searchStatus('active')
            ->searchPrinted('2')
            ->groupBy('batch_routes.graphic_dir', 'store_id', 'type', 'batch_route_id')
            ->get();

        $date = date("Y-m-d") . ' 00:00:00';

        $today = Batch::with('production_station', 'section', 'summary_user')
            ->selectRaw('batches.id, summary_date, summary_user_id, production_station_id, section_id, count(batch_number) as count')
            ->searchStatus('active')
            ->where('summary_date', '>', $date)
            ->groupBy('section_id')
            ->groupBy('production_station_id')
            ->groupBy('summary_date')
            ->groupBy('summary_user_id')
            ->orderBy('summary_date', 'DESC')
            ->get();

        return response()->json([
            'production' => $production,
            'graphics' => $graphics,
            'today' => $today,
        ], 200);
    }

    public function printerOption()
    {
        $data = [];
        foreach ($this->printers as $key => $value) {
            $data[] = [
                'value' => $key,
                'label' => $value,
            ];
        }
        return response()->json($data, 200);
    }

    public function statusOption()
    {
        $data = [];
        foreach (Rejection::graphicStatus(1) as $key => $value) {
            $data[] = [
                'value' => $key,
                'label' => $value,
            ];
        }
        return response()->json($data, 200);
    }

    public function showSublimation(Request $request)
    {
        set_time_limit(0);

        $request->has('from_date') ? $from_date = $request->get('from_date') . ' 00:00:00' : $from_date = '2016-06-01 00:00:00';
        $request->has('to_date') ? $to_date = $request->get('to_date') . ' 23:59:59' : $to_date = date("Y-m-d H:i:s");
        $request->has('store_id') ? $store_id = $request->get('store_id') : $store_id = null;
        $request->has('production_station_id') ? $production_station_id = $request->get('production_station_id') : $production_station_id = null;
        $request->has('type') ? $type = $request->get('type') : $type = null;
        $request->has('select_batch') ? $select_batch = $request->get('select_batch') : $select_batch = null;


        if ($request->all() != []) {
            $batches = Batch::with('items', 'production_station', 'route')
                ->join('stations', 'batches.station_id', '=', 'stations.id')
                ->where('batches.section_id', 6)
                ->searchStatus('active')
                ->where('stations.type', 'G')
                ->where('stations.id', 92)
                ->where('graphic_found', '1')
                ->where('to_printer', '0')
                ->searchBatch($select_batch)
                ->where('min_order_date', '>', $from_date)
                ->where('min_order_date', '<', $to_date)
                ->searchStore($store_id)
                ->searchProductionStation($production_station_id)
                // ->where('to_printer', '0')
                ->select(
                    'batch_number',
                    'status',
                    'station_id',
                    'batch_route_id',
                    'store_id',
                    'graphic_found',
                    'to_printer',
                    'to_printer_date',
                    'min_order_date',
                    'production_station_id'
                )
                ->orderBy('min_order_date')
                ->get();
            $summary = [];
        } else {

            $batches = [];

            $summary = Batch::with('production_station', 'items.rejections.user', 'items.rejections.rejection_reason_info')
                ->join('stations', 'batches.station_id', '=', 'stations.id')
                ->where('batches.section_id', 6)
                ->searchStatus('active')
                ->searchStore($store_id)
                ->where('stations.type', 'G')
                ->where('stations.id', 92)
                ->where('graphic_found', '1')
                ->where('to_printer', '0')
                ->selectRaw('production_station_id, MIN(min_order_date) as date, count(*) as count')
                ->groupBy('production_station_id')
                ->orderBy('date', 'ASC')
                ->get();
        }

        if (count($batches) > 0) {
            $store_ids = array_unique($batches->pluck('store_id')->toArray());

            $storesList = Store::where('permit_users', 'like', "%" . auth()->user()->id . "%")
                ->where('is_deleted', '0')
                ->where('invisible', '0')
                ->whereIn('store_id', $store_ids)
                ->orderBy('sort_order')
                ->get()
                ->pluck('store_name', 'store_id');
        } else {
            $storesList = Store::list('%', '%', 'none');
        }

        $stores = [];
        foreach ($storesList as $key => $value) {
            $stores[] = [
                'value' => $key,
                'label' => $value
            ];
        }

        $config = Printer::configurations();

        if (isset($from_date) && $from_date == '2016-06-01 00:00:00') {
            $from_date = null;
        }

        return response()->json([
            'summary' => $summary,
            'batches' => $batches,
            'stores' => $stores,
            'config' => $config
        ], 200);
    }

    public function showSublimationQueues()
    {
        $w = new Wasatch;
        $queues = $w->getQueues();
        return response()->json($queues, 200);
    }

    public function moveToProduction(Request $request)
    {
        $request->has('store_id') ? $store_id = $request->get('store_id') : $store_id = null;

        $to_move = Batch::with('section', 'production_station')
            ->join('stations', 'batches.station_id', '=', 'stations.id')
            ->where('batches.is_deleted', '0')
            ->searchStatus('movable')
            ->where('graphic_found', '1')
            ////->whereNotNull('summary_date')
            ->where('stations.type', 'G')
            ->searchStore($store_id)
            ->selectRaw('section_id, production_station_id, count(*) as total')
            ->groupBy('section_id')
            ->groupBy('production_station_id')
            ->orderBy('section_id')
            ->get();
        return response()->json($to_move, 200);
    }

    public function moveToQC(Request $request)
    {
        $request->has('store_id') ? $store_id = $request->get('store_id') : $store_id = null;

        $to_move = Batch::with('section', 'production_station')
            ->join('stations', 'batches.station_id', '=', 'stations.id')
            ->where('batches.is_deleted', '0')
            ->searchStatus('movable')
            ->where('graphic_found', '1')
            ////->whereNotNull('summary_date')
            ->where('stations.type', 'P')
            ->searchStore($store_id)
            ->selectRaw('section_id, production_station_id, count(*) as total')
            ->groupBy('section_id')
            ->groupBy('production_station_id')
            ->orderBy('section_id')
            ->get();

        // $last_scan = Batch::with('section', 'production_station', 'items')
        //     ->join('stations', 'batches.station_id', '=', 'stations.id')
        //     ->where('batches.is_deleted', '0')
        //     ->where('stations.type', 'Q')
        //     ->latest('batches.change_date')
        //     ->take(5)
        //     ->get();

        // for ($i = 0; $i < 5; $i++) {
        //     $username[$i] = Batch::lastScan($last_scan[$i]->batch_number);
        //     $name[$i] = $username[$i]['username'];
        // }

        return response()->json($to_move, 200);
    }

    public function ShowBatch(Request $request)
    {

        if (!$request->has('scan_batches')) {
            return response()->json([
                "message" => 'No Batch Selected',
                'status' => 203
            ], 203);
        }

        $scan_batches = trim($request->get('scan_batches'));

        if (substr($scan_batches, 0, 4) == 'BATC') {
            $batch_number = $this->getBatchNumber(substr($scan_batches, 4));
        } else {
            $batch_number = $this->getBatchNumber($scan_batches);
        }

        if ($batch_number == null) {
            return response()->json([
                "message" => 'No Batch Selected',
                'status' => 203
            ], 203);
        }
        if ($request->has('isProduction')) {
            $result = $this->moveNext($batch_number, 'production');
        } else {
            $result = $this->moveNext($batch_number, 'qc');
        }

        if ($result['error'] != null) {
            if ($request->has('isProduction')) {
                Batch::note($batch_number, 4, '6', 'Production - ' . $result['error']);
            } else {
                Batch::note($batch_number, 4, '6', 'QC - ' . $result['error']);
            }
            return response()->json([
                "message" => $result['error'],
                'status' => 203
            ], 203);
        }

        $items = Item::where('items.batch_number', $batch_number)
            ->where('items.is_deleted', '0')
            ->first();

        //        $customer = Customer::where('order_id', $items->order_id)
        //            ->where('is_deleted', '0')
        //            ->first();

        $parts = parse_url($items->item_thumb);

        // /assets/images/Sure3d/thumbs/1217029-13-Image.jpg


        //
        //        $filename = "^XA";
        //        $filename .= "^CF0,60";
        //        $filename .= "^FO100,50^FD Batch Number^FS";
        //        $filename .= "^FX for barcode.";
        //        $filename .= "^BY5,2,270";
        //        $filename .= "^FO50,100";
        //        $filename .= "^BCN,100,Y,N,N";
        //        $filename .= "^FD$batch_number^FS";
        //        $filename .= "^CF0,40";
        //        $filename .= "^FO40,245^FDCustomer name: $customer->ship_full_name^FS";
        //        $filename .= "^FO40,280^FDStyle Number: $items->item_code ^FS";
        //        $filename .= "^FO40,320^FDQTY: $items->item_quantity^FS";
        //        $filename .= $zplImage;
        //        $filename .= "^XZ";


        $to_move = Batch::with('items', 'route', 'station', 'summary_user')
            ->where('batch_number', $result['batch_number'])
            ->first();


        $format = 'Qty: ' . $items->item_quantity . ' - #[COUNT]';
        $filename = "^XA~TA000~JSN^LT0^MNW^MTT^PON^PMN^LH0,0^JMA^PR2,2~SD30^JUS^LRN^CI0^XZ";
        $filename .= "^XA";
        $filename .= "^MMT";
        $filename .= "^PW305";
        $filename .= "^LL0203";
        $filename .= "^LS0";
        $filename .= "^FO55,35^A0,40^FB220,1,0,CH^FD{$format}^FS";
        $filename .= "^FO55,70^A0,30^FB220,1,0,CH^FD[UNIQUE_ID]^FS";

        if (stripos($batch_number, "-") !== false) {
            $filename .= "^FO25,100^BY2.3^BCN,60,,,,A^FD{$batch_number}^FS";
        } else {
            $filename .= "^FO100,100^BY2.3^BCN,60,,,,A^FD{$batch_number}^FS";
        }

        $filename .= "^PQ1,0,1,Y^XZ";

        $created = $to_move->items[0]->created_at ?? \Carbon\Carbon::now();
        $date = $created->toDateString();

        $filename = str_replace("[UNIQUE_ID]", $date, $filename);
        $label = trim(preg_replace('/\n+/', ' ', $filename));

        return response()->json([
            "to_move" => $to_move,
            "label" => $label,
            "message" => $request->has('isProduction') ? 'Moved to Production' : 'Moved to QC',
            "status" => 201
        ], 201);
        // return view('graphics.show_batch_qc', compact('to_move', 'label'));
    }

    private function getBatchNumber($filename)
    {
        // if (substr($filename, -4, 1) == '.') {
        //   $filename = substr($filename, 0, -4);
        // }

        $ex = explode('-', $filename);

        if (is_numeric($ex[0])) {
            return $ex[0];
        } elseif (isset($ex[1])) {
            return $ex[0] . '-' . $ex[1];
        } else {
            return null;
        }
    }

    public function moveNext($batch, $type, $canLook = false, $normal = true)
    {


        if ($batch instanceof Batch && $normal) {
            //            $ns = Batch::getNextStation('object', $batch->batch_route_id, $batch->station_id);

            //            if (is_object($ns)) {
            //                if (stripos($ns->station_name, "S-GGR-INDIA") !== false) return ['error' => null, 'success' => sprintf('Warning: Batch %s cannot be moved', $batch->batch_number), 'batch_number' => $batch->batch_number];
            //            }


            if ($batch->station) {
                $station_name = $batch->station->station_name;
            } else {
                $station_name = 'Station not Found';
            }

            $batch = Batch::with("route")->where("batch_number", $batch->batch_number)->first();
            $stations = BatchRoute::routeThroughStations($batch->batch_route_id, $station_name);

            if (stripos($stations, "S-GGR-INDIA") !== false) {
                if (!$canLook) {
                    $batch->prev_station_id = null;
                    $batch->station_id = 264;
                } else {
                    $batch->prev_station_id = null;
                    $batch->station_id = 92;
                }
            } else {
                $batch->prev_station_id = null;
                $batch->station_id = 92;
            }

            $batch->save();

            return [
                'success' => sprintf('Batch %s Successfully Moved to %s<br>', $batch->batch_number, "station"),
                'batch_number' => $batch->batch_number,
                'error' => null
            ];
        }

        $success = null;
        $error = null;

        if (!($batch instanceof Batch)) {

            $num = $batch;

            $batch = Batch::with('route.stations_list', 'station')
                ->where('batch_number', $num)
                ->searchstatus('active')
                ->first();

            if (!$batch) {
                // if (!$batch || count($batch) == 0) {

                $related = Batch::related($num);

                if ($related == false) {
                    return [
                        'error' => sprintf('Batch not found'),
                        'success' => $success,
                        'batch_number' => $num
                    ];
                } else {
                    $batch = $related;
                }
            }
        }

        $next_station = Batch::getNextStation('object', $batch->batch_route_id, $batch->station_id);

        if (is_object($next_station)) {
            if (stripos($next_station->station_name, "S-GGR-INDIA") !== false) return ['error' => $error, 'success' => sprintf('Warning: Batch %s cannot be moved', $batch->batch_number), 'batch_number' => $batch->batch_number];
        }

        //        if(is_object($next_station) && $next_station->station_name === "S-GRPH" && Auth::user() === null) {
        //            return ['error' => $error,
        //                'success' => sprintf('Warning: Batch %s cannot be moved', $batch->batch_number),
        //                'batch_number' => $batch->batch_number
        //            ];
        //        }

        if ($type == 'graphics') {
            // test if it is the first graphics station in route
            if (!($batch->route->stations_list->first()->station_id == $batch->station_id && $batch->station->type == 'G')) {
                return [
                    'error' => $error,
                    'success' => sprintf('Warning: Batch %s not in first graphics station', $batch->batch_number),
                    'batch_number' => $batch->batch_number
                ];
            }
        } else if ($type == 'production') {

            if (!($batch->station->type == 'G' && $next_station->type == 'P')) {
                return [
                    'error' => sprintf(
                        'Batch %s not moving from graphics to production - ' .
                            $batch->station->station_name . ' ' . $batch->change_date . '<br>',
                        $batch->batch_number
                    ),
                    'success' => $success,
                    'batch_number' => $batch->batch_number
                ];
            }

            if ($batch->status != 'active' && $batch->status != 'back order') {
                return [
                    'error' => sprintf('Batch %s status is %s', $batch->batch_number, $batch->status),
                    'success' => $success,
                    'batch_number' => $batch->batch_number
                ];
            }
        } else if ($type == 'qc') {

            if (!($batch->station->type == 'P' && $next_station->type == 'Q')) {
                return [
                    'error' => sprintf(
                        'Batch %s not moving from production to QC - ' .
                            $batch->station->station_name . ' ' . $batch->change_date . '<br>',
                        $batch->batch_number
                    ),
                    'success' => $success,
                    'batch_number' => $batch->batch_number
                ];
            }

            if ($batch->status != 'active' && $batch->status != 'back order') {
                return [
                    'error' => sprintf('Batch %s status is %s', $batch->batch_number, $batch->status),
                    'success' => $success,
                    'batch_number' => $batch->batch_number
                ];
            }
        } else if ($type == 'print') {

            if ($next_station == null || $next_station->station_name != 'S-GRP') {
                return [
                    'error' => 'Batch not moved, next station not printer station',
                    'success' => $success,
                    'batch_number' => $batch->batch_number
                ];
            }
        }

        if ($next_station && $next_station->id != '0') {
            $batch->prev_station_id = $batch->station_id;
            $batch->station_id = $next_station->id;
            $batch->save();
            $success = sprintf('Batch %s Successfully Moved to %s<br>', $batch->batch_number, $next_station->station_name);
        } else {
            $error = sprintf('Batch %s has no further stations on route <br>', $batch->batch_number);
        }

        return ['error' => $error, 'success' => $success, 'batch_number' => $batch->batch_number];
    }

    public function stationOption()
    {
        $station = Station::where('is_deleted', '0')
            ->whereIn('type', ['P', 'Q'])
            ->where('section', 6)
            ->get();

        $station->transform(function ($item) {
            return [
                'value' => $item['id'],
                'label' => $item['station_description']
            ];
        });
        return $station;
    }

    public function printAllSublimation(Request $request)
    {
        if (!file_exists(public_path($this->sort_root))) {
            return response()->json([
                'message' => 'Cannot find Graphics Directory',
                'status' => 203
            ], 203);
        }

        // if (!file_exists($this->sub_dir . 'lock')) {
        //   touch($this->sub_dir . 'lock');
        // }
        //
        // $f = fopen($this->sub_dir . 'lock', 'r');
        //
        // if (!flock($f, LOCK_EX)) {
        //   Log::info('Print sublimation - Sublimation is locked');
        //   return 'Sublimation Directory Busy... Retry';
        // }

        if (!$request->has('print_batches') || !$request->has('printer')) {
            Log::error('printAllSublimation: Batches or Printer not provided');
            return response()->json([
                'message' => 'Batches or Printer not provided',
                'status' => 203
            ], 203);
        }

        $print_batches = $request->get('print_batches');
        $printer = $request->get('printer');

        $error = array();
        $success = array();

        foreach ($print_batches as $batch_number) {
            $file = $this->getArchiveGraphic($batch_number);

            if (substr($file, 0, 5) != 'ERROR') {
                $x = $this->printSubFile($file, $printer, $batch_number);   //TODO uncomment and write printSubFile function from 5p
                if ($x == 'success') {
                    $success[] = $file . ' sent to ' . $printer;
                } else {
                    $error[] = $file . ' - ' . $x;
                }
            } else {
                $error[] = $batch_number . ' - ' . $file;
            }
        }

        return response()->json([
            'message' => 'Printed',
            'status' => 201
        ], 201);
    }

    public function getArchiveGraphic($name)
    {
        $list = glob(public_path(self::$archive) . $name . "*");
        if (count($list) < 1) {
            $batch = Batch::with(
                'items.order.store',
                'items.rejections.user',
                'items.rejections.rejection_reason_info',
                'items.spec_sheet',
                'items.product',
                'station',
                'route',
                'section',
                'store',
                'summary_user'
            )
                ->where('is_deleted', '0')
                ->where('batch_number', $name)
                ->first() ?? null;
            if (!$batch) {
                return "ERROR reprintGraphic: Batch not found";
            }
            foreach ($batch->items ?? [] as $item) {
                //    $path = "/var/www/order.monogramonline.com" . '/public_html/assets/images/template_thumbs/' . $item->order_id . "-" . $item->id . '.jpg';

                //TODO need to verify path URL (check if it is item_code or item_sku) - prev one is 
                // $path = public_path("/assets/images/product_thumb/") . $item->item_sku . ".jpg"; 
                $path = public_path("/assets/images/product_thumb/") . $item->item_code . ".jpg";
                if (file_exists($path)) {
                    if (copy($path, public_path(self::$archive) . $name)) {
                        $list2 = glob(public_path(self::$archive) . $name . "*");
                        if (count($list2) >= 1) {
                            foreach ($list2 as $file) {
                                $files[filemtime($file)] = $file;
                            }
                            ksort($files);
                            return array_pop($files);
                        } else {
                            $msg = "ERROR reprintGraphic: Error file was not found.... after checking twice";
                            return "ERROR Not found after trying to fix archive lost.";
                        }
                    }
                } else {
                    return "ERROR reprintGraphic: No thumb exist for " . $item->order_id . "-" . $item->id . "(" . $item->item_code . ")";
                }
            }
            return 'ERROR not found in Archive/could not get at all!';
        }
        foreach ($list as $file) {
            $files[filemtime($file)] = $file;
        }
        ksort($files);
        return array_pop($files);
    }

    public function printSublimation(Request $request)
    {
        if ($request->get('pdf') == true) {
            return $this->printSubFile(
                null,
                $request->get('printer'),
                $request->get('batch_number'),
                100,
                null,
                0,
                $request->get('pdf'),
                false
            );
        }

        if (!File::exists(public_path($this->sort_root))) {
            return response()->json([
                'message' => 'Cannot find Graphics Directory',
                'status' => 203
            ], 203);
        }


        if (!$request->has('printer')) {
            return response()->json([
                'message' => 'You did not select a printer',
                'status' => 203
            ], 203);
        }

        if ($request->has('batch_number') && $request->get('batch_number') != '') {
            $batch_number = $request->get('batch_number');
        }
        $file = $this->getArchiveGraphic($batch_number);
        if (substr($file, 0, 5) == 'ERROR') {
            return response()->json([
                'data' => $file,
                'message' => substr($file, 6, strlen($file)),
                'status' => 203
            ], 203);
        }
        $printer = $request->get('printer');

        $x = $this->printSubFile(
            $file,
            $printer,
            $batch_number,
            $request->get('scale'),
            $request->get('minsize'),
            $request->get('mirror'),
            false,
            false
        );
        return $x;
    }

    private function printSubFile($file, $printer, $batch_number = null, $scale = 100, $minsize = null, $mirror = 0, $pdf = false, $normal = true)
    {
        if ($pdf) {
            $jsonPayLoad = $this->createJsonPayload($batch_number);

            $api = new ApiClient(null, null, null, "none");
            $token = $api->getAuthenticationToken();
            $createResponse = $api->postPayload('/api/printing_batches', $token, $jsonPayLoad);
            if ($createResponse->getStatusCode() == 201) {
                $createResponseData = json_decode($createResponse->getBody()->getContents(), true);
                $fileResponse = $api->getPayload(
                    $createResponseData['pdfFile'],
                    $token
                );

                if ($fileResponse->getStatusCode() == 200) {
                    $fileResponseData = json_decode($fileResponse->getBody()->getContents(), true);
                    $printerNumber = explode("-", $printer)[1];

                    $stagingBaseDir = '/var/www/order.monogramonline.com/storage';

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, ApiClient::API_SERVER . '/' . $fileResponseData['contentUrl']);
                    curl_setopt($ch, CURLOPT_VERBOSE, 0);

                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HEADER, false);
                    curl_setopt($ch, CURLOPT_BINARYTRANSFER, true); // Videos are needed to transfered in binary
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

                    $response = curl_exec($ch);
                    $filename = explode('/', curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
                    $filename = array_pop($filename);

                    curl_close($ch);
                    $result = ["file" => $response, "filename" => $filename];


                    $pdfFilePath = $stagingBaseDir . DIRECTORY_SEPARATOR . 'wasatch/staging-' . $printerNumber;

                    $fp = fopen($pdfFilePath . DIRECTORY_SEPARATOR . $batch_number . ".pdf", 'w');
                    fwrite($fp, $result['file']);
                    fclose($fp);

                    $folderPath = "/media/RDrive/" . 'SOFT-' . $printerNumber . "/";

                    shell_exec("mv " . $pdfFilePath . $batch_number . ".pdf" . " " . $folderPath . $batch_number . ".pdf");
                    $batch = Batch::where('batch_number', $batch_number)->first();

                    if (!$batch) {
                        return 'Batch not found ' . $batch_number;
                    }

                    if ($batch->to_printer != '0') {
                        Batch::note($batch->batch_number, $batch->station_id, '6', 'Batch already printed - printSublimation');
                        return 'Batch marked as printed';
                    }

                    if ($batch) {
                        try {

                            $msg = $this->moveNext($batch, 'print', false, $normal);

                            if ($msg['error'] != '') {
                                Batch::note($batch->batch_number, $batch->station_id, '6', 'printSublimation - ' . $msg['error']);
                                return 'Error: ' . $msg['error'] . ' - ' . $batch_number;
                            }

                            $batch->to_printer = $printer;
                            $batch->to_printer_date = date("Y-m-d H:i:s");
                            $batch->change_date = date("Y-m-d H:i:s");
                            $batch->save();
                        } catch (\Exception $e) {
                            Log::error('printSubFile: Error moving batch ' . $file . ' - ' . $e->getMessage());
                            Batch::note($batch->batch_number, $batch->station_id, '6', 'Exception moving Batch - printSublimation');
                            return 'Error: Error moving batch ' . $batch_number;
                        }

                        Batch::note($batch->batch_number, $batch->station_id, '6', 'Graphics Sent to Printer');
                    } else {
                        Log::error('printSubFile: Batch not found ' . $batch_number);
                        return 'Error: Batch not found ' . $batch_number;
                    }


                    return "success";
                } else {
                    return "API Error " . $fileResponse->getBody()->getContents();
                }
            } else {
                return "API Error " . $createResponse->getBody()->getContents();
            }
        } else {

            if (!file_exists($file)) {
                Log::error('printSubFile: File not found ' . $file);
                return response()->json([
                    'message' => 'File not found',
                    'status' => 203
                ], 203);
            }

            if ($batch_number == null) {
                $batch_number = $this->getBatchNumber($file);
            }

            $batch = Batch::where('batch_number', $batch_number)->first();

            if (!$batch) {
                Log::error('printSubFile: Batch not found ' . $batch_number);
                return response()->json([
                    'message' => 'Batch not found ' . $batch_number,
                    'status' => 203
                ], 203);
            }
            if ($batch->to_printer != '0') {
                Log::error('printSubFile: Batch already printed ' . $batch_number);
                Batch::note($batch->batch_number, $batch->station_id, '6', 'Batch already printed - printSublimation');
                return response()->json([
                    'message' => 'Batch already printed',
                    'reload' => true,
                    'status' => 203
                ], 203);
            }

            $w = new Wasatch;
            $notInQueue = 1;
            // $notInQueue = $w->notInQueue($batch_number);  //TODO uncomment this line

            if ($notInQueue != '1') {
                return $notInQueue;
            }

            // TODO - need to check this function (emergency)
            $summary_file = $this->createSummary($batch_number);

            // if (!file_exists($summary_file)) {
            //     sleep(20);
            // }

            $file_list = FileHelper::getContents($file);

            if (!is_array($file_list) || count($file_list) < 1) {
                Log::error('printSubFile: No Files Found - ' . $file);
                return response()->json([
                    'message' => 'No Files Found',
                    'status' => 203
                ], 203);
            }

            #####################################
            $frameSize = null;
            $parameterOptions = Item::join('parameter_options', 'items.child_sku', '=', 'parameter_options.child_sku')
                ->where('items.is_deleted', '0')
                ->where('items.batch_number', '=', $batch_number)
                ->first();
            //        if($parameterOptions->frame_size) {
            $frameSize = $parameterOptions->frame_size;

            //            if (isEmpty($frameSize)) {
            //                $frameSize = 0;
            //                Log::error('printSubFile: Batch Summary Creation Error - ' . $batch_number);
            //                return 'Parameter Options ';
            //            }

            $mirror = $parameterOptions->mirror;
            $orientation = $parameterOptions->orientation;
            //        }
            //        dd($file, $printer, $batch_number, $parameterOptions, $frameSize, $mirror );
            ###################################


            $list = array();
            foreach ($file_list as $path) {
                //          $this->helper->jdbg("path", $path);
                $info = ImageHelper::getImageSize($path, $scale);
                //          $this->helper->jdbg("info", $info);
                //dd($info);
                $info['frameSize'] = $frameSize;
                $info['mirror'] = $mirror;
                $info['orientation'] = $orientation;

                if (is_array($info)) {
                    if (strpos($path, "RDrive")) {
                        $info['source'] = 'R';
                        $list[str_replace(public_path($this->sort_root), '/', $path)] = $info;
                    } else if (strpos($path, 'graphics')) {
                        $info['source'] = 'P';
                        $list[str_replace($this->old_sort_root, '/', $path)] = $info;
                    }
                } else {
                    Log::error('printSubFile: Imagesize Error - ' . $path);
                    $batch->graphic_found = '7';
                    $batch->save();
                    self::removeFile($path);
                    return response()->json([
                        'message' => 'Imagesize Error ' . $path,
                        'status' => 203
                    ], 203);
                }
            }

            if ($summary_file != false && file_exists($summary_file)) {
                $info = ImageHelper::getImageSize($summary_file);
            } else {
                Log::error('printSubFile: Batch Summary Creation Error - ' . $batch_number);
                return response()->json([
                    'message' => 'Batch Summary Creation Error',
                    'status' => 203
                ], 203);
            }


            if (is_array($info)) {
                $info['source'] = 'R';
                $info['frameSize'] = $frameSize;
                //         $info['mirror'] = $mirror;
                $list[str_replace(public_path($this->sort_root), '/', $summary_file)] = $info;
            } else {
                Log::error('printSubFile: Batch Summary Imagesize Error - ' . $batch_number);
                return response()->json([
                    'message' => 'Batch Summary Imagesize Error',
                    'status' => 203
                ], 203);
            }
            $w = new Wasatch;
            $list = array_reverse($list);
            $downloadfile = $w->printJob($list, $batch_number, substr($printer, -1), substr($printer, 0, 4), null, $batch->items[0]->item_quantity);
            Batch::note($batch->batch_number, '', '6', 'Sent to ' . $printer);

            if ($batch) {
                try {
                    $msg = $this->moveNext($batch, 'print', false, $normal);

                    if ($msg['error'] != '') {
                        Log::info('printSubFile: ' . $msg['error'] . ' - ' . $file);
                        Batch::note($batch->batch_number, $batch->station_id, '6', 'printSublimation - ' . $msg['error']);
                        return response()->json([
                            'message' => 'Error: ' . $msg['error'] . ' - ' . $batch_number,
                            'status' => 203
                        ], 203);
                    }

                    $batch->to_printer = $printer;
                    $batch->to_printer_date = date("Y-m-d H:i:s");
                    $batch->change_date = date("Y-m-d H:i:s");
                    $batch->save();
                } catch (\Exception $e) {
                    Log::error('printSubFile: Error moving batch ' . $file . ' - ' . $e->getMessage());
                    Batch::note($batch->batch_number, $batch->station_id, '6', 'Exception moving Batch - printSublimation');
                    return response()->json([
                        'message' => 'Error: Error moving batch ' . $batch_number,
                        'status' => 203
                    ], 203);
                }

                Batch::note($batch->batch_number, $batch->station_id, '6', 'Graphics Sent to Printer');
            } else {
                Log::error('printSubFile: Batch not found ' . $batch_number);
                return response()->json([
                    'message' => 'Error: Batch not found ' . $batch_number,
                    'status' => 203
                ], 203);
            }

            return response()->json([
                'data' => $downloadfile,
                'message' => 'Printed',
                'status' => 201
            ], 201);
        }
    }

    public static function removeFile($path)
    {

        if (!file_exists($path)) {
            return true;
        }

        if (!is_dir($path)) {
            try {
                return unlink($path);
            } catch (\Exception $e) {
                Log::error('Graphics removeFile: cannot remove directory ' . $path);
                return false;
            }
        } else {

            if (substr($path, strlen($path) - 1, 1) != '/') {
                $path .= '/';
            }

            $files = glob($path . '*', GLOB_MARK);
            foreach ($files as $file) {
                if (is_dir($file)) {
                    self::removeFile($file);
                } else {
                    try {
                        unlink($file);
                    } catch (\Exception $e) {
                        Log::error('Graphics removeFile: cannot remove file ' . $file);
                        return false;
                    }
                }
            }

            return rmdir($path);
        }
    }

    protected function createJsonPayload($batchNumber)
    {
        $batchToProcess = Batch::with('items.parameter_option.design')
            ->where('batch_number', $batchNumber)
            ->first();
        $batch = Batch::where('batch_number', $batchNumber)->first();


        $childSku = $batchToProcess->items[0]->child_sku;
        $batchHeader = strtoupper($batchToProcess->items[0]->item_description);
        $seedPageSize = substr($childSku, -4);
        $doubleSided = substr($childSku, 0, 1) == 'D';

        $pdfParams = [
            'doubleSided' => false,
            'pageWidth' => 1570,
            'marginTop' => 0,
            'imgDpi' => 100,
            'columnLayout' => 1
        ];

        if (stripos($batchToProcess->items[0]->child_sku, "5060") !== false) {
            $pdfParams['pageHeight'] = 1740;
            $pdfParams['columnLayout'] = 1;
        } elseif (stripos($batchToProcess->items[0]->child_sku, "30") !== false) {
            $pdfParams['pageHeight'] = 1215;
            $pdfParams['columnLayout'] = 2;
        } else {
            if ($batchToProcess->items[0]->parameter_option->frame_size === 0) {
                $pdfParams['pageHeight'] = 1300;
            } else {
                $pdfParams['pageHeight'] = ($batchToProcess->items[0]->parameter_option->frame_size + 4) * 25.4 + 500;
                $pdfParams['columnLayout'] = 2;
            }

            //            Log::error('Unsupported layout ' . $batchToProcess->items[0]->child_sku);
            //            return 'Unsupported Layout, unselect Pdf option';
        }

        $ordersToProcess = [];
        $dpi = $pdfParams['imgDpi'];
        foreach ($batchToProcess->items as $item) {
            if ($item->item_status !== 'shipped') {
                $options = json_decode($item->item_option, true);
                $itemImages = [];

                if (file_exists("/var/www/order.monogramonline.com/assets/images/template_thumbs/" . $item->order_id . "-" . $item->id . '.jpg')) {
                    $itemImages[] = 'http://order.monogramonline.com/assets/images/template_thumbs/' . $item->order_id . "-" . $item->id . '.jpg';
                    $dpi = $this->getDPIImageMagick("/var/www/order.monogramonline.com/assets/images/template_thumbs/" . $item->order_id . "-" . $item->id . '.jpg');
                } else {
                    if ($batch->section_id == 6 || $batch->section_id == 15 || $batch->section_id == 18) {
                        $flop = 1;
                    } else if ($batch->section_id == 3 || $batch->section_id == 10) {
                        $flop = 0;
                    } else {
                        $flop = 0;
                    }

                    $file = $this->getArchiveGraphic($batchNumber);

                    if (is_dir($file)) {
                        $graphic_path = $file . '/';
                        $file_names = array_diff(scandir($file), array('..', '.'));
                    } else {
                        $graphic_path = '';
                        $file_names[] = $file;
                    }

                    $thumb_path = base_path() . '/public_html/assets/images/graphics/';

                    foreach ($file_names as $file_name) {

                        $name = substr($file_name, 0, strpos($file_name, '.'));

                        try {
                            ImageHelper::createThumb($graphic_path . $file_name, $flop, $thumb_path . $name . '.jpg', 750);
                        } catch (\Exception $e) {
                            return "Cannot find image at all";
                        }
                    }

                    if (isset($file_names)) {
                        foreach ($file_names as $thumb) {
                            $itemImages[] = "http://order.monogramonline.com/assets/images/graphics/" . substr($thumb, 0, strpos($thumb, '.')) . '.jpg';
                            $dpi = $this->getDPIImageMagick($thumb_path . substr($thumb, 0, strpos($thumb, '.')) . '.jpg');
                            break;
                        }
                    }
                }
                $pdfParams['imgDpi'] = $dpi;
                $itemsToProcess = [
                    "id" => $item->id,
                    "sku" => $item->child_sku,
                    "quantity" => $item->item_quantity,
                    "metadata" => [
                        "image" => $itemImages
                    ]
                ];

                $ordersToProcess[] = [
                    'id' => $item->order_id,
                    'po' => $item->order_5p,
                    'creationDate' => (string)$item->created_at,
                    'items' => [$itemsToProcess]
                ];
            }
        }


        return [
            'reference' => $batchToProcess->batch_number,
            'jsonData' => [
                'pdfParams' => $pdfParams,
                'batchInfo' => [
                    'batchNumber' => $batchToProcess->batch_number,
                    'productSKU' => $childSku,
                    'batchHeader' => $batchHeader
                ],
                'orders' => $ordersToProcess,
            ],
        ];
    }

    private function createSummary($batch_number)
    {
        if (auth()->user()) {
            $url = url("/graphics/sub_summary/" . $batch_number);
        } else {
            $url = url("/graphics/sub_screenshot/" . $batch_number);
        }

        if (!file_exists(public_path($this->sort_root) . 'summaries/')) {
            mkdir(public_path($this->sort_root) . 'summaries/');
        }

        $file = public_path($this->sort_root) . 'summaries/' . $batch_number . '.pdf';

        set_time_limit(0);
        try {
            $x = shell_exec('xvfb-run --server-args="-screen 0, 1024x768x24" wkhtmltopdf ' . $url . ' ' . $file . " > /dev/null 2>&1");
        } catch (\Exception $e) {
            Log::error('Error creating sublimation summary for batch ' . $batch_number);
            Log::error($e->getMessage());
        }


        try {
            $y = shell_exec("pdfcrop $file $file > /dev/null 2>&1");
        } catch (\Exception $e) {
            Log::error('Error cropping sublimation summary for batch ' . $batch_number);
            Log::error($e->getMessage());
            Log::error($y);
        }

        if (!file_exists($file)) {
            Log::error('Error sublimation summary does not exist for batch ' . $batch_number);
            return false;
        }

        return $file;
    }

    public function reprintBulk(Request $request)
    {
        set_time_limit(0);

        $batch_numbers = $request->get('batch_number');

        // $success = array();
        // $error = array();

        $success = 'Print Sublimation for batch ';
        $error = 'Error for batch ';

        if (is_array($batch_numbers)) {

            foreach ($batch_numbers as $batch_number) {

                $msg = $this->reprint($batch_number, $request->get('directory'));

                if ($msg == 'success') {
                    $success = $success . $batch_number . ',';
                }

                if (substr($msg, 0, 5) == 'ERROR') {
                    $error = $error . $batch_number . ',';
                }
            }
            if ($error != 'Error for batch ') {
                return response()->json([
                    'status' => 203,
                    'message' => $error,
                ], 203);
            }
            return response()->json([
                'status' => 201,
                'message' => $success,
            ], 201);
        } else {
            return response()->json([
                'status' => 203,
                'message' => 'No Batches Selected'
            ], 203);
        }
    }

    public function reprintGraphic(Request $request)
    {
        if (!$request->has('name')) {
            Log::error('reprintGraphic: Name not Set');
            return response()->json([
                'status' => 203,
                'message' => 'Name not Set'
            ], 203);
        }

        $result = $this->reprint($request->get('name'), $request->get('directory'));

        if ($result != 'success' || !$request->has('goto')) {
            if ($result == 'success') {
                return response()->json([
                    'status' => 201,
                    'message' => "Reprint " . $request->get('name')
                ], 201);
            }
            return response()->json([
                'status' => 203,
                'message' => $result
            ], 203);
        } else {
            return response()->json([
                'status' => 206,
                'select_batch' => $request->get('name')
            ], 206);
        }
    }

    public function reprint($name, $directory = null)
    {

        if (!file_exists(public_path($this->sort_root))) {
            return 'ERROR Cannot find Graphics Directory';
        }

        $name = trim($name);

        $filename = $this->getArchiveGraphic($name);

        $batch_number = $this->getBatchNumber($name);

        $batch = Batch::with('route.stations_list')
            ->where('batch_number', $batch_number)
            ->first();

        if ($batch) {
            Batch::note($batch->batch_number, $batch->station_id, '10', 'Graphic retrieved from archive and sent to graphic directory');
            $batch->to_printer = '0';
            $batch->save();
        }

        /*                   NOTE TO SELF
         * The directory is being retrieved by the request URL,
         * so if that does not exist, it does not move the graphic to the right folder
         *
         * WHICH causes it to return a `success` and not moving on to the next stage
         */
        if ($directory != '') {
            try {
                Log::info('Reprint: ' . public_path($this->sort_root) . $directory . substr($filename, strrpos($filename, '/')));
                $this->recurseCopy($filename, public_path($this->sort_root) . $directory . '/' . substr($filename, strrpos($filename, '/')));
                return 'success';
            } catch (\Exception $e) {
                Log::error('reprintGraphic: Could not copy File from Archive - ' . $e->getMessage());
                return 'ERROR not copied from Archive';
            }
        } else {
            $batch->station_id = $batch->route->stations_list->first()->station_id;
            $batch->save();
            $this->moveNext($batch, 'graphics', true);
            return 'success';
        }
    }

    private function recurseCopy($src, $dst, $rename = 0)
    {

        if (is_dir($src)) {

            $dir = opendir($src);

            mkdir($dst);

            while (false !== ($file = readdir($dir))) {

                if (($file != '.') && ($file != '..')) {

                    if (is_dir($src . '/' . $file)) {

                        $this->recurseCopy($src . '/' . $file, $dst . '/' . $file, $rename);
                    } else {

                        try {

                            if (substr($file, -4) == '.tmp' || substr($file, -3) == '.db') {
                                unlink($src . '/' . $file);
                                continue;
                            }

                            if ($rename) {
                                $new_file = $this->uniqueFileName($dst, $file, null, 1);
                            } else {
                                $new_file = $file;
                            }

                            copy($src . '/' . $file, $dst . '/' . $new_file);
                        } catch (\Exception $e) {
                            Log::error('recurseCopy: Cannot copy file ' . $dir . ' - ' . $e->getMessage());
                        }
                    }
                }
            }
            closedir($dir);
        } else {

            if ($rename) {
                if (strrpos($dst, '/')) {
                    $file = substr($dst, strrpos($dst, '/') + 1);
                    $dir = substr($dst, 0, strlen($dst) - strlen($file));
                } else {
                    $file = $dst;
                    $dir = '/';
                }

                $new_file = $this->uniqueFileName($dir, $file, null, 1);
            } else {
                $new_file = $dst;
                $dir = '';
            }

            try {
                copy($src, $dir . $new_file);
            } catch (\Exception $e) {
                Log::error('recurseCopy: Cannot copy file ' . $dir . ' - ' . $e->getMessage());
            }
        }
    }
    public function uniqueFilename($dir, $filename, $suffix = '-COPY', $batch_rename = 0)
    {

        if (strpos($filename, '.')) {
            $file = substr($filename, 0, strpos($filename, '.'));
            $ext = substr($filename, strpos($filename, '.'));
        } else {
            $file = $filename;
            $ext = '';
        }

        if ($batch_rename) {
            $batch = $this->getBatchNumber($file);
            if ($batch) {
                $file = $batch;
            }
        } else if (strpos($filename, $suffix)) {
            $file = substr($filename, 0, strpos($filename, $suffix));
        }

        try {
            $num = count(glob($dir . $file . '*'));

            if ($num > 0) {

                return $file . $suffix . '-' . $num . $ext;
            } else {

                return $file . $ext;
            }
        } catch (\Exception $e) {
            Log::error('uniqueFilename: Error creating name ' . $filename . ' - ' . $e->getMessage());
            return false;
        }
    }

    public function uploadFile(Request $request)
    {

        if ($request->has('item_id') && $request->has('batch_number')) {
            $request->has('batch_number') ? $select_batch = $request->get('batch_number') : $select_batch = null;
            $request->has('item_id') ? $item_id = $request->get('item_id') : $item_id = null;
            $batche = Batch::with('items', 'route.stations_list')
                ->where('batch_number', $select_batch)
                ->get();
            foreach ($batche as $batch) {
                foreach ($batch->items as $items) {
                    if ($items->id == $item_id) {
                        if (empty($items->tracking_number)) {
                            $thumb = '/assets/images/template_thumbs/' . $items->order_id . "-" . $items->id . '.jpg';
                            $items->tracking_number = NULL;
                            $items->item_status = 1;
                            $items->reached_shipping_station = 0;
                            $items->item_thumb = $this->domain . $thumb;
                            $items->save();

                            $batch->status = 2;
                            $batch->section_id = 6;
                            $batch->station_id = 92;
                            $batch->prev_station_id = $batch->route->stations_list->first()->station_id;
                            $batch->export_count = 1;
                            $batch->csv_found = 0;
                            $batch->graphic_found = 1;
                            $batch->to_printer = 0;
                            $batch->to_printer_date = null;
                            $batch->archived = 1;
                            $batch->save();

                            $filename = $batch->batch_number . '.' . $request->file('upload_file')->getClientOriginalExtension();
                            $filename = $this->uniqueFilename($this->sort_root . "archive/", $filename);

                            if (move_uploaded_file($request->file('upload_file'), $_SERVER['DOCUMENT_ROOT'] . $this->sort_root . "archive/" . $filename)) {
                                try {
                                    ImageHelper::createThumb(public_path() . $this->sort_root . "archive/" . $filename, 0, public_path() . $thumb, 350);
                                } catch (\Exception $e) {
                                    Log::error('Batch uploadFile createThumb: ' . $e->getMessage());
                                }

                                Batch::note($batch->batch_number, $batch->station_id, '111', 'Graphic Uploaded to Main');
                                return response()->json([
                                    'status' => 201,
                                    'message' => 'Graphic Uploaded for Batch ' . $batch->batch_number
                                ], 201);
                            }
                        }
                    }
                }
            }
            return response()->json([
                'status' => 203,
                'message' => 'Batch number required..'
            ], 203);
        }
        if (!$request->has('batch_number')) {
            return response()->json([
                'status' => 203,
                'message' => 'Batch number required..'
            ], 203);
        }

        $batch = Batch::where('batch_number', $request->get('batch_number'))->first();

        if (!$batch) {
            return response()->json([
                'status' => 203,
                'message' => 'Batch not found'
            ], 203);
        }

        $filename = $batch->batch_number . '.' . $request->file('upload_file')->getClientOriginalExtension();

        $filename = $this->uniqueFilename($this->main_dir, $filename);

        if (move_uploaded_file($request->file('upload_file'), $this->main_dir . $filename)) {
            Batch::note($batch->batch_number, $batch->station_id, '11', 'Graphic Uploaded to Main');
            return response()->json([
                'status' => 201,
                'message' => 'Graphic Uploaded for Batch ' . $batch->batch_number
            ], 201);
        }

        Batch::note($batch->batch_number, $batch->station_id, '11', 'Error uploading graphic');
        return response()->json([
            'status' => 203,
            'message' => 'Error uploading graphic for Batch ' . $batch->batch_number
        ], 203);
    }

    public function uploadFileUsingLink(Request $request)
    {

        $this->uploadFileFromLink($request);

        //        return response()->json(
        //            [
        //                "Status" => true,
        //                "Message" => "Successful",
        //                "Data" => $request->all(),
        //                "Link_Decoded" => urldecode($request->get("link"))
        //            ]
        //        );
        return redirect()->back()->withInput()->withSuccess("Successfully fetched graphic from Zakeke");
    }


    public function sortFiles(Request $request)
    {
//        $helper = new Helper();
        if(!file_exists($this->sort_root)) {
            Log::error('Sortfiles: Cannot find Graphics Directory');
            return;
        }

        $error_files = $this->findErrorFiles();
//        $helper->jdbg("error_files", $error_files);
        $manual_files = $this->getManual('list');
//        $helper->jdbg("manual_files", $manual_files);
        $dir_list = array_diff(scandir($this->main_dir), array('..', '.', 'lock'));
//        $helper->jdbg("dir_list", $dir_list);

        $id = rand(1000, 9999);


        /* TEMP REMOVED
        if ($request->has("location")) {
            Log::info("------- SORT WAS CALLED BY: " . $request->get("location"));
        }
        */

        if(count($dir_list) > 0) {
//          echo "<pre>";
//              print_r($dir_list);
//          echo "</pre>";
            //    Log::info('STARTING SORTFILES ' . $id);
        } else {
            //  Log::info("Nothing to sort from " . $this->main_dir);
            //   echo "Nothing to sort from " . $this->main_dir;
        }


        foreach ($dir_list as $dir) {

            $result = shell_exec("lsof | grep " . $this->main_dir . $dir . "*");

            if($result != null || substr($dir, -5) == '.lock' || !file_exists($this->main_dir . $dir) ||
                !is_writable($this->main_dir . $dir) || file_exists($this->main_dir . $dir . "/lock") ||
                file_exists($this->main_dir . $dir . '.lock')) {
                Log::info('1.Sortfiles Locked -------sortFiles ' . $id . ': ' . $this->main_dir . $dir);
                continue;
            }

            touch($this->main_dir . $dir . '.lock');

            $batch_number = $this->getBatchNumber($dir);

            if(isset($error_files[$batch_number])) {
                foreach ($error_files[$batch_number] as $error_file) {
                    Log::info('sortFiles ' . $id . ':  Remove Error File ' . $error_file);
                    if(!$this->removeFile($error_file)) {
                        unlink($this->main_dir . $dir . '.lock');
                        continue;
                    }
                }
            }

            if(isset($manual_files[$batch_number])) {
                Log::info('sortFiles ' . $id . ':  Remove Manual File ' . $manual_files[$batch_number]);
                if(!$this->removeFile($manual_files[$batch_number])) {
                    unlink($this->main_dir . $dir . '.lock');
                    continue;
                }
            }
            $batch = Batch::with('route')
                ->where('batch_number', $batch_number)
                ->first();

            try {
                $to_file = $this->uniqueFilename(self::$archive, $dir);

                if(file_exists(self::$archive . $to_file)) {
                    Log::error('sortFiles ' . $id . ': File already in archive ' . self::$archive . $to_file);
                }
                $this->recurseAppend($this->main_dir . $dir, self::$archive . $to_file);

                if($batch) {
                    Batch::note($batch->batch_number, $batch->station_id, '6', 'Moving to archived');


                    $batch->archived = '1';
                    $batch->save();
                    Batch::note($batch->batch_number, $batch->station_id, '6', 'Graphics Archived');
                }
            } catch (\Exception $e) {
                Log::error('sortFiles ' . $id . ': Error archiving file ' . $to_file . ' - ' . $e->getMessage());
                if($batch) {
                    $batch->archived = '2';
                    $batch->save();
                    Batch::note($batch->batch_number, $batch->station_id, '6', 'Graphics Archiving Error');
                }
                continue;
            }

            if($batch && $batch->route) {
                $graphic_dir = $batch->route->graphic_dir . '/';
            } else {
                $graphic_dir = 'MISC/';
                Log::error('sortFiles ' . $id . ':  Graphic Directory Not Set ' . $dir . "in route name: ");
                if($batch) {
                    Batch::note($batch->batch_number, $batch->station_id, '6', 'Graphic Directory Not Set');
                }
            }


            if(!file_exists($this->sort_root . $graphic_dir)) {
                mkdir($this->sort_root . $graphic_dir);
            }

            $result = shell_exec("lsof | grep " . $this->sort_root . $graphic_dir . $dir . "*");

            if($result != null) {
                Log::info('sortFiles ' . $id . ':  Destination File in use ' . $graphic_dir . $dir);
                if($batch) {
                    Batch::note($batch->batch_number, $batch->station_id, '6',
                        'Error moving file to graphic directory - Destination File in use');
                }
                continue;
            }

            try {

                //$this->removeFile($this->sort_root . $graphic_dir . $dir);
                //$moved = @rename($this->main_dir . $dir, $this->sort_root . $graphic_dir . $dir);

                if($graphic_dir != '/') {
                    $this->recurseAppend($this->main_dir . $dir, $this->sort_root . $graphic_dir . $dir);
                }
                $this->removeFile($this->main_dir . $dir);

                if($batch) {
                    $batch->graphic_found = '1';
                    $batch->to_printer = '0';
                    $batch->save();

                    Batch::note($batch->batch_number, $batch->station_id, '6',
                        "Graphic $dir moved to $graphic_dir directory");
                    $msg = $this->moveNext($batch, 'graphics');
                }

            } catch (\Exception $e) {
                Log::error('sortFiles ' . $id . ':  Error moving file to graphic directory ' . $dir . ' ' . $e->getMessage());

                if($batch) {
                    $batch->graphic_found = '2';
                    $batch->save();

                    Batch::note($batch->batch_number, $batch->station_id, '6',
                        'Error moving file to graphic directory');
                }
                continue;
            }

            if($batch) {
                Batch::note($batch->batch_number, $batch->station_id, '6', 'Moved to next station');

                try {
                    Log::info("2.Sortfiles " . $batch->batch_number . ' Now -------sortFiles ' . $id . ': ' . $this->main_dir . $dir);

                    if($msg['error'] != '') {
                        Log::info('sortFiles ' . $id . ': ' . $msg['error'] . ' - ' . $dir);
                        Batch::note($batch->batch_number, $batch->station_id, '6', 'Graphics Sort - ' . $msg['error']);
                    }

                } catch (\Exception $e) {
                    Log::error('sortFiles ' . $id . ': Error moving batch ' . $dir . ' - ' . $e->getMessage());
                    Batch::note($batch->batch_number, $batch->station_id, '6',
                        'Exception moving Batch - Graphics Sort');
                }
            } else {
                Log::error('sortFiles ' . $id . ': Batch not found ' . $dir);
            }

            try {
                unlink($this->main_dir . $dir . '.lock');
            } catch (\Exception $e) {
                Log::error('sortFiles ' . $id . ': Lock could not be unlinked ' . $dir);
            }
        }

        if(count($dir_list) > 0) {
            Log::info('ENDING SORTFILES ' . $id);
        }

        Design::updateGraphicInfo();

        // FileHelper::removeEmptySubFolders($this->sub_dir);

//      return ;
    }


    public function downloadSure3d()
    {
        try {
            $s = new Sure3d();
            $s->download();
        } catch (\Exception $e) {
            Log::error('downloadSure3d: ' . $e->getMessage());
        }
        log::info('downloadSure3d: Sure3d Download Complete');
        return;
    }

    // TODO Remove unique file saving

    public function autoPrint()
    {
        set_time_limit(0);

        $w = new Wasatch;
        $queues = $w->getQueues();

        $config = Printer::configurations('A');

        foreach ($config as $number => $station) {
            if(isset($queues['PRINTER_' . $number]) && count($queues['PRINTER_' . $number]['STAGED_XML']) > 6) {
                unset($config[$number]);
            }
        }

        if(count($config) < 1) {
            return;
        }

        if(!file_exists($this->sort_root)) {
            Log::error('AutoPrint: Sublimation Directory Not Found');
            return;
        }

        $batches = Batch::with('route')
            ->join('stations', 'batches.station_id', '=', 'stations.id')
            ->where('batches.section_id', 6)
            ->searchStatus('active')
            ->where('stations.type', 'G')
            ->where('stations.id', 92)
            ->where('graphic_found', '1')
            ->where('to_printer', '0')
            // ->where('min_order_date', '>', $from_date)
            // ->where('min_order_date', '<', $to_date)
            ->whereIn('production_station_id', $config)
            ->select('batch_number', 'batch_route_id', 'min_order_date', 'production_station_id')
            ->orderBy('min_order_date')
            ->get();

        $counts = array();

        foreach ($config as $number => $station) {
            $counts[$station] = 0;
        }

        $stations = array_flip($config);


        foreach ($batches as $batch) {

            if($counts[$batch->production_station_id] < 10) {

                $notInQueue = $w->notInQueue($batch->batch_number);

                if($notInQueue != '1') {
                    continue;
                }

                if(strpos(strtolower($batch->route->csv_extension), 'soft')) {
                    $type = 'SOFT-';
                } elseif(strpos(strtolower($batch->route->csv_extension), 'hard')) {
                    $type = 'HARD-';
                } else {
                    continue;
                }

                $file = $this->getArchiveGraphic($batch->batch_number);

                $printer = $type . $stations[$batch->production_station_id];

                $result = $this->printSubFile($file, $printer, $batch->batch_number);

                if($result == 'success') {
                    $counts[$batch->production_station_id]++;
                }
            }

            if(array_sum($counts) == (count($counts) * 10)) {
                return;
            }

        }

        return;

    }

    public function printWasatch()
    {

        $w = new Wasatch;
        return $w->stagedXml();

    }
}
