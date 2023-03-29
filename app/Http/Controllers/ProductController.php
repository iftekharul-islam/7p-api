<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\PurchasedInvProduct;
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

        $data = $request->only([
            'stock_no',
            'unit',
            'unit_price',
            'unit_qty',
            'vendor_id',
            'vendor_sku',
            'vendor_sku_name',
            'lead_time_days',
            'user_id',
        ]);

        $data['user_id'] = auth()->user()->id;

        try {
            PurchasedInvProduct::create($data);
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
        $data = $request->only([
            'stock_no_unique',
            'stock_name_discription',
            'section_id',
            'sku_weight',
            're_order_qty',
            'min_reorder',
            'last_cost',
            'upc',
            'wh_bin',
            'warehouse'
        ]);
        $data['stock_no_unique'] = $request->stock_no_unique ?? $this->generateStockNoUnique();
        $data['user_id'] = auth()->user()->id;

        Inventory::create($data);

        return response()->json([
            'message' => 'Stock created successfully!',
            'data' => []
        ], 201);
    }

    private function generateStockNoUnique()
    {
        $stockNoUnique = Inventory::orderBy('id', 'desc')->first();

        return sprintf("1%05d", (($stockNoUnique->id ?? 0) + 1));
    }


    public function stockOption()
    {
        $stocks = Inventory::get();
        $stocks->transform(function ($item) {
            return [
                'value' => $item['id'],
                'label' => $item['stock_no_unique'] . ' - ' . $item['stock_name_discription'],
                'stock_no' => $item['stock_no_unique'],
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

    public function productOptionbyVendor($id)
    {
        $products = Product::where('vendor_id', $id)->get();
        $products->transform(function ($item) {
            return [
                'value' => $item['id'],
                'label' => $item['name'],
                'data' => $item
            ];
        });
        return $products;
    }
}
