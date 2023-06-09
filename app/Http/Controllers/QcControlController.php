<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\BatchScan;
use App\Models\Station;
use Illuminate\Http\Request;

class QcControlController extends Controller
{
    public function index(Request $request)
    {
        $qc_stations = Station::where('type', 'Q')
            ->get()
            ->pluck('id');

        $totals = Batch::with('section', 'station')
            ->searchStatus('active')
            ->whereIn('station_id', $qc_stations)
            ->whereHas('store', function ($q) {
                $q->where('permit_users', 'like', "%" . auth()->user()->id . "%");
            })
            ->groupBy('station_id', 'section_id')
            ->orderBy('section_id')
            ->selectRaw('section_id, station_id, COUNT(*) as count')
            ->get();

        return response()->json($totals, 200);
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

            if (count($batch) == 0) {

                return redirect()->action('QcController@index')->withErrors(['error' => sprintf('Batch %s not found', $batch_number)]);
            } else {

                if ($batch->status != 'active') {

                    $related = Batch::related($batch_number);

                    if ($related != false) {
                        return redirect()->action('QcController@scanIn', ['batch_number' => $related->batch_number, 'user_barcode' => $request->get('user_barcode')]);
                    } else {
                        return redirect()->action('QcController@index')->withErrors(['error' => sprintf('Problem with Batch %s', $batch_number)]);
                    }
                }
                $user_id = auth()->user()->id;

                if ($batch->station->type == 'P') {
                    //                dd($batch->station->type, $user_id);
                    $graphicsController = new GraphicsController;
                    $graphicsController->moveNext($batch_number, 'qc');
                    Batch::note($batch_number, $batch->station_id, '1', 'Special Move to QC');
                    $batch = Batch::with('items', 'station')
                        ->where('batch_number', 'LIKE', $batch_number)
                        ->first();
                }

                if ($batch->station->type != 'Q') {
                    return redirect()->action('QcController@index')->withErrors(['error' => sprintf('Batch %s not in QC station', $batch_number)]);
                }
            }
        } else {
            return redirect()->action('QcController@index')->withErrors(['error' => sprintf('Batch not entered')]);
        }

        $scan = new BatchScan();
        $scan->batch_number = $batch->batch_number;
        $scan->station_id = $batch->station_id;
        $scan->in_user_id = $user_id;
        $scan->in_date = date("Y-m-d H:i:s");
        $scan->save();

        return redirect()->action('QcController@showBatch', ['id' => $batch->id, 'batch_number' => $batch->batch_number]);
    }
}
