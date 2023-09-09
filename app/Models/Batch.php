<?php

namespace App\Models;

// use App\StationLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Ship\CSV;
use Ship\Sure3d;

class Batch extends Model
{
    protected $table = 'batches';


    public static function boot()
    {
        parent::boot();

        static::updating(function ($model) {
            if (isset($model->station_id) && $model->getOriginal('station_id')) {

                if ($model->station_id != $model->getOriginal('station_id')) {
                    $station_log = new StationLog();
                    $station_log->batch_number = $model->batch_number;
                    $station_log->station_id = $model->station_id;
                    $station_log->prev_station_id = $model->getOriginal('station_id');
                    if (auth()->user()) {
                        $station_log->user_id = Auth::user()->id;
                    } else {
                        $station_log->user_id = 87;
                    }
                    $station_log->save();
                }
            }
        });
    }

    // scopePermitUser
    public function scopePermitUser($query)
    {
        return $query->where('user_id', auth()->user()->id);
    }

    public function setStationIDAttribute($value)
    {

        $station = Station::find($value);
        if (isset($this->attributes['station_id'])) {
            $this->note($this->attributes['batch_number'], $this->attributes['station_id'], '4', "Station Moved to $station->station_name");
        } else {
            $this->note($this->attributes['batch_number'], 0, '4', "Station Moved to $station->station_name");
        }


        $this->attributes['station_id'] = $value;
        $this->attributes['change_date'] = date("Y-m-d H:i:s");
    }

    public function setBatchRouteIdAttribute($value)
    {
        $this->attributes['batch_route_id'] = $value;

        $route = Station::join('batch_route_station', 'batch_route_station.station_id', '=', 'stations.id')
            ->selectRaw('stations.id as production_station, stations.section as section')
            ->where('batch_route_station.batch_route_id', $value)
            ->where('stations.type', 'P')
            ->orderBy('batch_route_station.id', 'ASC')
            ->first();

        if (isset($route->production_station) && $route->production_station != NULL) {
            $this->attributes['production_station_id'] = $route->production_station;
        } else {
            Log::error('Batch route production station not set. Route : ' . $value);

            $route = Station::join('batch_route_station', 'batch_route_station.station_id', '=', 'stations.id')
                ->selectRaw('stations.id as qc_station, stations.section as section')
                ->where('batch_route_station.batch_route_id', $value)
                ->where('stations.type', 'Q')
                ->orderBy('batch_route_station.id', 'ASC')
                ->first();

            if (isset($route->qc_station) && $route->qc_station != NULL) {
                $this->attributes['production_station_id'] = $route->qc_station;
                $this->attributes['section_id'] = $route->section;
            } else {
                Log::error('Batch route QC station not set. Route : ' . $value);
                $this->attributes['production_station_id'] = '0';
            }
        }

        if (isset($route->section) && $route->section != NULL && $route->section != 0) {
            $this->attributes['section_id'] = $route->section;
        } else {
            Log::error('Batch section not set. Route : ' . $value);
        }
    }

    public function items()
    {
        return $this->hasMany(Item::class, 'batch_number', 'batch_number')
            ->selectRaw('*, (Select count(*) from items as g where g.order_5p = items.order_5p and g.batch_number = items.batch_number group by order_5p) as count')
            ->where('is_deleted', '0')
            ->orderBy('count', 'ASC')
            ->orderBy('order_5p', 'ASC')
            ->orderBy('child_sku', 'ASC');
    }

    public function pending_items()
    {
        return $this->hasMany(Item::class, 'batch_number', 'batch_number')
            ->where('is_deleted', '0')
            ->searchStatus('pending');
    }

    public function store()
    {
        return $this->hasOne(Store::class, 'store_id', 'store_id');
    }

    public function skus()
    {
        $this->hasMany(Item::class, 'batch_number', 'batch_number')
            ->selectRaw('child_sku, item_description, item_thumb, count(item_quantity) as count')
            ->groupBy('child_sku')
            ->groupBy('item_description')
            ->groupBy('item_thumb');
    }


    public function first_item()
    {
        return $this->hasOne(Item::class, 'batch_number', 'batch_number')
            ->where('is_deleted', '0')
            ->orderBy('id', 'ASC');
    }


