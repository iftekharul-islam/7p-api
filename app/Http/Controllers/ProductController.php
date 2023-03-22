<?php

namespace App\Http\Controllers;

use App\Models\Products;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        $products = Products::with('vendor')->get();
        return $products;
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

        Products::create([
            'stock_id' => $request->stock_id,
            'name' => $request->name,
            'sku_weight' => $request->sku_weight,
            're_order_qty' => $request->re_order_qty,
            'min_order' => $request->min_order,
            'adjusment' => $request->adjusment,
            'unit' => $request->unit,
            'qty' => $request->qty,
            'unit_price' => $request->unit_price,
            'vendor_id' => $request->vendor_id,
            'vendor_sku' => $request->vendor_sku,
            'sku_name' => $request->sku_name,
            'lead_time_days' => $request->lead_time_days,
        ]);

        return response()->json([
            'message' => 'Product created successfully!',
            'status' => 201,
            'data' => []
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
