<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $products = Product::where('is_deleted', '0')
            ->searchInOption($request->get('search_in'), $request->get("search_for"))
            ->searchProductionCategory($request->get('product_production_category'));
        return $products->paginate($request->get('perPage', 10));
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
        $id_catalog = trim($request->get('id_catalog'));
        $product_model = trim($request->get('product_model'));
        $checkExisting = Product::where('id_catalog', $id_catalog)
            ->orWhere('product_model', $product_model)
            ->first();
        if ($checkExisting) {
            return response()->json([
                'message' => 'Product already exists either with id catalog or model!',
                'status' => 203,
                'data' => []
            ], 203);
        }

        $data = $request->only([
            'id_catalog',
            'product_model',
            'product_upc',
            'product_asin',
            'product_default_cost',
            'product_url',
            'product_name',
            'ship_weight',
            'product_production_category',
            'product_price',
            'product_sale_price',
            'product_wholesale_price',
            'product_thumb',
            'product_description',
            'height',
            'width',
        ]);
        try {
            Product::create($data);
            return response()->json([
                'message' => 'Production created successfully!',
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
        $product = Product::query()
            ->where('is_deleted', '0')
            ->find($id);
        if (!$product) {
            return response()->json([
                'message' => 'Product Not Found',
                'status' => 203,
                'data' => []
            ], 203);
        }
        return $product;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json([
                'message' => 'Product not found!',
                'status' => 203,
                'data' => []
            ], 203);
        }
        $data = $request->only([
            'id_catalog',
            'product_upc',
            'product_asin',
            'product_default_cost',
            'product_url',
            'product_name',
            'ship_weight',
            'product_production_category',
            'product_price',
            'product_sale_price',
            'product_wholesale_price',
            'product_thumb',
            'product_description',
            'manufacture_id',
            'height',
            'width'
        ]);
        $product->product_note = $request->get("product_note") . "@" . $request->get("msg_flag");

        $product->update($data);
        return response()->json([
            'message' => 'Product update successfully!',
            'status' => 201,
            'data' => []
        ], 201);
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

    public function searchableFieldsOption()
    {
        $searchable_fields = [];
        foreach (Product::$searchable_fields ?? [] as $key => $value) {
            $searchable_fields[] = [
                'label' => $value,
                'value' => $key,
            ];
        };
        return $searchable_fields;
    }
}
