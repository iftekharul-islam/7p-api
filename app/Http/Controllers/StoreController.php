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

    public function show(string $id)
    {
        $store = Store::find($id);
        if (!$store) return response()->json(['message' => 'Store not found', 'status' => 203], 203);
        $file = "/var/www/order.monogramonline.com/Store.json";
        $store->dropship = false;
        $store->dropshipTracking = false;

        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);

            if (isset($data[$store->store_name])) {
                $store->dropship = $data[$store->store_name]['DROPSHIP'];
                $store->dropshipTracking = $data[$store->store_name]['DROPSHIP_IMPORT'];
            }
        }
        return $store;
    }

    public function store(Request $request)
    {
        if (!isset($request->store_name)) return response()->json(['message' => 'Name can not be empty', 'status' => 203], 203);
        if (!isset($request->store_id)) return response()->json(['message' => 'ID can not be empty', 'status' => 203], 203);
        if (!isset($request->email)) return response()->json(['message' => 'Email can not be empty', 'status' => 203], 203);
        if (!isset($request->company)) return response()->json(['message' => 'Company can not be empty', 'status' => 203], 203);
        if (!isset($request->input)) return response()->json(['message' => 'Data Input can not be empty', 'status' => 203], 203);
        if (!isset($request->ship_label)) return response()->json(['message' => 'Additional Shipping Label can not be empty', 'status' => 203], 203);
        if (!isset($request->packing_list)) return response()->json(['message' => 'Packing List can not be empty', 'status' => 203], 203);
        if (!isset($request->multi_carton)) return response()->json(['message' => 'Multiple Package Shipping can not be empty', 'status' => 203], 203);
        if (!isset($request->ups_type)) return response()->json(['message' => 'UPS can not be empty', 'status' => 203], 203);
        if (!isset($request->fedex_type)) return response()->json(['message' => 'Fedex can not be empty', 'status' => 203], 203);

        $sort = Store::selectRaw('MAX(sort_order) as num')->first();
        $store = new Store;

        $new_id = strtolower($request->get('store_id'));
        $new_id = str_replace(' ', '', $new_id);
        $new_id = preg_replace('/[^\w]+/', '-', $new_id);

        $store->store_id = $new_id;

        $store->store_name = $request->get('store_name');
        $store->company = $request->get('company');
        $store->qb_export = $request->get('qb_export') ? $request->get('qb_export') : '0';
        $store->sort_order = $sort->num + 1;
        $store->class_name = $request->get('class_name');
        $store->email = $request->get('email');
        $store->input = $request->get('input');
        $store->change_items = $request->get('change_items') ? $request->get('change_items') : '0';
        $store->qc = $request->get('qc') ? $request->get('qc') : '0';
        $store->batch = $request->get('batch') ? $request->get('batch') : '0';
        $store->print = $request->get('print') ? $request->get('print') : '0';
        $store->confirm = $request->get('confirm') ? $request->get('confirm') : '0';
        $store->backorder = $request->get('backorder') ? $request->get('backorder') : '0';
        $store->ship_banner_url = $request->get('ship_banner_url') ? $request->get('ship_banner_url') : '';
        $store->ship_banner_image = $request->get('ship_banner_image') ? $request->get('ship_banner_image') : '';
        $store->ship = $request->get('ship') ? $request->get('ship') : '0';
        $store->validate_addresses = $request->get('validate_addresses') ? $request->get('validate_addresses') : '0';
        $store->change_method = $request->get('change_method') != null ? $request->get('change_method') : '1';
        $store->ship_label = $request->get('ship_label');
        $store->packing_list = $request->get('packing_list');
        $store->multi_carton = $request->get('multi_carton');

        $store->ups_type = $request->get('ups_type');
        $store->ups_account = $request->get('ups_account');

        $store->fedex_type = $request->get('fedex_type');
        $store->fedex_account = $request->get('fedex_account');
        $store->fedex_password = $request->get('fedex_password');
        $store->fedex_key = $request->get('fedex_key');
        $store->fedex_meter = $request->get('fedex_meter');

        $store->ship_name = $request->get('ship_name');
        $store->address_1 = $request->get('address1');
        $store->address_2 = $request->get('address2');
        $store->city = $request->get('city');
        $store->state = $request->get('state');
        $store->zip = $request->get('zip');
        $store->phone = $request->get('phone');

        $store->save();

        return response()->json([
            'message' => $store->store_name . ' Store created successfully',
            'status' => 201,
        ], 201);
    }

    public function update(Request $request, string $id)
    {
        if (!isset($request->store_name)) return response()->json(['message' => 'Name can not be empty', 'status' => 203], 203);
        if (!isset($request->store_id)) return response()->json(['message' => 'ID can not be empty', 'status' => 203], 203);
        if (!isset($request->email)) return response()->json(['message' => 'Email can not be empty', 'status' => 203], 203);
        if (!isset($request->company)) return response()->json(['message' => 'Company can not be empty', 'status' => 203], 203);
        if (!isset($request->input)) return response()->json(['message' => 'Data Input can not be empty', 'status' => 203], 203);
        if (!isset($request->ship_label)) return response()->json(['message' => 'Additional Shipping Label can not be empty', 'status' => 203], 203);
        if (!isset($request->packing_list)) return response()->json(['message' => 'Packing List can not be empty', 'status' => 203], 203);
        if (!isset($request->multi_carton)) return response()->json(['message' => 'Multiple Package Shipping can not be empty', 'status' => 203], 203);
        if (!isset($request->ups_type)) return response()->json(['message' => 'UPS can not be empty', 'status' => 203], 203);
        if (!isset($request->fedex_type)) return response()->json(['message' => 'Fedex can not be empty', 'status' => 203], 203);

        $store = Store::find($id);

        if (!$store) return response()->json(['message' => 'Store not found', 'status' => 203], 203);

        $store->store_name = $request->get('store_name');
        $store->company = $request->get('company');
        $store->qb_export = $request->get('qb_export') ? $request->get('qb_export') : '0';
        $store->class_name = $request->get('class_name');
        $store->email = $request->get('email');
        $store->input = $request->get('input');
        $store->change_items = $request->get('change_items') ? $request->get('change_items') : '0';
        $store->batch = $request->get('batch') ? $request->get('batch') : '0';
        $store->print = $request->get('print') ? $request->get('print') : '0';
        $store->qc = $request->get('qc') ? $request->get('qc') : '0';
        $store->confirm = $request->get('confirm') ? $request->get('confirm') : '0';
        $store->backorder = $request->get('backorder') ? $request->get('backorder') : '0';
        $store->ship_banner_url = $request->get('ship_banner_url') ? $request->get('ship_banner_url') : '';
        $store->ship_banner_image = $request->get('ship_banner_image') ? $request->get('ship_banner_image') : '';
        $store->ship = $request->get('ship') ? $request->get('ship') : '0';
        $store->validate_addresses = $request->get('validate_addresses') ? $request->get('validate_addresses') : '0';
        $store->change_method = $request->get('change_method') != null ? $request->get('change_method') : '1';
        $store->ship_label = $request->get('ship_label');
        $store->packing_list = $request->get('packing_list');
        $store->multi_carton = $request->get('multi_carton');

        $store->ups_type = $request->get('ups_type');
        $store->ups_account = $request->get('ups_account');
        $store->fedex_type = $request->get('fedex_type');
        $store->fedex_account = $request->get('fedex_account');
        $store->fedex_password = $request->get('fedex_password');
        $store->fedex_key = $request->get('fedex_key');
        $store->fedex_meter = $request->get('fedex_meter');

        $store->ship_name = $request->get('ship_name');
        $store->address_1 = $request->get('address1');
        $store->address_2 = $request->get('address2');
        $store->city = $request->get('city');
        $store->state = $request->get('state');
        $store->zip = $request->get('zip');
        $store->phone = $request->get('phone');

        //TODO: Add current Store.json file to the store folder
        $file = "/var/www/order.monogramonline.com/Store.json";
        $template = [
            "DROPSHIP" => (bool) $request->get('dropship'),
            "DROPSHIP_IMPORT" => (bool) $request->get("dropship_tracking")
        ];

        //TODO: uncomment this code to add store to Store.json file
        // if (!file_exists($file)) {
        //     file_put_contents($file, json_encode(
        //         [
        //             $store->store_name => $template
        //         ],
        //         JSON_PRETTY_PRINT
        //     ));
        // } else {
        //     $data = json_decode(file_get_contents($file), true);

        //     if (!isset($data[$store->store_name])) {
        //         $data[$store->store_name] = $template;

        //         file_put_contents($file, json_encode(
        //             $data,
        //             JSON_PRETTY_PRINT
        //         ));
        //     } else {
        //         $data[$store->store_name] = $template;
        //         file_put_contents($file, json_encode(
        //             $data,
        //             JSON_PRETTY_PRINT
        //         ));
        //     }
        // }
        $store->save();
        return response()->json([
            'message' => 'Store Updated',
            'status' => 201,
        ], 201);
    }

    //delete store
    public function delete(string $id)
    {
        $store = Store::find($id);
        $store->is_deleted = '1';
        $store->save();

        $store->delete();
        return response()->json([
            'message' => 'Store Deleted',
            'status' => 201,
        ], 201);
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

    public function companyOption()
    {
        foreach (Store::$companies as $key => $value) {
            $data[] = [
                'value' => $key,
                'label' => $value,
            ];
        }
        return $data;
    }

    public function inputOption()
    {
        foreach (Store::inputOptions() as $key => $value) {
            $data[] = [
                'value' => $key,
                'label' => $value,
            ];
        }
        return $data;
    }

    public function batchOption()
    {
        foreach (Store::batchOptions() as $key => $value) {
            $data[] = [
                'value' => $key,
                'label' => $value,
            ];
        }
        return $data;
    }

    public function confirmOption()
    {
        foreach (Store::notifyOptions() as $key => $value) {
            $data[] = [
                'value' => $key,
                'label' => $value,
            ];
        }
        return $data;
    }

    public function qcOption()
    {
        foreach (Store::qcOptions() as $key => $value) {
            $data[] = [
                'value' => $key,
                'label' => $value,
            ];
        }
        return $data;
    }
}
