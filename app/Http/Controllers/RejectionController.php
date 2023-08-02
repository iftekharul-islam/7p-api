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

class RejectionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $batch_array = array();
        $store_ids = Store::where('permit_users', 'like', "%" . auth()->user()->id . "%")
            ->where('is_deleted', '0')
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
                // if (!array_key_exists($item->batch_number, $batch_array)) {
                // $batch_array[$item->batch_number]['items'] = $items->where('batch_number', $item->batch_number)->all();
                $batch_array[$item->batch_number]['items'][] = $item;
                $batch_array[$item->batch_number]['summaries'] = $item->batch->summary_count;
                $batch_array[$item->batch_number]['id'] = $item->batch->id;
                // }
            }

            $batch_arr = [];
            foreach ($batch_array as $key => $value) {
                $batch_arr[] = [
                    ...$value,
                    'key' => $key
                ];
            }
        }

        return response()->json([
            'batch_array' => $batch_arr ?? null,
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

    public function badAddress(Request $request)
    {
        $order = Order::find($request->get('order_id'));

        if (!$order) {
            return response()->json([
                'message' => 'Order not found',
                'status' => 203
            ], 203);
        }

        if ($order->order_status == 4) {
            Log::info('Order ' . $request->get('order_id') . ' updated in WAP to address hold.');
            $order->order_status = 11;
            $order->save();

            return response()->json([
                'message' => $order->short_order . ' Address Sent to Customer Service',
                'status' => 201
            ], 201);
        } else {
            return response()->json([
                'message' => $order->short_order . ' Cannot be placed in Address Hold, order is not in progress',
                'status' => 203
            ], 203);
        }
    }
}
