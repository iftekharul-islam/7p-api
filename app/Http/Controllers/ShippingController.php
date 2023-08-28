<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Item;
use App\Models\Order;
use App\Models\Ship;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Ship\Shipper;

class ShippingController extends Controller
{
    public static $search_in = [
        'unique_order_id' => 'Package ID',
        'mail_class' => 'Shipped Via',
        'order_id' => 'Order',
        'batch_number' => 'Batch Number',
        'tracking_number' => 'Tracking number',
        'tracking_type' => 'Tracking Type',
        'item_id' => 'Item id',
        'name' => 'Name',
        'address_one' => 'Address 1',
        'company' => 'Company',
        'city' => 'City',
        'state' => 'State',
        'postal_code' => 'Postal code',
        'country' => 'Country',
        'email' => 'Email',
        'user' => 'Shipped By'
    ];

    public function index(Request $request)
    {
        $label = null;
        $error = null;
        $success = null;
        if ($request->has('label')) {
            $label = $request->get('label');
        } elseif ($request->has('unique_order_id')) {

            $filename = 'assets/images/shipping_label/' . $request->get('unique_order_id') . '.zpl';
            if (file_exists($filename)) {

                $label = file_get_contents($filename);
                $label = trim(preg_replace('/\n+/', ' ', $label));
                $pattern = '/"/';
                $label = preg_replace($pattern, '', $label);
                // TODO - need to emplement printer
                $success = "Need to implement printer";
                //                dd("Exist ",$filename, $label);
            } else {
                //                dd("No Exist ",$filename);
                $error = 'Label Not Found';
            }
        }

        if (
            !$request->has('search_for_first') && !$request->has('search_for_second')
            && !$request->has('start_date') && !$request->has('end_date')
        ) {
            $start_date = date("Y-m-d");
        } else {
            $start_date = $request->get('start_date');
        }

        if ($request->has('unique_order_id')) {
            $ships = Ship::with('items.batch', 'items.order.customer', 'user')
                ->searchStoreId($request->get('store_id'))
                ->where('is_deleted', '0')
                ->where('unique_order_id', $request->get('unique_order_id'))
                ->groupBy('tracking_number')
                ->latest('transaction_datetime')
                ->paginate(10);
        } else {
            $ships = Ship::with('items.batch', 'items.order.customer', 'user')
                ->where('is_deleted', '0')
                ->searchCriteria($request->get('search_for_first'), $request->get('search_in_first'))
                ->searchCriteria($request->get('search_for_second'), $request->get('search_in_second'))
                ->searchStoreId($request->get('store_id'))
                ->searchWithinDate($start_date, $request->get('end_date'))
                // postmark_date transaction_datetime
                ->groupBy('tracking_number')
                ->latest('transaction_datetime')
                ->paginate(10);
        }

        $yesterday = $last30 = date("Y-m-d H:i:s", strtotime('-1 days'));

        return response()->json([
            'message' => $error ?? $success,
            'ships' => $ships,
            'label' => $label,
            'yesterday' => $yesterday,
            'status' => $error ? 203 : ($success ? 201 : 200)
        ], 201);
    }

    public function searchInOption()
    {
        $options = [];
        foreach (static::$search_in as $key => $value) {
            $options[] = [
                'label' => $value,
                'value' => $key,
            ];
        }
        return $options;
    }

    public function manualShip(Request $request)
    {
        /*
         * Stops orders from being duplicated
         */

        //  TODO need to optimize time
        $track = $request->get('track_number', "ERR");

        if (Cache::has("TRACKING_DUPLICATE_$track")) {
        } else {
            Cache::add("TRACKING_DUPLICATE_$track", $track, 60 * 3);
        }

        if (strlen($request->get('track_number')) > 0) {

            $shipper = new Shipper;

            $info = $shipper->enterTracking(
                $request->get('track_item_id'),
                $request->get('track_order_id'),
                $request->get('track_number'),
                $request->get('method')
            );

            if (is_array($info)) {
                $shipper->setOrderFulfillment($request->get('track_shopify_order_id'), $request->get('track_shopify_item_line_id'), $request->get('track_shopify_item_quantity'), $request->get('track_number'), $request->get('method')); // method = $trackingCompany
                return redirect()->action('ShippingController@index', [
                    'unique_order_id' => $info['unique_order_id'],
                    'reminder' => $info['reminder']
                ]);
            } else {
                return response()->json([
                    'message' => $info,
                    'status' => 203
                ], 203);
                // return redirect()->back()->withErrors($info);
            }
        } else {
            return response()->json([
                'message' => 'Tracking number not set',
                'status' => 203
            ], 203);
            // return redirect()->back()->withErrors(['error' => "Tracking number not set"]);
        }
        return response()->json([
            'message' => 'Updated Successfully',
            'status' => 201
        ], 201);
    }

