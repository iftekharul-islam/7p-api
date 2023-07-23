<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\BatchScan;
use App\Models\Item;
use App\Models\Station;
use Illuminate\Http\Request;
use library\Helper;

class QcControlController extends Controller
{
    public function index(Request $request)
    {
        $qc_stations = Station::where('type', 'Q')
            ->get()
            ->pluck('id');

        $totals = Batch::with('section', 'station', 'store')
            ->searchStatus('active')
            ->whereIn('station_id', $qc_stations)
            // ->whereHas('store', function ($q) {
            //     $q->where('permit_users', 'like', "%" . auth()->user()->id . "%");
            // })
            ->groupBy('station_id', 'section_id')
            ->orderBy('section_id')
            ->selectRaw('section_id, station_id, COUNT(*) as count')
            ->get();

        return response()->json([
            'totals' => $totals,
        ], 200);
    }

    public function list(Request $request)
    {
        if (!$request->has('station_id')) {
            return response()->json([
                'message' => 'Station not set',
                'status' => 203
            ], 203);
        }

        $station = Station::find($request->get('station_id'));

        if (!$station || $station->type != 'Q') {
            return response()->json([
                'message' => 'Invalid Station',
                'status' => 203
            ], 203);
        }

        $batches = Batch::with('station', 'scanned_in.in_user', 'store', 'activeItems', 'first_item')
            ->withCount('items')
            ->searchStatus('active')
            ->where('station_id', $request->get('station_id'))
            ->orderBy('min_order_date', 'ASC')
            ->get();

        return response()->json([
            'batches' => $batches,
            'station' => $station,
        ], 200);
    }

    public function order(Request $request)
    {
        if ($request->has('batch_number')) {
            if (substr(trim($request->get('batch_number')), 0, 4) == 'BATC') {
                $batch_number = substr(trim($request->get('batch_number')), 4);
            } else {
                $batch_number = trim($request->get('batch_number'));
            }
            $batch = Batch::with('items', 'station')
                ->where('batch_number', 'LIKE', $batch_number)
                ->first();

            if (!isset($batch)) {
                return response()->json([
                    'message' => sprintf('Batch %s not found', $batch_number),
                    'status' => 203,
                ], 203);
            } else {

                if ($batch->status != 'active') {

                    $related = Batch::related($batch_number);

                    if ($related != false) {
                        // $this->order('batch_number' = $related->batch_number, 'user_barcode' = $request->get('user_barcode'));
                    } else {
                        return response()->json([
                            'message' => sprintf('Problem with Batch %s', $batch_number),
                            'status' => 203,
                        ], 203);
                    }
                }
                $user_id = auth()->user()->id;

                if ($batch->station->type == 'P') {
                    $graphicsController = new GraphicsController;
                    $graphicsController->moveNext($batch_number, 'qc');
                    Batch::note($batch_number, $batch->station_id, '1', 'Special Move to QC');
                    $batch = Batch::with('items', 'station')
                        ->where('batch_number', 'LIKE', $batch_number)
                        ->first();
                }

                if ($batch->station->type != 'Q') {
                    return response()->json([
                        'message' => sprintf('Batch %s not in QC station', $batch_number),
                        'status' => 203,
                    ], 203);
                }
            }
        } else {
            return response()->json([
                'message' => 'Batch not entered',
                'status' => 203,
            ], 203);
        }

        $scan = new BatchScan();
        $scan->batch_number = $batch->batch_number;
        $scan->station_id = $batch->station_id;
        $scan->in_user_id = $user_id;
        $scan->in_date = date("Y-m-d H:i:s");
        $scan->save();

        $batch_number = $batch->batch_number;
        $id = $batch->id;
        $reminder = $request->get('reminder');

        $label = null;

        $label_order = null;

        if ($request->has('batch_number')) {

            $qc_stations = Station::where('type', 'Q')
                ->get()
                ->pluck('id');

            $batch = Batch::with('items.order.customer', 'items.wap_item.bin', 'prev_station', 'station', 'scanned_in.in_user')
                ->searchStatus('qc_view')
                ->whereIn('station_id', $qc_stations)
                ->where('batch_number', $batch_number)
                ->where('batches.id', $id)
                ->first();

            if (!$batch) {
                return response()->json([
                    'message' => sprintf('Batch %s not found', $batch_number),
                    'status' => 203,
                ], 203);
            }

            if (!$batch->scanned_in) {
                return response()->json([
                    'message' => sprintf('Batch %s not Scanned Into QC', $batch_number),
                    'status' => 203,
                ], 203);
            }

            if (isset($batch->items)) {
                $complete = Item::searchStatus('production')
                    ->where('batch_number', $batch_number)
                    ->groupBy('batch_number')
                    ->where('is_deleted', '0')
                    ->count();

                if ($complete == 0) {
                    $this->scanOut($batch->batch_number);

                    if ($batch->status != 'empty' && $batch->status != 'complete') {
                        $batch->status = 'complete';
                        $batch->save();
                    }
                }
            }

            $options = array();

            foreach ($batch->items as $item) {
                $options[$item->id] = Helper::optionTransformer($item->item_option, 0, 1, 1, 0, 0, '<br>');
            }
        } else {
            return response()->json([
                'message' => sprintf('No Batch Number Provided'),
                'status' => 203,
            ], 203);
        }

        $order_ids = array_unique($batch->items->pluck('order_5p')->toArray());

        if ($batch->status == 'active' && count($order_ids) == 1) {
            return redirect()->action('QcController@showOrder', ['batch_number' => $batch_number, 'id' => $id, 'order_5p' => $batch->items->first()->order_5p]);
        } else {
            return view('quality_control.batch', compact('id', 'batch', 'batch_number', 'options', 'label', 'label_order', 'user', 'reminder'));
        }
    }
}
