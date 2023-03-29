<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\PurchasedInvProduct;
use App\Models\Vendor;
use Illuminate\Http\Request;

class PurchasedInvProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $product = PurchasedInvProduct::query();
        $product = $product->with(['vendor', 'inventory']);
        if ($request->q) {
            $product = $product->where('stock_no', 'like', '%' . $request->q . '%')
                ->orWhere('vendor_sku', 'like', '%' . $request->q . '%')
                ->orWhere('vendor_sku_name', 'like', '%' . $request->q . '%')
                ->orWhereHas('inventory', function ($q) use ($request) {
                    $q->where('stock_name_discription', 'like', '%' . $request->q . '%');
                })
                ->orWhereHas('vendor', function ($q) use ($request) {
                    $q->where('vendor_name', 'like', '%' . $request->q . '%');
                });
        }
        if ($request->sort && $request->sortColumn) {
            $product = $product->orderBy($request->sortColumn, $request->sort);
        }
        $product = $product->paginate($request->get('perPage', 10));
        return $product;
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
            'stock_no' => 'required',
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
        return PurchasedInvProduct::with(['vendor', 'inventory'])->find($id);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PurchasedInvProduct $purchasedInvProduct)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //TODO need to work
        try {
            $product = PurchasedInvProduct::findOrFail($id);
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
        $data = PurchasedInvProduct::find($id);
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
                'label' => $item['vendor_name']
            ];
        });
        return $vendors;
    }

    public function productOptionbyVendor($id)
    {
        $products = PurchasedInvProduct::where('vendor_id', $id)->get();
        $products->transform(function ($item) {
            return [
                'value' => $item['id'],
                'label' => $item['stock_no'] . ' - ' . $item['vendor_sku'] . ' - ' . $item['vendor_sku_name'],
                'data' => $item
            ];
        });
        return $products;
    }
}
