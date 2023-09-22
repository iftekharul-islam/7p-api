<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\StoreItem;
use Illuminate\Http\Request;

class StoreItemController extends Controller
{
    public function index(string $id)
    {
        $store = Store::with('store_items')->find($id);
        if (!$store) return response()->json(['message' => 'Store not found', 'status' => 203], 203);
        return $store;
    }

    public function store(Request $request)
    {

        if (!$request->has('store_id')) {
            return response()->json(['message' => 'Store ID is required', 'status' => 203], 203);
        }
        if (!$request->has('vendor_sku') || !$request->has('parent_sku')) {
            return response()->json(['message' => 'Vendor SKU and Parent SKU are required', 'status' => 203], 203);
        }

        $item = new StoreItem();
        $item->store_id = $request->get('store_id');
        $item->custom = $request->get('custom');
        $item->vendor_sku = $request->get('vendor_sku');
        $item->description = $request->get('description');
        $item->cost = $request->get('cost');
        $item->parent_sku = $request->get('parent_sku');
        $item->child_sku = $request->get('child_sku');
        $item->url = $request->get('url');
        $item->upc = $request->get('upc');
        $item->save();
        return response()->json(['message' => 'Item Added', 'status' => 201], 201);
    }

    public function update(Request $request)
    {

        if (!$request->has('store_id')) {
            return response()->json(['message' => 'Store ID is required', 'status' => 203], 203);
        }
        if (!$request->has('vendor_sku') || !$request->has('parent_sku')) {
            return response()->json(['message' => 'Vendor SKU and Parent SKU are required', 'status' => 203], 203);
        }

        $item = StoreItem::find($request->get('id'));
        if (!$item) return response()->json(['message' => 'Item not found', 'status' => 203], 203);

        $item->store_id = $request->get('store_id');
        $item->custom = $request->get('custom');
        $item->vendor_sku = $request->get('vendor_sku');
        $item->description = $request->get('description');
        $item->cost = $request->get('cost');
        $item->parent_sku = $request->get('parent_sku');
        $item->child_sku = $request->get('child_sku');
        $item->url = $request->get('url');
        $item->upc = $request->get('upc');
        $item->save();

        return response()->json(['message' => 'Item Updated', 'status' => 201], 201);
    }

    public function delete(string $id)
    {
        $item = StoreItem::find($id);
        if (!$item) return response()->json(['message' => 'Item not found', 'status' => 203], 203);
        $item->is_deleted = '1';
        $item->save();

        return response()->json(['message' => 'Item Delete', 'status' => 201], 201);
    }
}
