<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Rejection;
use App\Models\Store;
use Illuminate\Http\Request;

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

                if (!array_key_exists($item->batch_number, $batch_array)) {
                    $batch_array[$item->batch_number]['items'] = $items->where('batch_number', $item->batch_number)->all();
                    $batch_array[$item->batch_number]['summaries'] = $item->batch->summary_count;
                    $batch_array[$item->batch_number]['id'] = $item->batch->id;
                }
            }
        }

        return response()->json([
            'batch_array' => $batch_array,
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
                'lavel' => $value
            ];
        }
        return $data;
    }
}
