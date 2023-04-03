<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryAdjustment;
use App\Models\Rejection;
use Illuminate\Http\Request;

class InventoryAdjustmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function viewAdjustment(Request $request)
    {
        $adjustments = InventoryAdjustment::query()->with('user', 'inventory');
        if ($request->q) {
            $adjustments->where('stock_no_unique', $request->q);
        }
        return $adjustments->latest()->paginate($request->get('perPage', 10));
    }

    public function adjustInventory(Request $request)
    {
        $inventory =  Inventory::with('adjustments.user', 'inventoryUnitRelation')
            ->leftjoin('purchased_products', 'inventories.stock_no_unique', '=', 'purchased_products.stock_no')
            ->where('stock_no_unique', $request->q)
            ->first();
        return ['data' => $inventory ?? null];
    }

    public function ProductionRejects(Request $request)
    {
        logger($request->q);
        $rejects = Rejection::with('item.inventoryunit', 'rejection_reason_info')
//            ->whereNull('scrap')
            ->where('item_id', $request->q);
        $data = $rejects->paginate($request->get('perPage', 10));
        logger($data);
        return $data;
    }



    public function updateAdjustInventory(Request $request)
    {
        if ($request->has('count_quantity') && $request->has('count_stock_no')) {
            $count_stock_no = $request->get('count_stock_no');
            InventoryAdjustment::adjustInventory(3, $request->get('count_stock_no'), $request->get('count_quantity'), $request->get('count_note'));
            $inventory = Inventory::where('stock_no_unique', $count_stock_no)->first();
            if ($inventory->qty_on_hand == intval($request->get('count_quantity'))) {
                return response()->json([
                    'message' => "Quantity on hand for $count_stock_no adjusted to " . $request->get('count_quantity'),
                    'status' => 201,
                    'data' => []
                ], 201);
            } else {
                return response()->json([
                    'message' => "Quantity on hand for $count_stock_no incorrect after adjustment",
                    'status' => 203,
                    'data' => []
                ], 203);
            }
        } elseif ($request->has('adjust_quantity') && $request->has('count_stock_no')) {
            $count_stock_no = $request->get('count_stock_no');
            if ($request->get('adjust_quantity') == 0) {
                return response()->json([
                    'message' => "Invalid quantity entered",
                    'status' => 203,
                    'data' => []
                ], 203);
            }
            InventoryAdjustment::adjustInventory(4, $request->get('count_stock_no'), $request->get('adjust_quantity'),  $request->get('adjust_note'));
            return response()->json([
                'message' => "$count_stock_no adjusted by " . $request->get('adjust_quantity'),
                'status' => 201,
                'data' => []
            ], 201);
        } elseif ($request->has('rejection_id')) {
            $reject = Rejection::with('item.inventoryunit')
                ->where('id', $request->get('rejection_id'))
                ->first();
            if ($request->get('action') == 'scrap') {
                if (count($reject->item->inventoryunit) > 0) {
                    foreach ($reject->item->inventoryunit as $stock_no) {
                        if ($stock_no->stock_no_unique != '' && $stock_no->stock_no_unique != 'ToBeAssigned') {
                            //only saves last adjustment ??
                            $reject->scrap = InventoryAdjustment::adjustInventory(5, $stock_no->stock_no_unique, $reject->reject_qty * $stock_no->unit_qty, $reject->id, $reject->item->id);
                            $reject->save();
                            return redirect()->action('InventoryAdjustmentController@index', ['tab' => 'production', 'reject_item' => $request->get('reject_item')])
                                ->with('success', 'Inventory adjusted for Reject Item ' . $request->get('reject_item'));
                        } else {
                            $reject->scrap = 0;
                            $reject->save();
                            return redirect()->action('InventoryAdjustmentController@index', ['tab' => 'production', 'reject_item' => $request->get('reject_item')])
                                ->withErrors('Reject Item ' . $request->get('reject_item') . ' ignored, no stock number found');
                        }
                    }
                } else {
                    $reject->scrap = 0;
                    $reject->save();
                    return redirect()->action('InventoryAdjustmentController@index', ['tab' => 'production', 'reject_item' => $request->get('reject_item')])
                        ->withErrors('Reject Item ' . $request->get('reject_item') . ' ignored, no stock number found');
                }
            } elseif ($request->get('action') == 'ignore') {

                $reject->scrap = 0;
                $reject->save();

                return redirect()->action('InventoryAdjustmentController@index', ['tab' => 'production', 'reject_item' => $request->get('reject_item')])
                    ->with('success', 'Reject Item ' . $request->get('reject_item') . ' ignored.');
            } else {
                return redirect()->action('InventoryAdjustmentController@index', ['tab' => 'production', 'reject_item' => $request->get('reject_item')])
                    ->withErrors('Error when processing Item ' . $request->get('reject_item'));
            }
        } elseif ($request->has('reject_quantity') && $request->has('receive_stock_no')) {
        } else {
            return response()->json([
                'message' => "Unrecognized Input",
                'status' => 203,
                'data' => []
            ], 203);
        }
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
    public function show(InventoryAdjustment $inventoryAdjustment)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(InventoryAdjustment $inventoryAdjustment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, InventoryAdjustment $inventoryAdjustment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(InventoryAdjustment $inventoryAdjustment)
    {
        //
    }
}
