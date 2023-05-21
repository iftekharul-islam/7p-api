<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Ship;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Market\Dropship;
use Market\Quickbooks;
use Ship\Batching;
use Ship\CSV;

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

    public function importOrdersFile(Request $request)
    {
        if ($request->has('store_id')) {
            $store = Store::where('store_id', $request->get('store_id'))->first();

            if ($store->input == '3') {
                $className = "Market" . "\\" . $store->class_name;

                $controller = new $className;
                $result = $controller->importCsv($store, $request->file('file'));
                $errors = $result['errors'];
                $orders = Order::with('items', 'customer', 'store')
                    ->whereIn('id', $result['order_ids'])
                    ->get();

                if ($store->batch == '2') {
                    $store_ids = array_unique($orders->pluck('store_id')->toArray());

                    foreach ($store_ids as $store_imported) {
                        Batching::auto(0, $store_imported);
                    }
                }
            }
            return response()->json([
                'message' => 'Orders imported successfully',
                'status' => 201,
                'errors' => $errors,
                'orders' => $orders,
            ], 201);
        } else {
            return response()->json([
                'message' => 'Store not found',
                'status' => 203,
            ], 203);
        }
    }

    public function importTrackingFile(Request $request)
    {
        if ($request->has('store_id')) {
            $store = Store::where("store_id", $request->get('store_id'))->first();
            $data = Dropship::export($request, $store);

            $orders = Order::with('items', 'customer', 'store')
                ->whereIn('id', $data['orders'])
                ->get();
        } else {
            return response()->json([
                'message' => 'Store not found',
                'status' => 203,
            ], 203);
        }
    }

    public function importZakekeFile(Request $request)
    {
        $csv = new CSV;
        $data = $csv->intoArray($request->file("file")->getPathname(), ",");

        $temp = [];
        foreach ($data as $datum) {
            foreach ($datum as $value) {
                $temp[] = $value;
            }
        }
        $data = $temp;
        unset($temp);

        $stats = [
            "NOT_FOUND" => [],
            "QUANTITY_MORE_THAN_ONE" => [],
            "STATUS_ISSUE" => [],
            "ORDER_MATCHED" => 0
        ];

        $orders = Order::with("items")
            ->whereIn("short_order", $data)
            ->get();


        $stats['NOT_FOUND'] = $data;
        unset($data);

        /*
         * Remove found orders from the NOT_FROUND
         * Only leaving the ones that aren't found
         */
        foreach ($orders as $order) {
            if (in_array($order->short_order, $stats['NOT_FOUND'])) {
                unset($stats['NOT_FOUND'][array_search($order->short_order, $stats['NOT_FOUND'])]);
            }
        }

        /**
         * Adding the order short_order here
         * will not add it to the
         * @see $stats['ORDER_MATCHED']++
         */
        $filters = [];

        foreach ($orders as $order) {


            foreach ($order->items as $item) {


                /*
                 * Check if contains more than 2 stuff
                 */
                if ($item->item_quantity >= 2 or count($order->items) >= 2) {
                    $stats['QUANTITY_MORE_THAN_ONE'][] = $order->short_order;
                } else {
                    if (!isset($filters[$order->short_order])) {

                        /*
                         * Check if item is on hold
                         */
                        if ($order->order_status != 23) {
                            if (!in_array($order->short_order, $stats['QUANTITY_MORE_THAN_ONE'])) {
                                $stats['STATUS_ISSUE'][] = $order->short_order;
                            }
                        } else {
                            // Not on hold can continue
                            $stats['ORDER_MATCHED']++;
                            $filters[] = $order->short_order;
                        }
                    }
                }
            }
        }

        /*
         * Now use the filters array, supply it to the zakeke bin I made in GoLang to fetch the links
         */

        $zakekeFilters = implode(",", $filters);
        $response = shell_exec("zakeke " . $zakekeFilters);

        $data = @json_decode($response, true);



        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            return redirect()->back()->withErrors('Zakeke API seems to be down, try again later!');
        } else {
            /*
             * Now start loadingthrough order again, and set their graphic.
             */


            $ctx = stream_context_create(array(
                'http' =>
                array(
                    'timeout' => 300,  //1200 Seconds is 20 Minutes
                )
            ));

            foreach ($orders as $order) {


                foreach ($order->items as $item) {

                    // Make sure the batch is not empty
                    if ($item->batch_number !== "") {

                        /*
                         * Ensure it exists, and the PNG link is not empty
                         */
                        if (isset($data[$order->short_order]['Links'][0]['PDF']) && $data[$order->short_order]['Links'][0]['PDF'] !== "") {



                            $link = $data[$order->short_order]['Links'][0]['PDF'];
                            $linkEncoded = base64_encode($link);
                            $batch = $item->batch_number;
                            $id = $item->id;
                            file_get_contents("http://order.monogramonline.com/lazy/link?link=$linkEncoded&batch_number=$batch&item_id=$id", false, $ctx);
                        }
                    }
                }
            }
        }

        $message = "We have successfully fetched the graphic for the following orders:</br> " . $this->pretty($filters);

        if (isset($stats['NOT_FOUND']) && count($stats['NOT_FOUND']) >= 1) {
            //    $message .= "\nOrders that we couldn't be found: " . $this->pretty($stats['NOT_FOUND']);
        }
        if (isset($stats['STATUS_ISSUE']) && count($stats['STATUS_ISSUE']) >= 1) {
            $message .= "\nOrders that status did not match filter: " . $this->pretty($stats['STATUS_ISSUE']);
        }
        if (isset($stats['QUANTITY_MORE_THAN_ONE']) && count($stats['QUANTITY_MORE_THAN_ONE']) >= 1) {
            $message .= "\nOrders we couldn't process because it had multiple lines: " . $this->pretty($stats['QUANTITY_MORE_THAN_ONE']);
        }
        return response()->json([
            'message' => $message,
            'status' => 201,
        ], 201);
    }

    //exportData funtion
    public function exportData(Request $request)
    {
        $result['qb_summary'] = Ship::join('stores', 'shipping.store_id', '=', 'stores.store_id')
            ->selectRaw('stores.store_id, stores.store_name, COUNT(*) as count')
            ->whereNull('shipping.qb_export')
            ->where('stores.qb_export', '1')
            ->where('shipping.is_deleted', '0')
            ->groupBy('store_id', 'stores.store_name')
            ->get();

        $result['csv_summary'] = Ship::join('stores', 'shipping.store_id', '=', 'stores.store_id')
            ->selectRaw('shipping.store_id, stores.store_name, COUNT(*) as count')
            ->whereNull('shipping.csv_export')
            ->where('stores.ship', '4')
            ->where('shipping.is_deleted', '0')
            ->groupBy('store_id', 'stores.store_name')
            ->get();

        $loadDrop = $request->get("drop", false);

        $storesNew =  Cache::remember("stores_all", 1, function () {
            return Store::all();
        });
        $file = "/var/www/order.monogramonline.com/Store.json";

        $data = [];
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
        }

        $dropship = [];


        if ($loadDrop) {
            foreach ($storesNew as $storeD) {
                if (isset($data[$storeD->store_name]) && $data[$storeD->store_name]['DROPSHIP']) {

                    $id = $storeD->store_id;


                    /*
                         * A new implementation of no cache, avoid issue where item are old
                         */
                    $toAdd = Order::with("items", "customer", "items.shipInfo")
                        ->whereHas("items", function ($query) use ($id) {
                            return $query->where("store_id", $id)
                                ->whereIn("child_sku", Cache::get('SKU_TO_INVENTORY_ID')['ALL'])
                                ->withinDate(Carbon::createFromDate(2021, 11, 10)->toDateString(), Carbon::now()->addMonth(5)->toDateString());
                        })
                        ->where("order_status", "<=", "4")
                        ->whereNotIn("id", Cache::get("SHIPMENT_CACHE"))
                        ->get();

                    if (count($toAdd) != 0) {
                        Cache::forget("stores_items_$id");
                        Cache::add("stores_items_$id", $toAdd, 60 * 24);
                        $total[] = $toAdd;
                    }
                    // ---------- Ends here

                    $temp = Cache::get("stores_items_$id");

                    if (count($temp) >= 1) {
                        $dropship[$storeD->id] = [
                            "ID" => $storeD->id,
                            "ID_REAL" => $storeD->store_id,
                            "NAME" => $storeD->store_name,
                            "COUNT" => count($temp)
                        ];
                    }
                }
            }
        }

        $result['dropship'] = $dropship;
        return response()->json([
            'message' => '',
            'status' => 200,
            'data' => $result,
        ], 200);
    }

    public function qbExport(Request $request)
    {

        if (!$request->has('store_ids') || !$request->has('start_date') || !$request->has('end_date')) {
            return redirect()->back()->withInput()->withErrors('Stores and dates required to create Quickbooks export');
        }

        $shipments = Ship::with('items', 'user')
            ->whereIn('store_id', $request->get('store_ids'))
            ->where('transaction_datetime', '>=', $request->get('start_date') . ' 00:00:00')
            ->where('transaction_datetime', '<=', $request->get('end_date') . ' 23:59:59')
            ->where('is_deleted', '0')
            ->get();

        $pathToFile = Quickbooks::export($shipments);

        $ids = $shipments->pluck('id')->toArray();

        Ship::whereIn('id', $ids)->update(['qb_export' => '1']);

        if ($pathToFile != null) {
            return response()->download($pathToFile)->deleteFileAfterSend(false);
        }
    }

    public function qbCsvExport(Request $request)
    {

        if (!$request->has('store_ids') || !$request->has('start_date') || !$request->has('end_date')) {
            return redirect()->back()->withInput()->withErrors('Stores and dates required to create CSV export');
        }

        try {
            $shipments = Ship::join('items', 'items.tracking_number', '=', 'shipping.tracking_number')
                ->join('orders', 'items.order_5p', '=', 'orders.id')
                ->whereIn('shipping.store_id', $request->get('store_ids'))
                ->where('shipping.transaction_datetime', '>=', $request->get('start_date') . ' 00:00:00')
                ->where('shipping.transaction_datetime', '<=', $request->get('end_date') . ' 23:59:59')
                ->where('shipping.is_deleted', '0')
                //                ->limit(5)
                //                ->selectRaw('sum(items.item_quantity) as sum, count(items.id) as count')
                ->get();
            //                ->get(['item_code', 'item_quantity', 'item_unit_price', 'purchase_order', 'order_date','transaction_datetime']);


            //                $shipments = Ship::  join('items', 'items.tracking_number', '=', 'shipping.tracking_number')
            //                ->join('orders', 'items.order_5p', '=', 'orders.id')
            ////                ->whereIn('shipping.store_id', $request->get('store_ids'))
            //                ->where('orders.order_date', '>=', $request->get('start_date') . ' 00:00:00')
            //                ->where('orders.order_date', '<=', $request->get('end_date') . ' 23:59:59')
            //                ->where('orders.is_deleted', '0')
            //                ->limit(5)
            //                ->get(['item_code', 'item_quantity', 'item_unit_price', 'purchase_order', 'order_date','transaction_datetime']);
            //
            ////            $shipments = Item::with('order')
            ////            $shipments = Item::with('order')
            //            $shipments = Item::with('order')
            //            ->where('is_deleted', '0')
            //                ->searchStore('524339241')
            //                ->searchStatus('2')
            //                ->searchSection($request->get('section'))
            //                ->searchOrderDate($request->get('start_date'), $request->get('end_date'))
            ////                ->selectRaw('sum(items.item_quantity) as sum, count(items.id) as count')
            ////                ->limit(5)
            ////                ->pluck('id')
            ////                ->get(['items.item_code', 'items.item_quantity', 'items.item_unit_price', 'order.purchase_order', 'order.order_date','order.created_at']);
            ////                ->select('items.item_code', 'items.item_quantity','items.item_unit_price')
            //            ->get();

            set_time_limit(0);
            $pathToFile = Quickbooks::csvExport($shipments);

            if ($pathToFile != null) {
                return response()->download($pathToFile)->deleteFileAfterSend(false);
            }
        } catch (Exception $e) {
            Log::error('Error Creating qbCsvExport - ' . $e->getMessage());
        }
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

    public function orderStoreOption()
    {
        $data = [];
        $store = Store::where('is_deleted', '0')
            ->where('input', '3')
            ->orderBy('sort_order')
            ->get();
        info($store);
        foreach ($store as $value) {
            $data[] = [
                'value' => $value['store_id'],
                'label' => $value['store_name'],
            ];
        }
        return $data;
    }

    public function trackingStoreOption()
    {
        //TODO : get store from json file
        $file = "/var/www/order.monogramonline.com/Store.json";
        $data = [];
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
        }
        $import_storesTracking = [];
        $storeNames = [];
        foreach ($data as $name => $dt) {
            if ($dt['DROPSHIP_IMPORT']) {
                $storeNames[] = $name;
            }
        }
        if (count($storeNames) !== 0) {
            $import_storesTracking = Store::whereIn("store_name", $storeNames)
                ->get();
        }
        $res = [];
        foreach ($import_storesTracking as $value) {
            $res[] = [
                'value' => $value['store_name'],
                'label' => $value['store_id'],
            ];
        }
        return $res;
    }
}
