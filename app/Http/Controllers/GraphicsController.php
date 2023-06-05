<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Design;
use App\Models\Item;
use App\Models\Rejection;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Ship\Wasatch;

class GraphicsController extends Controller
{
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

        if ($tab == 'summary') {
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
                ->where('batches.status', 2)
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

            $total = $items->sum('items_count') + $rejects->sum('items_count') + $unbatched ? $unbatched->items_count : 0;
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

        $manual = $this->getManual();
        $count['manual'] = count($manual);

        if ($tab == 'exported') {
            $exported = $this->exported($manual->pluck('batch_number')->all());
            $count['exported'] = count($exported);
        } else {
            $count['exported'] = $this->exported($manual->pluck('batch_number')->all(), 'count');
        }

        if ($tab == 'error') {
            $error_list = $this->graphicErrors();
            $count['error'] = count($error_list);
        } else {
            $count['error'] = $this->graphicErrors('count');
        }

        // $found = $this->graphicFound();

        $sections = Section::get()->pluck('section_name', 'id');

        return response()->json([
            'status' => 200,
            'message' => 'Success',
            'to_export' => $to_export,
            'exported' => $exported,
            'error_list' => $error_list,
            'manual' => $manual,
            // 'found' => $found,
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

            $to_printer = Batch::with('itemsCount', 'first_item')
                ->join('stations', 'batches.station_id', '=', 'stations.id')
                ->whereHas('store', function ($q) {
                    $q->where('permit_users', 'like', "%" . auth()->user()->id . "%");
                })
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
}
