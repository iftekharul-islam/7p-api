<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return Vendor::paginate($request->get('perPage', 10));

        // return $products;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required',
            'phone_number' => 'required',
        ]);

        try {
            Vendor::create($request->all());
            return response()->json([
                'message' => 'Vendor created successfully!',
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
        return Vendor::find($id);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $product = Vendor::find($id);
            if (!$product) {
                return response()->json([
                    'message' => 'Product not found!',
                    'status' => 203,
                    'data' => []
                ], 203);
            }
            $product->update($request->all());
            return response()->json([
                'message' => 'Vendor created successfully!',
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
        $data = Vendor::find($id);
        if ($data) {
            $data->delete();
            return response()->json([
                'message' => 'Vendor delete successfully!',
                'status' => 201,
                'data' => []
            ], 201);
        }
        return response()->json([
            'message' => "Vendor didn't found!",
            'status' => 203,
            'data' => []
        ], 203);
    }
}
