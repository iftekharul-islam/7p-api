<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Item;
use App\Models\Order;
use App\Models\Rejection;
use App\Models\Ship;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use library\Helper;

class RejectionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $batch_array = array();
        $store_ids = Store::where('is_deleted', '0')
            // ->where('permit_users', 'like', "%" . auth()->user()->id . "%")
            ->where('invisible', '0')
            ->get()
            ->pluck('store_id')
            ->toArray();

        $total_items = null;
        $batch_array = [];
        $summary = null;
        $label = null;

        if ($request->all() == []) {

            $summary = Item::join('rejections', 'items.id', '=', 'rejections.item_id')
                ->where('items.is_deleted', '0')
                ->searchStatus('rejected')
                ->where('graphic_status', '!=', 4)
                ->whereIn('store_id', $store_ids)
                ->where('rejections.complete', '0')
                ->selectRaw('rejections.graphic_status, rejections.rejection_reason, COUNT(items.id) as count')
                ->groupBy('rejections.graphic_status')
                ->groupBy('rejections.rejection_reason')
                ->orderBy('rejections.graphic_status')
                ->orderBy('rejections.rejection_reason')
                ->get();
        } else {
            if ($request->has('label')) {
                $label = $request->get('label');
            } else {
                $label = null;
            }

            $items = Item::with(
                'rejection.rejection_reason_info',
                'rejection.user',
                'rejection.from_station',
                'rejections',
                'order',
                'batch'
            )
                ->where('is_deleted', '0')
                ->whereIn('store_id', $store_ids)
                ->searchStatus('rejected')
                ->searchBatch(trim($request->get('batch_number')))
                ->searchGraphicStatus($request->get('graphic_status'))
                ->searchSection($request->get('section'))
                ->searchRejectReason($request->get('reason'))
                ->orderBy('batch_number', 'ASC')
                ->get();

            $total_items = count($items);

            foreach ($items as $item) {

                if (!array_key_exists($item->batch_number, $batch_array)) {
                    $batch_array[$item->batch_number]['items'] = $items->where('batch_number', $item->batch_number)->all();
                    $batch_array[$item->batch_number]['summaries'] = $item->batch->summary_count;
                    $batch_array[$item->batch_number]['id'] = $item->batch->id;
                }
            }

            // foreach ($items as $item) {
            //     // if (!array_key_exists($item->batch_number, $batch_array)) {
            //     // $batch_array[$item->batch_number]['items'] = $items->where('batch_number', $item->batch_number)->all();
            //     $batch_array[$item->batch_number]['items'][] = $item;
            //     $batch_array[$item->batch_number]['summaries'] = $item->batch->summary_count;
            //     $batch_array[$item->batch_number]['id'] = $item->batch->id;
            //     // }
            // }

            // $batch_arr = [];
            // foreach ($batch_array as $key => $value) {
            //     $batch_arr[] = [
            //         ...$value,
            //         'key' => $key
            //     ];
            // }

        }

        return response()->json([
            'batch_array' => $batch_array ?? null,
            'total_items' => $total_items,
            'label' => $label,
            'summary' => $summary,
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
    public function show(Rejection $rejection)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Rejection $rejection)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Rejection $rejection)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Rejection $rejection)
    {
        //
    }

    public function destinationOption()
    {
        $destinations = ['0' => 'Send Batch to', 'G' => 'Graphics', 'GM' => 'Manual Graphics', 'P' => 'Production', 'Q' => 'Quality Control'];
        $data = [];
        foreach ($destinations as $key => $value) {
            $data[] = [
                'value' => $key,
                'label' => $value
            ];
        }
        return $data;
    }

    public function rejectWapItem(Request $request)
    {

        //TODO - need to update for reject
        $origin = $request->get('origin');

        $rules = [
            'item_id'           => 'required',
            'reject_qty'        => 'required|integer|min:1',
            'graphic_status'    => 'required',
            'rejection_reason'  => 'required|exists:rejection_reasons,id',
        ];

        $validation = Validator::make($request->all(), $rules);

        if ($validation->fails()) {
            return response()->json([
                'message' => $validation->errors()->first(),
                'status' => 203
            ], 203);
        }

        $item = Item::with('inventoryunit')
            ->where('id', $request->get('item_id'))
            ->first();

        if ($item->item_status == 'rejected') {
            return response()->json([
                'message' => 'Item Already Rejected',
                'status' => 203
            ], 203);
        }

        $batch_number = $item->batch_number;

        if ($origin == 'QC') {
            $batch = Batch::find($request->get('id'));

            if ($batch && $batch->batch_number != $batch_number) {
                return redirect()->back()->withErrors(['error' => sprintf('Please scan user ID')]);
            }
        } elseif ($origin == 'SL') {
            $tracking_number = $item->tracking_number;
        }

        $result = $this->itemReject(
            $item,
            $request->get('reject_qty'),
            $request->get('graphic_status'),
            $request->get('rejection_reason'),
            $request->get('rejection_message'),
            $request->get('title'),
            $request->get('scrap')
        );

        if ($request->get('graphic_status') == '1') {

            $count = Rejection::where('item_id', $result['reject_id'])->count();

            if ($count == 1) {
                $this->moveStation($result['new_batch_number']);

                $msg = Batch::export($result['new_batch_number'], '0');

                if (isset($msg['error'])) {
                    Batch::note($result['new_batch_number'], '', 0, $msg['error']);
                }
            }
        }

        //TODO - need to update route for reject

        // if ($origin == 'QC') {
        //     return redirect()->route('qcShow', ['id' => $request->get('id'), 'batch_number' => $batch_number, 'label' => $result['label']]);
        // } elseif ($origin == 'BD') {

        //     return redirect()->route('batchShow', ['batch_number' => $batch_number, 'label' => $result['label']]);
        // } elseif ($origin == 'WP') {

        //     return redirect()->route('wapShow', ['bin' => $request->get('bin_id'), 'label' => $result['label'], 'show_ship' => '1']);
        // } elseif ($origin == 'SL') {

        //     $order = Order::find($item->order_5p);

        //     $order->order_status = 4;
        //     $order->save();

        //     Order::note("Order status changed from Shipped to To Be Processed - Item $item->id rejected after shipping", $order->order_5p, $order->order_id);

        //     $shipment = Ship::with('items')
        //         ->where('order_number', $order->id)
        //         ->where('tracking_number', $tracking_number)
        //         ->first();

        //     if ($shipment && $shipment->items && count($shipment->items) == 0) {
        //         $shipment->delete();
        //     }

        //     return redirect()->route('shipShow', ['search_for_first' => $tracking_number, 'search_in_first' => 'tracking_number', 'label' => $result['label']]);
        // } elseif ($origin == 'MP') {    
        //     return redirect()->action('GraphicsController@showBatch', ['scan_batches' => $request->get('scan_batches')]);
        // } else {

        //     $label = $result['label'];
        //     return view('prints.includes.label', compact('label'));
        // }
    }

    public function sendToStart(Request $request)
    {
        $batch_numbers = $request->get('batches');

        $error = array();
        $success = array();

        foreach ($batch_numbers as $batch_number) {

            $result = $this->moveStation($batch_number);

            if ($result) {
                $success[] = 'Batch ' . $batch_number . ' Moved to Production';
            } else {
                $error[] = 'Error moving '  . $batch_number;
            }

            $msg = Batch::export($batch_number, '0');

            if (isset($msg['success'])) {
                $success[] = $msg['success'];
            }

            if (isset($msg['error'])) {
                $error[] = $msg['error'];
            }
        }

        return response()->json([
            'message' => $success[0],
            'status' => 201
        ], 201);

        return redirect()->action('RejectionController@index', ['graphic_status' => $request->get('graphic_status'), 'section' => $request->get('section')])
            ->with('success', $success)
            ->withErrors($error);
    }

    private function moveStation($batch_number, $station_change = null)
    {

        $batch = Batch::with('route.stations_list')
            ->where('batch_number', $batch_number)
            ->first();

        if (!$batch) {
            return false;
        } else {

            if ($station_change == null || $station_change == 'G') {
                $station_change = $batch->route->stations_list->first()->station_id;
            } else if ($station_change == 'P') {
                $station_change = $batch->route->production_stations->first()->id;
            } else if ($station_change == 'Q') {
                $station_change = $batch->route->qc_stations->first()->id;
            }

            $batch->prev_station_id = $batch->station_id;
            $batch->station_id = $station_change;
            $batch->status = 'active';
            $batch->save();

            $items = Item::where('batch_number', $batch->batch_number)
                ->where('is_deleted', '0')
                ->get();

            foreach ($items as $item) {
                $item->item_status = 1;
                $item->save();
            }

            Rejection::where('to_batch', $batch->batch_number)
                ->whereNull('to_station_id')
                ->update([
                    'supervisor_user_id' => auth()->user()->id,
                    'to_station_id'      => $station_change,
                    'complete'           => '1'
                ]);

            Batch::note($batch->batch_number, $batch->station_id, '5', 'Reject batch moved into production');

            return true;
        }
    }

    public function reprintLabel(Request $request)
    {

        $rejection = Rejection::with(
            'item.order',
            'rejection_reason_info',
            'user',
            'from_batch_info.scans.station',
            'from_batch_info.scans.in_user',
            'from_batch_info.scans.out_user'
        )
            ->where('id', $request->get('id'))
            ->first();

        $label = $this->getLabel($rejection);
        return response()->json([
            'message' => 'Label Reprinted',
            'params' => ['label' => $label],
            'status' => 201,
        ], 201);
        return redirect()->action('RejectionController@index', ['label' => $label]);
    }

    public function getLabel($rejection)
    {

        $item = $rejection->item;
        $order_id = $rejection->item->order->short_order;

        if ($rejection->rejection_reason_info) {
            $reason = $rejection->rejection_reason_info->rejection_message;
        } else {
            $reason = '';
        }

        $username = $rejection->user->username;

        $last_scan = null;

        foreach ($rejection->from_batch_info->scans as $scan) {
            if ($scan->station->type == "P") {
                if (isset($scan->in_user)) {
                    $last_scan = $scan->station->station_name . ' : IN ' . $scan->in_user->username . ' ' . substr($scan->in_date, 0, 10);
                }

                if (isset($scan->out_user)) {
                    $last_scan .= ' - OUT ' . $scan->out_user->username . ' ' . substr($scan->in_date, 0, 10);
                }
                break;
            }
        }


        $label = "^XA" .
            "^FX^CF0,200^FO100,50^FDREJECT^FS^FO50,220^GB700,1,3^FS" .
            "^FX^CF0,30^FO50,240^FDItem ID: $rejection->item_id^FS^FO350,240^FDOrder ID:$order_id^FS^FO50,280^FDBatch: $rejection->to_batch^FS" .
            "^FO50,320^FDDate: $rejection->created_at^FS^FO50,370^GB700,1,3^FS^FO50,400^FB750,3,,^FD$item->item_description^FS" .
            "^FO50,440^FB750,2,,^FDSKU:$item->child_sku^FS" .
            "^FO100,480^FB560,6,,^FD" . Helper::optionTransformer($item->item_option, 1, 0, 0, 1, 0, ',  ') . "^FS" .
            "^FX^FO50,630^GB700,270,3^FS^CF0,40^FO75,650^FDRejected QTY: $rejection->reject_qty^FS" .
            "^FO350,650^FDRejected By: $username^FS^CF0,50^FO75,700^FDReason: $reason^FS" .
            "^FO75,750^FB700,3,,^FD$rejection->rejection_message^FS" .
            "^CF0,20^FO60,875^FDSCAN : $last_scan^FS" .
            "^FX^BY5,2,150^FO50,920^BC^FDITEM$rejection->item_id^FS" .
            "^XZ";

        return str_replace("'", " ", $label);
    }
}