    public function route()
    {
        return $this->belongsTo(BatchRoute::class, 'batch_route_id', 'id');
    }

    public function route_list()
    {
        return $this->belongsTo(BatchRoute::class, 'batch_route_id', 'id')
            ->select(DB::raw('CONCAT(batch_routes.batch_route_name, " => ", batch_routes.batch_code) AS route', 'id'));
    }


    public function station()
    {
        return $this->belongsTo(Station::class, 'station_id', 'id');
    }


    public function prev_station()
    {
        return $this->belongsTo(Station::class, 'prev_station_id', 'id');
    }


    public function production_station()
    {
        return $this->belongsTo(Station::class, 'production_station_id', 'id');
    }


    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id', 'id');
    }


    public function summary_user()
    {
        return $this->belongsTo(User::class, 'summary_user_id', 'id');
    }


    public function picking_report()
    {
        return $this->belongsTo(PickingReport::class, 'picking_report_id');
    }

    public function scanned_in()
    {
        return $this->hasOne(BatchScan::class, 'batch_number', 'batch_number')
            ->whereNull('out_date')
            ->orderBy('batch_scans.created_at', 'DESC');
    }

    public static function lastScan($batch_number)
    {
        $last_move = StationLog::where('batch_number', $batch_number)
            ->join('users', 'station_logs.user_id', '=', 'users.id')
            ->selectRaw('batch_number,  station_logs.created_at as date, username, station_logs.created_at')
            ->orderBy('station_logs.created_at', 'DESC')
            ->limit(1)
            ->get();
        $last_in_scan = BatchScan::where('batch_number', $batch_number)
            ->join('users', 'batch_scans.in_user_id', '=', 'users.id')
            ->whereNull('batch_scans.out_date')
            ->selectRaw('batch_number,  batch_scans.in_date as date, username, batch_scans.created_at')
            ->orderBy('batch_scans.created_at', 'DESC')
            ->limit(1)
            ->get();
        $last_out_scan = BatchScan::where('batch_number', $batch_number)
            ->join('users', 'batch_scans.out_user_id', '=', 'users.id')
            ->whereNotNull('batch_scans.out_date')
            ->selectRaw('batch_number,  batch_scans.out_date as date, username, batch_scans.created_at')
            ->orderBy('batch_scans.created_at', 'DESC')
            ->limit(1)
            ->get();

        $result = array();

        if (count($last_in_scan) > 0) {
            $result[$last_in_scan->first()->date] = $last_in_scan->first()->toArray();
        }

        if (count($last_out_scan) > 0) {
            $result[$last_out_scan->first()->date] = $last_out_scan->first()->toArray();
        }

        if (count($last_move) > 0) {
            $result[$last_move->first()->date] = $last_move->first()->toArray();
        }

        arsort($result);

        return reset($result);
    }

    public function scans()
    {
        return $this->hasMany(BatchScan::class, 'batch_number', 'batch_number');
    }

    public static function isFinished($batch_number)
    {
        if ($batch_number == '0') {
            return false;
        }

        $batch = Batch::with('items', 'pending_items')
            ->where('batch_number', $batch_number)
            ->first();

        if (!$batch) {
            return false;
        }

        if (!$batch->items || count($batch->items) == 0) {
            if ($batch->status != 'empty') {
                $batch->status = 'empty';
                $batch->save();
            }
            return true;
        } elseif (!$batch->pending_items || count($batch->pending_items) == 0) {
            if ($batch->status != 'complete') {
                $batch->status = 'complete';
                $batch->save();
            }
            return true;
        }

        return false;
    }


    public static function getStatusList()
    {
        $statuses = array();
        $statuses['all'] = 'Select Status';
        $statuses['active'] = 'Active';
        $statuses['complete'] = 'Completed';
        $statuses['held'] = 'Held';
        $statuses['back order'] = 'Back Order';
        return $statuses;
    }

    public function getGraphicFoundAttribute($value)
    {
        if ($value == '0') {
            return 'Not Found';
        } elseif ($value == '1') {
            return 'Found';
        } elseif ($value == '2') {
            return 'File Move Error';
        } elseif ($value == '3') {
            return 'Graphic Directory Not Found';
        } elseif ($value == '4') {
            return 'Pendant Error';
        } elseif ($value == '5') {
            return 'Graphic Found - Error Moving Batch';
        } elseif ($value == '6') {
            return 'Not Exported - Template / Settings Missing';
        } elseif ($value == '7') {
            return 'Image Size Error';
        } else {
            return 'Unknown';
        }
    }


    public function getStatusAttribute($value)
    {
        if ($value == 1) {
            return 'complete';
        } elseif ($value == 2) {
            return 'active';
        } elseif ($value == 3) {
            return 'held';
        } elseif ($value == 4) {
            return 'back order';
        } elseif ($value == 8) {
            return 'empty';
        } elseif ($value == 0) {
            return 'default';
        } else {
            return 'Unknown';
        }
    }


    public function setStatusAttribute($value)
    {
        if (isset($this->attributes['status'])) {

            if ($this->attributes['status'] == 1) {
                $status = 'complete';
            } elseif ($this->attributes['status'] == 2) {
                $status =  'active';
            } elseif ($this->attributes['status'] == 3) {
                $status =  'held';
            } elseif ($this->attributes['status'] == 4) {
                $status =  'back order';
            } elseif ($this->attributes['status'] == 8) {
                $status =  'empty';
            } elseif ($this->attributes['status'] == 0) {
                $status =  'default';
            } else {
                $status =  'Unknown';
            }

            $text = 'Batch Status changed from ' . $status . ' to ' . $value;
        } else {
            $text = 'Batch Status changed to ' . $value;
        }

        if (!isset($status) || $status != $value) {
            $this->attributes['change_date'] = date("Y-m-d H:i:s");
        }

        $this->note($this->batch_number, $this->station_id, '0', $text);

        $value = strtolower($value);

        if ($value == 'complete' || $value == 'completed' || $value == '1') {
            $this->attributes['status'] = 1;
        } elseif ($value == 'active' || $value == '2') {
            $this->attributes['status'] = 2;
        } elseif ($value == 'held' || $value == '3') {
            $this->attributes['status'] = 3;
        } elseif ($value == 'back order' || $value == 'backorder' || $value == '4') {
            $this->attributes['status'] = 4;
        } elseif ($value == 'empty' || $value == '8') {
            $this->attributes['status'] = 8;
        } else {
            $this->attributes['status'] = 9;
        }
    }


    public function scopeSearchStatus($query, $status, $batch_num = NULL)
    {
        $status = strtolower($status);

        if ($batch_num) {
            return;
        }

        if (!$status || $status == 'all') {
            return $query->whereNotIn('status', [1, 8]); // not completed
        }

        if ($status == 'active') {
            return $query->where('status', 2);
        }

        if ($status == 'not active') {
            return $query->whereNotIn('status', [1, 2, 8]); //not active, empty or complete
        }

        if ($status == 'complete') {
            return $query->where('status', 1);
        }

        if ($status == 'held') {
            return $query->where('status', 3);
        }

        if ($status == 'back order' || $status == 'backorder') {
            return $query->where('status', 4);
        }

        if ($status == 'movable') {
            return $query->whereIn('status', [4, 2]);
        }

        if ($status == 'qc_view') {
            return $query->whereIn('status', [1, 2, 8]);
        }

        // if($status == 'shipped'){
        //   #return $query->where('item_order_status', '=', $status);
        //   return $query->whereNotNull('tracking_number');
        // }
        // 
        // if($status == 'not_shipped'){
        //   return $query->whereNull('tracking_number')
        //                 ->whereHas('order', function($q) {
        //                     return $q->where('orders.order_status', '!=',  '8')
        //                               ->where('orders.is_deleted', 0);
        //                   });
        // }
        // 
        // if($status == 'cancelled'){
        //   return $query->whereHas('order', function($q) {
        //                     return $q->where('orders.order_status', '=',  '8')
        //                               ->where('orders.is_deleted', 0);
        //                   });
        // }
        // 
        //return $query->where('status', '=', $status);
    }

    public function scopeSearchGraphic($query, $flag)
    {
        if (!isset($flag)) {
            return;
        }

        return $query->where('graphic_found', $flag);
    }

    public function scopeSearchStationType($query, $type)
    {
        if (!isset($type)) {
            return;
        }

        return $query->whereHas('station', function ($q) use ($type) {
            return $q->where('type', $type);
        });
    }

    public static function note($batch_number, $station_id, $from, $text)
    {
        $note = new BatchNote;
        $note->batch_number = $batch_number;
        $note->station_id = $station_id;
        if (auth()->user()) {
            $note->user_id = auth()->user()->id;
        } else {
            $note->user_id = 87;
        }
        $note->from = $from;
        $note->note = $text;
        $note->save();
    }

    public static function getNextStation($type, $batch_route_id, $current_station_id)
    {
        if (Station::find($current_station_id)->type == 'Q') {
            return NULL; // cannot go past QC
        }

        $next_stations = DB::select(sprintf(
            "SELECT * FROM batch_route_station 
              WHERE batch_route_id = %d and id > ( 
                  SELECT id FROM batch_route_station 
                    WHERE batch_route_id = %d AND station_id = %d)",
            $batch_route_id,
            $batch_route_id,
            $current_station_id
        ));

        if (count($next_stations)) {
            $next_station = Station::find($next_stations[0]->station_id);
        } else {
            return null;
        }

        if (count($next_stations) && $type == 'id') {

            return $next_station->id;
        } elseif (count($next_stations) && $type == 'name') {

            return $next_station->station_name;
        } elseif (count($next_stations) && $type == 'type') {

            return $next_station->type;
        } elseif (count($next_stations) && $type == 'desc') {

            return $next_station->station_name . ' - ' . $next_station->station_description;
        } elseif (count($next_stations) && $type == 'object') {

            return $next_station;
        } else {
            return null;
        }
    }

    public static function getPrevStation($batch_route_id, $current_station_id)
    {
        $prev_stations = DB::select(sprintf(
            "SELECT * FROM batch_route_station 
              WHERE batch_route_id = %d and id < ( 
                  SELECT id FROM batch_route_station 
                    WHERE batch_route_id = %d AND station_id = %d)
                    order by id DESC",
            $batch_route_id,
            $batch_route_id,
            $current_station_id
        ));

        if (count($prev_stations)) {
            return Station::find($prev_stations[0]->station_id);
        } else {
            return null;
        }
    }

    public function scopeSearchBatch($query, $batch_num)
    {
        if (!isset($batch_num)) {
            return;
        }

        $batch_num = explode(",", trim($batch_num));

        return $query->whereIn('batch_number', $batch_num);
    }


    public function scopeSearchRoute($query, $route_id)
    {
        if (!isset($route_id) || $route_id == 'all') {
            return;
        }

        return $query->where('batch_route_id', $route_id);
    }


    public function scopeSearchStation($query, $station_id)
    {
        if (!isset($station_id) || $station_id == 'all') {
            return;
        }

        return $query->where('station_id', $station_id);
    }

    public function scopeSearchStore($query, $store_id)
    {
        $stores = Store::query();
        if (isset($store_id) && count($store_id)) {
            $stores->whereIn('store_id', $store_id);
        }
        $store_ids = $stores->where('permit_users', 'like', "%" . auth()->user()->id . "%")
            ->where('is_deleted', '0')
            ->where('invisible', '0')
            ->get()
            ->pluck('store_id')
            ->toArray();

        if (is_array($store_ids)) {
            return $query->whereIn('batches.store_id', $store_ids);
        } else {
            logger('$store_id');
            return $query->where('batches.store_id', $store_ids);
        }
    }

    public function scopeSearchMinChangeDate($query, $start_date)
    {
        if (!isset($start_date)) {
            return;
        }
        $starting = sprintf("%s 00:00:00", $start_date);

        return $query->where('change_date', '>=', $starting);
    }


    public function scopeSearchMaxChangeDate($query, $end_date)
    {
        if (!isset($end_date)) {
            return;
        }
        $ending = sprintf("%s 23:59:59", $end_date);

        return $query->where('change_date', '<=', $ending);
    }


    public function scopeSearchOrderDate($query, $start_date, $end_date)
    {
        if (!isset($start_date)) {
            return;
        }
        $starting = sprintf("%s 00:00:00", $start_date);
        $ending = sprintf("%s 23:59:59", $end_date);

        return $query->whereHas('items', function ($q) use ($starting, $ending) {
            return $q->searchOrderDate($starting, $ending);
        });
    }


    public function scopeSearchProductionStation($query, $station)
    {
        if (!isset($station)) {
            return;
        }

        if (is_array($station)) {
            return $query->whereIn('production_station_id', $station);
        }

        return $query->where('production_station_id', $station);
    }

    public function scopeSearchGraphicDir($query, $dir)
    {
        if (!isset($dir)) {
            return;
        }

        return $query->whereHas('route', function ($q) use ($dir) {
            return $q->where('graphic_dir', $dir);
        });
    }

    public function scopeSearchSection($query, $id)
    {
        if (!isset($id)) {
            return;
        }

        return $query->where('section_id', $id);
    }

    public function scopeSearchPrinted($query, $printed, $print_date = null, $printed_by = null)
    {
        if (!isset($printed)) {
            return;
        } elseif ($printed == 0) {

            return $query->whereNull('summary_date')
                //->where('graphic_found','1')
                ->whereHas('station', function ($q) {
                    return $q->where('type', '!=', 'G');
                })
                ->whereHas('section', function ($q) {
                    return $q->where('summaries', '0');
                });
        } elseif ($printed == 2) {

            return $query->whereNull('summary_date')
                ->where('graphic_found', '1')
                ->whereHas('station', function ($q) {
                    return $q->where('type', 'G');
                })
                ->whereHas('section', function ($q) {
                    return $q->where('summaries', '0');
                });
        } elseif ($printed == 1 && isset($print_date) && isset($printed_by)) {

            return $query->where('summary_date', $print_date)
                ->where('summary_user_id', $printed_by);
        } elseif ($printed == 1 && isset($print_date)) {

            return $query->where('summary_date', $print_date);
        } elseif ($printed == 1) {

            return $query->whereNotNull('summary_date');
        } elseif ($printed == 3 && $print_date) {

            return $query->where(function ($q) use ($print_date) {
                $q->where('summary_date', '<', $print_date . ' 23:59:59')
                    ->orWhereNull('summary_date');
            });
        } else {

            return;
        }
    }

    public function scopeSearchType($query, $type)
    {

        if (!$type) {
            return;
        }

        if ($type == 'Reject') {
            return $query->whereRaw('substr(batches.batch_number,1,1) = "R"');
        }
    }

    public function itemsCount()
    {
        return $this->items()->count();
        // ->selectRaw('count(*) as count')
        // ->groupBy('batch_number', 'items.id', 'items.order_5p', 'items.order_id', 'items.store_id');
    }

    public function itemsCounts()
    {
        return $this->items()
            ->selectRaw('count(*) as count')
            ->groupBy('batch_number', 'items.id', 'items.order_5p', 'items.order_id', 'items.store_id');
    }


    public static function getOriginalNumber($batch_number)
    {
        if (strpos($batch_number, '-')) {
            return substr($batch_number, strpos($batch_number, '-') + 1);
        } else {
            return $batch_number;
        }
    }

    public static function related($batch_number)
    {
        $original = Batch::getOriginalNumber($batch_number);

        $related = Batch::with('first_item.order', 'route', 'station', 'itemsCount')
            ->where('batch_number', 'LIKE', '%' . $original)
            ->where('batch_number', '!=', $batch_number)
            ->searchStatus('movable')
            ->get();

        if (count($related) == 1) {
            return $related[0];
        } else {
            return false;
        }
    }

    public static function getNewNumber($old_batch, $prefix)
    {
        $old_batch = Batch::getOriginalNumber($old_batch);

        $batch_count = Batch::where('batch_number', 'LIKE', $prefix . '%' . $old_batch)->count();

        while (true) {

            $new_batch = sprintf('%s%02d-%s', $prefix, $batch_count + 1, $old_batch);

            $check = Batch::where('batch_number', $new_batch)->count();

            if ($check == 0) {
                return $new_batch;
            } else {
                $batch_count++;
            }
        }
    }

    public static function export($id, $force = 0, $format = 'CSV')
    {
        if ($format == 'CSV') {
            $savepath = '/media/RDrive/5p_batch_csv_export';
        } else if ($format == 'XLS') {
            $savepath = '/media/RDrive/5p_batch_xls_export';
        }

        if (!$id || $id == '0') {
            return ['error' => 'Batch Number not set'];
        }

        // Get list of Items from Item Table by Batch Number
        $batch = Batch::with('pending_items.parameter_option.design', 'route', 'section', 'store')
            ->where('is_deleted', '0')
            ->where('batch_number', $id)
            ->first();

        // If items not found belong to this Batch number then return to error page.
        if (!$batch) {
            return ['error' => 'Batch Not Found - ' . $id];
        }

        // if ($batch->store && $batch->store->export == false) {
        //   Batch::note($batch->batch_number, $batch->station_id, '7', 'No Export Created - Store Configuration');
        //   return ['error' => 'Batch not exportable - ' . $id];
        // }

        if (count($batch->pending_items) < 1) {
            Batch::note($batch->batch_number, $batch->station_id, '7', 'Error Exporting CSV: Batch has no Items');
            $batch->isFinished($batch->batch_number);
            return ['error' => 'Batch has no exportable Items - ' . $id];
        }

        //SURE3D
        if ($batch->items->first()->sure3d != null && $force == '0') {
            try {
                $s = new Sure3d;
                $created = $s->exportBatch($batch);

                if ($created) {
                    $batch->export_count = $batch->export_count + 1;
                    $batch->export_date = date("Y-m-d H:i:s");
                    $batch->csv_found = '0';
                    $batch->graphic_found = '0';
                    $batch->to_printer = '0';
                    $batch->save();
                    return;
                }
            } catch (\Exception $e) {
                Log::error('Batch Export : Sure3d Error Batch ' . $batch->batch_number . ' - ' . $e->getMessage());
                Batch::note($batch->batch_number, $batch->station_id, '7', 'Sure3d Error: ' . $e->getMessage());
            }
        }

        if ($force == '0') {
            foreach ($batch->pending_items as $item) {

                if ($item->parameter_option && !$item->parameter_option->design) {
                    Design::check($item->parameter_option->graphic_sku);
                }

                $ex = explode('-', $batch->route->csv_extension);

                if (
                    is_array($ex) && array_intersect($ex, ['mono', 'np', 'squ']) == [] &&
                    !empty($item->parameter_option) && strtolower($batch->route->export_dir) != 'manual' &&
                    ($item->parameter_option->design->xml == '0' || $item->parameter_option->design->template == '0')
                ) {
                    $batch->graphic_found = '6';
                    $batch->save();
                    Batch::note($batch->batch_number, $batch->station_id, '7', 'Error Exporting ' . $format . ': XML or Template missing for Graphic SKU');
                    return ['error' => 'Batch Settings / Template Not Found - ' . $id];
                }
            }

            $msg = '';
        } else {

            $msg = ' without verifying XML or template';
        }

        $template_id = $batch->route->export_template;
        if ($force == '2') {
            $export_dir = 'MANUAL';
        } else {
            $export_dir = $batch->route->export_dir;
        }
        $csv_extension = $batch->route->csv_extension;
        // Get templates information by template Id from templates table.
        $template = Template::with('exportable_options')->find($template_id);

        if (!$template) {
            Batch::note($batch->batch_number, $batch->station_id, '7', 'Error Exporting ' . $format . ': Template not found');
            Log::error('Batch Export: Template not found - Batch: ' . $id);
            return ['error' => 'Template not found - Batch: ' . $id];
        }
        // Get all list of options name from template_options by options name.
        $columns = $template->exportable_options->pluck('option_name')
            ->toArray();

        if ($savepath == null && $export_dir != null) {
            $file_path = sprintf("%s/assets/exports/batches/%s/", public_path(), $export_dir);
        } elseif ($savepath == null) {
            $file_path = sprintf("%s/assets/exports/batches/", public_path());
        } elseif ($export_dir != null) {
            $file_path = sprintf("%s/%s/", $savepath, $export_dir);
            //$file_path = sprintf("%s/", $savepath);
        } else {
            $file_path = sprintf("%s/", $savepath);
        }
        try {
            // TODO - check if is this needed permission or not
            if (!file_exists($file_path)) {
                if (!mkdir($file_path, 0755, true)) {
                    Batch::note($batch->batch_number, $batch->station_id, '7', 'Error Exporting ' . $format);
                    Log::error('Batch Export: could not create directory ' . $file_path);
                    return ['error' => 'Could not create directory ' . $file_path . '  - Batch: ' . $id];
                }
            }
        } catch (\Exception $e){
            Log::error('Batch Export: could not create directory with error: ' . $e->getMessage());
        }



        if (empty($csv_extension)) {
            $file_name = sprintf("%s.csv", $id);
        } else {
            if ($csv_extension[0] != '-') {
                $routeUrl = '<a href="' . url(sprintf("/prod_config/batch_routes#%s", $batch->route->batch_code)) . '" target="_blank">' . $batch->route->batch_code . '</a>';
                Log::error('Can not create CSV - Batch# ' . $id . ' File Extension should start with {-} go to Routes ' .  $batch->route->batch_code . ' and fix like {-' . $csv_extension . '}');
                return ['error' => 'Can not create CSV - Batch# ' . $id . ' File Extension should start with {-} go to Routes ' . $routeUrl . ' and fix like {-' . $csv_extension . '}'];
            }
            $file_name = sprintf("%s%s.csv", $id, $csv_extension);
        }

        $rows = array();

        if ($template->show_header == 1) {
            $rows[] = $columns;
        }

        set_time_limit(0);
        foreach ($batch->pending_items as $item) {

            $row = [];
            #$row[] = explode("-", $item->order_id)[2];
            $options = $item->item_option;

            if (empty($options)) {
                Batch::note($batch->batch_number, $batch->station_id, '7', 'Error Exporting CSV - option empty');
                Log::error('Batch Export: Can not create CSV - Order# ' . $item->order_id . ' Batch# ' . $id . ' option empty.');
                return ['error' => 'Can not create CSV - Order# ' . $item->order_id . ' Batch# ' . $id . ' option empty.'];
            }

            $decoded_options_s = json_decode($options, true);
            $decoded_options = [];

            if ($decoded_options_s) {
                foreach ($decoded_options_s as $key => $value) {
                    $decoded_options[trim(str_replace("_", " ", $key))] = $value;
                }
            } else {
                Batch::note($batch->batch_number, $batch->station_id, '7', 'Error Exporting CSV - option empty');
                Log::error('Batch Export: Can not create CSV - Order# ' . $item->order_id . ' Batch# ' . $id . ' option empty.');
                return ['error' => 'Can not create CSV - Order# ' . $item->order_id . ' Batch# ' . $id . ' option empty.'];
            }

            foreach ($template->exportable_options as $column) {
                $result = '';
                if (str_replace(" ", "", strtolower($column->option_name)) == "order#") { //if the value is order number
                    #$result = array_slice(explode("-", $item->order_id), -1, 1);
                    $exp = explode("-", $item->order_id); // explode the short order
                    $result = $exp[count($exp) - 1];
                    #$result = $item->order_id;
                } elseif (str_replace(" ", "", strtolower($column->option_name)) == "sku") { // if the template value is sku
                    // previous line is commented after the sku became parent sku
                    // and, graphic_sku became sku
                    //} elseif ( str_replace(" ", "", strtolower($column->option_name)) == "parentsku" ) { // if the template value is sku
                    // Jewel comment on 06292016
                    //$result = $item->item_code;
                    // as the sku exists, the next column is the graphic sku
                    // insert result to the row
                    // Jewel comment on 06292016
                    //$row[] = $result;

                    // get the graphic sku, and the result will be saving the graphic sku value
                    $result = Option::getGraphicSKU($item);
                    // this result will be inserted to the row array below

                } elseif (str_replace(" ", "", strtolower($column->option_name)) == "po#") { // if string is po/batch number
                    $result = $item->batch_number;
                } elseif (str_replace(" ", "", strtolower($column->option_name)) == "orderdate") { //if the string is order date
                    $result = substr($item->order->order_date, 0, 10);
                } elseif (str_replace(" ", "", strtolower($column->option_name)) == "itemqty") { //if the string is item quantity = Item Qty
                    $result = intval($item->item_quantity);
                } elseif (str_replace(" ", "", strtolower($column->option_name)) == "itemdescription") { //if the string is item quantity = Item Qty
                    $result = $item->item_description;
                } else {
                    $keys = explode(",", $column->value);
                    $found = false;
                    $values = [];
                    foreach ($keys as $key) {
                        $trimmed_key = implode(" ", explode(" ", trim($key)));

                        if (array_key_exists($trimmed_key, $decoded_options)) {
                            $values[] = $decoded_options[$trimmed_key];
                            $found = true;
                        }
                    }
                    if ($values) {
                        $result = implode(",", $values);
                    }
                }
                $row[] = $result;
            }

            if ($format == 'CSV') {
                if ($template->show_header != 1) {
                    while ($row[count($row) - 1] == null) {
                        unset($row[count($row) - 1]);
                    }
                }
            }

            $rows[] = $row;
        }


        if ($format == 'XLS') {

            Excel::create($file_name, function ($excel) use ($rows) {

                $excel->sheet('Export', function ($sheet) use ($rows) {
                    $sheet->fromArray($rows, null, 'A1', false, false);
                });
            })->store('xls', $file_path);
        } elseif ($format == 'CSV') {
            try {
                // $csv = Writer::createFromFileObject(new \SplFileObject($fully_specified_path, 'w+'), 'w');
                // dd($rows, $file_path, $file_name);
                $csv = new CSV;
                $csv->createFile($rows, $file_path, null, $file_name, ',', 'w');
                Log::info('exportCSV: Batch# ' . $id .  " path = " . $file_path . $file_name);
                //          dd($file_path.$file_name);
            } catch (\Exception $e) {
                Batch::note($batch->batch_number, $batch->station_id, '7', 'Error Exporting CSV - Could not open destination File');
                Log::error('Batch Export: Could not open destination File ' . $file_name);
                return ['error' => 'Could not open destination File ' . $file_name . '  - Batch: ' . $id];
            }
        }

        $batch->export_count = $batch->export_count + 1;
        $batch->export_date = date("Y-m-d H:i:s");
        $batch->csv_found = file_exists($file_path . '/' . $file_name);
        $batch->graphic_found = '0';
        $batch->to_printer = '0';
        $batch->save();

        if ($batch->export_count > 1) {
            if (auth()->user()) {
                $user = Auth::user()->username;
            } else {
                $user = 'robot';
            }
            $msg .= ' (Export Count ' . $batch->export_count . ', Exported by ' . $user . ')';
        }

        //dd("5 ", $export_dir, $id, $force, $format, $batch, $file_path, $file_name, $rows, $msg);

        Batch::note($batch->batch_number, $batch->station_id, '7', 'Exported ' . $format . ' ' . $msg);

        return ['success' => 'Batch ' . $id . ' exported' . $msg];
    }



    public static function readyToExport()
    {

        $batches = Batch::with('pending_items.parameter_option')
            ->whereNull('export_date')
            ->whereNotIn('status', [1, 8])
            ->where('export_ready', '0')
            ->get();

        $templates = Batch::getTemplates();
        $stylenames = Batch::getXmlSettings();

        foreach ($batches as $batch) {
            foreach ($batch->pending_items  as $item) {
                $graphic_sku = $item->parameter_option->graphic_sku;

                if (
                    in_array($item->parameter_option->graphic_sku, $stylenames) &&
                    in_array($item->parameter_option->graphic_sku . '.ai', $templates)
                ) {
                    $batch->export_ready = '1';
                } else {
                    $batch->export_ready = '0';
                }
            }

            $batch->save();
        }
    }

    public function outputArray()
    {
        return [
            Batch::class,
            $this->id,
            url(sprintf('batches/details/%s', $this->batch_number)),
            'Batch: ' . $this->batch_number,
            $this->status,
            null
        ];
    }
    public static function getBatchWitRoute($batch_number)
    {
        return Batch::with('route')
            ->where('is_deleted', 0)
            ->where('batch_number', $batch_number)
            ->first();
    }

    /**
     * Get all of the comments for the Batch
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function activeItems()
    {
        return $this->hasMany(Item::class, 'batch_number', 'batch_number')->where('item_status', '1');
    }
}
