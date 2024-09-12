<?php

namespace App\Http\Controllers;

use App\Models\ProductionCategory;
use Illuminate\Http\Request;

class ProductionCategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = ProductionCategory::query();
        if ($request->q) {
            $categories = $categories->where('production_category_description', 'like', '%' . $request->q . '%')->orWhere('production_category_code', 'like', '%' . $request->q . '%');
        }
        return $categories->orderBy('id', 'desc')->paginate($request->get('perPage', 10));
    }

    public function show(string $id)
    {
        return ProductionCategory::find($id);
    }

    public function store(Request $request)
    {
        $request->validate([
            'production_category_code' => 'required',
        ]);

        $data = $request->only([
            'production_category_code',
            'production_category_description',
            'production_category_display_order'
        ]);
        try {
            ProductionCategory::create($data);
            return response()->json([
                'message' => 'Production Category created successfully!',
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

    public function update(Request $request, string $id)
    {
        $request->validate([
            'production_category_code' => 'required',
        ]);
        try {
            $station = ProductionCategory::find($id);
            if (!$station) {
                return response()->json([
                    'message' => 'Production Category not found!',
                    'status' => 203,
                    'data' => []
                ], 203);
            }
            $data = $request->only([
                'production_category_code',
                'production_category_description',
                'production_category_display_order'
            ]);
            $station->update($data);
            return response()->json([
                'message' => 'Production Category update successfully!',
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

    public function destroy(string $id)
    {
        $data = ProductionCategory::find($id);
        if ($data) {
            $data->delete();
            return response()->json([
                'message' => 'Production Category delete successfully!',
                'status' => 201,
                'data' => []
            ], 201);
        }
        return response()->json([
            'message' => "Production Category didn't found!",
            'status' => 203,
            'data' => []
        ], 203);
    }
    public function productionCategoryOption()
    {
        $productionCategory = ProductionCategory::where('is_deleted', '0')->get();
        $productionCategory->transform(function ($item, $key) {
            return [
                'label' => $item->production_category_description,
                'value' => $item->id
            ];
        });
        return $productionCategory;
    }
}
