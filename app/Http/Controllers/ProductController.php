<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Stock;
use App\Models\Vendor;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        return Product::with(['vendor', 'stock'])->paginate($request->get('perPage', 10));
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

        try {
            Product::create($request->all());
            return response()->json([
                'message' => 'Product created successfully!',
                'status' => 201,
                'data' => []
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 403,
                'data' => []
            ], 403);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Product::with(['vendor', 'stock'])->find($id);
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
        try {
            $product = Product::findOrFail($id);
            $product->update($request->all());
            return response()->json([
                'message' => 'Product created successfully!',
                'status' => 201,
                'data' => []
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 403,
                'data' => []
            ], 403);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $data = Product::find($id);
        if ($data) {
            $data->delete();
            return response()->json([
                'message' => 'Product delete successfully!',
                'status' => 201,
                'data' => []
            ], 201);
        }
        return response()->json([
            'message' => "Product didn't found!",
            'status' => 203,
            'data' => []
        ], 203);
    }

    public function addStock(Request $request)
    {
        $request->validate([
            'stock_number' => 'required'
        ]);

        Stock::create([
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
        $stocks = Stock::get();
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
        $vendors = Vendor::get();
        $vendors->transform(function ($item) {
            return [
                'value' => $item['id'],
                'label' => $item['name']
            ];
        });
        return $vendors;
    }
}
