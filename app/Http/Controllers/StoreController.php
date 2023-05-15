<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    //return all stores
    public function index()
    {
        $stores = Store::with('store_items')
            ->where('is_deleted', '0')
            ->orderBy('sort_order')
            //TODO: add user permission
            // ->where('permit_users', 'like', "%" . auth()->user()->id . "%")
            ->get();
        $stores->map(function ($store) {
            $store->company = Store::$companies[$store->company];
            return $store;
        });
        return $stores;
    }

    public function visible($id)
    {
        $store = Store::find($id);

        if (!$store) {
            return response()->json([
                'message' => 'Store not Found',
                'status' => 203,
            ], 203);
        }

        if ($store->invisible == '0') {
            $store->invisible = '1';
        } else {
            $store->invisible = '0';
        }
        $store->save();
        return response()->json([
            'message' => $store->invisible == '1' ? 'Hide store successfully' : 'Show store successfully',
            'status' => 201,
        ], 201);
    }

    public function sortOrder($direction, $id)
    {
        $store = Store::find($id);

        if (!$store) {
            return response()->json([
                'message' => 'Store not Found',
                'status' => 203,
            ], 203);
        }
        if ($direction == 'up') {
            $new_order = $store->sort_order - 1;
        } else if ($direction == 'down') {
            $new_order = $store->sort_order + 1;
        } else {
            return response()->json([
                'message' => 'Sort direction not recognized',
                'status' => 203,
            ], 203);
        }

        $switch = Store::where('sort_order', $new_order)->get();
        if (count($switch) > 1) {
            return response()->json([
                'message' => 'More than one store with same sort order',
                'status' => 203,
            ], 203);
        }

        if (count($switch) == 1) {
            $switch->first()->sort_order = $store->sort_order;
            $switch->first()->save();
        }

        $store->sort_order = $new_order;
        $store->save();

        return response()->json([
            'message' => 'Sort order updated successfully',
            'status' => 201,
        ], 201);
    }
}