    // TODO: Emergency - Resolve this function and optimize the code
    public function shipItems(Request $request)
    {
        if ($request->has('order_id') && $request->has('origin')) {
            $shipper = new Shipper;

            $packages = array();

            $item_ids = $request->get("selected-items-json", null);

            // if ($item_ids !== null) {
            //     $item_ids = json_decode($item_ids, true);
            // }
            // return $item_ids;

            $ounces = $request->get('ounces');
            $pounds = $request->get('pounds');

            if ($ounces == null || $pounds == null) {
                return response()->json([
                    'message' => 'Weight must be a number',
                    'status' => 203
                ], 203);
            }

            foreach ($pounds as $index => $pounds) {

                $weight = $pounds;

                if (isset($ounces[$index]) && $ounces[$index] != null) {

                    if (!is_numeric($weight) || !is_numeric($ounces[$index])) {
                        return response()->json([
                            'message' => 'Weight must be a number',
                            'status' => 203
                        ], 203);
                    }

                    $weight += $ounces[$index] / 16;
                }
                $packages[] = $weight;
            }

            if ($packages == []) {
                $packages[] = 0;
            }

            if ($request->get('origin') == 'QC' || $request->get('origin') == 'WAP') {
                $params = [];
                if ($request->get('location') === 'NY') {
                    $params = [
                        'from_address' => [
                            "company" => 'ALL INCLUSIVE',
                            "street1" => '481 Johnson AVE',
                            "street2" => 'A',
                            "city"    => 'Bohemia',
                            "state"   => 'NY',
                            "zip"     => '11716',
                            "country" => 'US',
                            "phone"   => '8563203210'
                        ]
                    ];
                }
            }

            if ($request->get('origin') == 'QC' && $request->has('batch_number')) {

                $batch = Batch::find($request->get('id'));

                if ($batch->batch_number != $request->get('batch_number')) {
                    return redirect()->route('qcShow', ['id' => $request->get('id'), 'batch_number' => $request->get('batch_number')])
                        ->withErrors(['error' => 'Batch Number not correct']);
                }

                //test complete
                info("A");
                $ship_info = $shipper->createShipment($request->get('origin'), $request->get('order_id'), $request->get('batch_number'), $packages, $item_ids, $params);
                info("B");

                if (is_array($ship_info) && isset($ship_info['reminder'])) {

                    return response()->json(['params' => [
                        'bin' => $request->get('bin'),
                        'order_id' => $request->get('order_id'),
                        'unique_order_id' => $ship_info['unique_order_id'],
                        'reminder' => $ship_info['reminder']
                    ], 'status' => 201, 'message' => 'Updated Successfully'], 201);

                    // return response()->json([
                    //     'message' => 'Updated Successfully',
                    //     'status' => 201
                    // ], 201);
                    // return redirect()->route(
                    //     'qcShow',
                    //     [
                    //         'id' => $request->get('id'),
                    //         'batch_number' => $request->get('batch_number'),
                    //         'unique_order_id' => $ship_info['unique_order_id'],
                    //         'label_order' => $request->get('order_id'),
                    //         'reminder' => $ship_info['reminder']
                    //     ]
                    // );
                } else if (is_array($ship_info) && $ship_info[0] == 'ambiguous') {

                    // $ambiguousAddress = $label[1];
                    // $customer_id = $label[2];
                    // $order_id = $request->get('order_id');
                    // $batch_number = $request->get('batch_number');
                    // $origin = $request->get('origin');
                    //
                    // $order = Order::find($order_id);
                    // $customer = $order->customer;
                    //
                    // return view('shipping.choose_address', compact('customer_id', 'ambiguousAddress', 'order_id', 'origin', 'batch_number', 'customer'));

                    return response()->json([
                        'message' => 'Address Validation Failed - Ambiguous address',
                        'status' => 203
                    ], 203);
                    // return redirect()->route('qcOrder', [
                    //     'id' => $request->get('id'),
                    //     'batch_number' => $request->get('batch_number'),
                    //     'order_5p' => $request->get('order_id')
                    // ])
                    //     ->withErrors(['error' => 'Address Validation Failed - Ambiguous address']);
                } else {
                    Log::info('1. ShipItems: ' . $ship_info);
                    return response()->json([
                        'message' => $ship_info,
                        'status' => 203
                    ], 203);
                    // return redirect()->route('qcOrder', [
                    //     'id' => $request->get('id'),
                    //     'batch_number' => $request->get('batch_number'),
                    //     'order_5p' => $request->get('order_id')
                    // ])
                    //     ->withErrors(['error' => $ship_info]);
                }
            } elseif ($request->get('origin') == 'WAP') {
                info("C");
                $ship_info = $shipper->createShipment($request->get('origin'), $request->get('order_id'), null, $packages, $item_ids, $params);
                info("D");

                if (is_array($ship_info) && isset($ship_info['reminder'])) {

                    return response()->json(['params' => [
                        'bin' => $request->get('bin'),
                        'order_id' => $request->get('order_id'),
                        'unique_order_id' => $ship_info['unique_order_id'],
                        'reminder' => $ship_info['reminder']
                    ], 'message' => 'Updated Successfully', 'status' => 201], 201);

                    return response()->json(['message' => 'Updated Successfully', 'status' => 201], 201);
                    // return redirect()->route('wapShow', [
                    //     'bin' => $request->get('bin'),
                    //     'order_id' => $request->get('order_id'),
                    //     'unique_order_id' => $ship_info['unique_order_id'],
                    //     'reminder' => $ship_info['reminder']
                    // ]);
                } else if (is_array($ship_info) && $ship_info[0] == 'ambiguous') {
                    return response()->json(['message' => 'Address Validation Failed - Ambiguous address', 'status' => 203], 203);
                    // return redirect()->route('wapShow', ['bin' => $request->get('bin'), 'order_id' => $request->get('order_id')])
                    //     ->withErrors(['error' => 'Address Validation Failed - Ambiguous address']);
                } else {
                    Log::info('2. ShipItems: ' . $ship_info);
                    return response()->json(['message' => $ship_info, 'status' => 203], 203);
                    // return redirect()->route('wapShow', ['bin' => $request->get('bin'), 'order_id' => $request->get('order_id')])
                    //     ->withErrors(['error' => $ship_info]);
                }
            } else if ($request->get('origin') == 'OR') {
                info("E");
                $ship_info = $shipper->createShipment(
                    $request->get('origin'),
                    $request->get('order_id'),
                    null,
                    $packages,
                    $item_ids,
                    $params
                );
                info("F");

                if (is_array($ship_info) && isset($ship_info['reminder'])) {

                    return response()->json(['message' => 'Shipment created', 'status' => 201], 201);

                    // return redirect()->route('orderShow', [
                    //     'order_id' => $request->get('order_id'),
                    //     'unique_order_id' => $ship_info['unique_order_id'],
                    //     'reminder' => $ship_info['reminder']
                    // ]);
                } else if (is_array($ship_info) && $ship_info[0] == 'ambiguous') {
                    return response()->json(['message' => 'Address Validation Failed - Ambiguous address', 'status' => 203], 203);
                    // return redirect()->route('orderShow', ['order_id' => $request->get('order_id')])
                    // ->withErrors(['error' => 'Address Validation Failed - Ambiguous address']);
                } else {
                    Log::info('3. ShipItems: ' . $ship_info);
                    return response()->json(['message' => $ship_info, 'status' => 203], 203);
                    // return redirect()->route('orderShow', ['order_id' => $request->get('order_id')])
                    //     ->withErrors(['error' => $ship_info]);
                }
            } else {
                Log::error('4. ShipItems: Parameter error');
                return response()->json(['message' => 'Parameter error', 'status' => 203], 203);
            }
        } else {
            Log::info('5. ShipItems: Origin or order_id not set');
            return response()->json(['message' => 'Origin or order_id not set.', 'status' => 203], 203);
        }
    }

