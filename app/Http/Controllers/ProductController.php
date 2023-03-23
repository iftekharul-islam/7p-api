<?php

namespace App\Http\Controllers;

use App\Models\Products;
use App\Models\Stocks;
use App\Models\Vendors;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Products::with('vendor')->paginate($request->get('perPage', 10));
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
        $request->validate([
            'stock_id' => 'required',
            'vendor_id' => 'required',
        ]);

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
        ], 201);
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

    public function addStock(Request $request)
    {
        $request->validate([
            'stock_number' => 'required'
        ]);

        Stocks::create([
            'stock_number' => $request->stock_number,
            'description' => $request->description,
            'section_id' => $request->section_id,
            'weight' => $request->weight,
            'order_quantity' => $request->order_quantity,
            'minimum_stock_quantity' => $request->minimum_stock_quantity,
            'last_cost' => $request->last_cost,
            'upc' => $request->upc,
            'vendor_sku' => $request->vendor_sku,
            'bin' => $request->bin,
            'image_url' => $request->image_url,
        ]);

        return response()->json([
            'message' => 'Product created successfully!',
            'data' => []
        ], 201);
    }


    public function stockOption()
    {
        $stocks = Stocks::get();
        $stocks->transform(function ($item) {
            return [
                'value' => $item['id'],
                'label' => $item['stock_number'] . ' - ' . $item['description'],
                'data' => $item,
            ];
        });
        return $stocks;
    }
    public function vendorOption()
    {
        $vendors = Vendors::get();
        $vendors->transform(function ($item) {
            return [
                'value' => $item['id'],
                'label' => $item['name']
            ];
        });
        return $vendors;
    }
}