    public function shipmentReturned(Request $request)
    {
        if (!isset($request->tracking_number)) {
            return response()->json([
                'message' => 'Tracking number not found!',
                'status' => 203
            ], 203);
        }

        $items = Item::where('tracking_number', $request->get('tracking_number'))
            ->where('is_deleted', '0')
            ->get();

        foreach ($items as $item) {
            $item->tracking_number = NULL;
            $item->item_status = 'reshipment';
            $item->save();
        }

        $shipment = Ship::where('tracking_number', $request->tracking_number)
            ->where('is_deleted', '0')
            ->first();

        if (!$shipment) {
            return response()->json([
                'message' => 'Shipment not found',
                'status' => 203
            ], 203);
        }

        $order_id = $shipment->order_number;

        $shipment->is_deleted = '1';
        $shipment->save();

        $order = Order::find($order_id);

        if (!$order) {
            $order = Order::where('order_id', $order_id)->where('is_deleted', '0')->first();
        }

        if ($order) {
            $order->order_status = 10;
            $order->save();

            Order::note("Shipment returned. Tracking number " . $shipment->tracking_number, $order->id, $order->order_id);
        }
        return response()->json([
            'message' => 'Shipment returned Successfully',
            'data' => [
                'order_id' => $order_id
            ],
            'status' => 201
        ], 201);

        return redirect()->action('OrderController@details', ['order_id' => $order->id]);
    }

    public function voidShipment(Request $request)
    {
        if ($request->ship_id == null) {
            return response()->json([
                'message' => 'Shipment ID Required',
                'status' => 203
            ], 203);
        }

        $shipper = new Shipper;
        $response = $shipper->voidShipment($request->ship_id);

        return response()->json([
            'message' => "Shipment Voided",
            'status' => 201
        ], 201);
    }
}
