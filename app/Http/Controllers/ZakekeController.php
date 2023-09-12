<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\BatchNote;
use App\Http\Other\Maker;
use App\Models\Item;
use App\Models\Section;
use App\Models\StoreItem;
use App\Models\Wap;
use Illuminate\Contracts\Validation\ValidationException;
use Illuminate\Http\Request;
use App\Models\Option;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use LaravelShipStation\ShipStation;
use Market\Dropship;
use Market\ShipStationImport;
use Ship\Batching;
use Ship\CSV;

class ZakekeController extends Controller
{

    const SHIP_STATION_API_URL = "https://ssapi.shipstation.com/";
    const SHIP_STATION_API_KEY = "8f6fd3ba674246bea607af316e4cd311";
    const SHIP_STATION_API_SECRET = "12554651d87449c5acca216568a5d4e6";

    public function test1()
    {
        $order = Order::where("id", "1171393")
            ->with("items")
            ->get();

        $notes = \App\Models\BatchNote::where("batch_number", "637537")->get();

        foreach ($notes as $note) {
            if (stripos($note->note, "(automatically from link)") !== false) {
            }
        }

        // dd($order);
    }

    public function test2(Request $request)
    {

        $order = Order::where("short_order", 100071)->first();

        $this->setOrderAsShippedShipStation($order->short_order, "9405509699939108473525");
    }

    public function customBatch()
    {

        $order = \request()->get("order");
        $order = Order::with('items', 'customer', 'store')
            ->where("id", $order)
            ->first();

        if (!$order) {
            return redirect()->back()->withErrors("The order cannot be found or an error happened");
        }

        Batching::auto(0, [$order->store->store_id], 1, $order->id);

        return redirect()->back()->withSuccess('Order has been successfully batched!');
    }

    public static function hasSure3D(string $sku, Request $request)
    {

        parse_str("search_for_first=$sku&contains_first=in&search_in_first=child_sku&search_for_second=&contains_second=in&search_in_second=&search_for_third=&contains_third=in&search_in_third=&search_for_fourth=&contains_fourth=in&search_in_fourth=&active=0&sku_status=&batch_route_id=&sure3d=", $dt);
        $request->merge($dt);

        $options = Option::with('product', 'route.template', 'inventoryunit_relation.inventory', 'design')
            ->leftjoin('inventory_unit', 'inventory_unit.child_sku', '=', 'parameter_options.child_sku')
            ->searchIn($request->get('search_for_first'), $request->get('contains_first'), $request->get('search_in_first'), $request->get('stockno'))
            ->searchIn($request->get('search_for_second'), $request->get('contains_second'), $request->get('search_in_second'), $request->get('stockno'))
            ->searchIn($request->get('search_for_third'), $request->get('contains_third'), $request->get('search_in_third'), $request->get('stockno'))
            ->searchIn($request->get('search_for_fourth'), $request->get('contains_fourth'), $request->get('search_in_fourth'), $request->get('stockno'))
            ->searchRoute($request->get('batch_route_id'))
            ->searchActive($request->get('active'))
            ->searchStatus($request->get('sku_status'))
            ->searchSure3d($request->get('sure3d'))
            ->selectRaw('parameter_options.*, inventory_unit.stock_no_unique')
            ->groupBy('parameter_options.child_sku')
            ->orderBy('parameter_options.parent_sku', 'ASC')
            ->first();

        return (bool)$options->sure3d;
    }



    public static function getSkuPrice(string $sku, Request $request)
    {

        parse_str("search_for_first=$sku&contains_first=in&search_in_first=child_sku&search_for_second=&contains_second=in&search_in_second=&search_for_third=&contains_third=in&search_in_third=&search_for_fourth=&contains_fourth=in&search_in_fourth=&active=0&sku_status=&batch_route_id=&sure3d=", $dt);
        $request->merge($dt);

        $options = Option::with('product', 'route.template', 'inventoryunit_relation.inventory', 'design')
            ->leftjoin('inventory_unit', 'inventory_unit.child_sku', '=', 'parameter_options.child_sku')
            ->searchIn($request->get('search_for_first'), $request->get('contains_first'), $request->get('search_in_first'), $request->get('stockno'))
            ->searchIn($request->get('search_for_second'), $request->get('contains_second'), $request->get('search_in_second'), $request->get('stockno'))
            ->searchIn($request->get('search_for_third'), $request->get('contains_third'), $request->get('search_in_third'), $request->get('stockno'))
            ->searchIn($request->get('search_for_fourth'), $request->get('contains_fourth'), $request->get('search_in_fourth'), $request->get('stockno'))
            ->searchRoute($request->get('batch_route_id'))
            ->searchActive($request->get('active'))
            ->searchStatus($request->get('sku_status'))
            ->searchSure3d($request->get('sure3d'))
            ->selectRaw('parameter_options.*, inventory_unit.stock_no_unique')
            ->groupBy('parameter_options.child_sku')
            ->orderBy('parameter_options.parent_sku', 'ASC')
            ->first();

        return $options->product->product_price;
    }

    public static function getInventoryInformation(string $sku)
    {

        $request = \request();
        parse_str("search_for_first=$sku&contains_first=in&search_in_first=child_sku&search_for_second=&contains_second=in&search_in_second=&search_for_third=&contains_third=in&search_in_third=&search_for_fourth=&contains_fourth=in&search_in_fourth=&active=0&sku_status=&batch_route_id=&sure3d=", $dt);
        $request->merge($dt);

        $options = Option::with('product', 'route.template', 'inventoryunit_relation.inventory', 'design')
            ->leftjoin('inventory_unit', 'inventory_unit.child_sku', '=', 'parameter_options.child_sku')
            ->searchIn($request->get('search_for_first'), $request->get('contains_first'), $request->get('search_in_first'), $request->get('stockno'))
            ->searchIn($request->get('search_for_second'), $request->get('contains_second'), $request->get('search_in_second'), $request->get('stockno'))
            ->searchIn($request->get('search_for_third'), $request->get('contains_third'), $request->get('search_in_third'), $request->get('stockno'))
            ->searchIn($request->get('search_for_fourth'), $request->get('contains_fourth'), $request->get('search_in_fourth'), $request->get('stockno'))
            ->searchRoute($request->get('batch_route_id'))
            ->searchActive($request->get('active'))
            ->searchStatus($request->get('sku_status'))
            ->searchSure3d($request->get('sure3d'))
            ->selectRaw('parameter_options.*, inventory_unit.stock_no_unique')
            ->groupBy('parameter_options.child_sku')
            ->orderBy('parameter_options.parent_sku', 'ASC')
            ->first();

        return $options;
    }

    public function skuInfo()
    {
        $data = self::getInventoryInformation(\request()->get("sku"));

        $sections = Section::where('is_deleted', '0')
            ->get()
            ->pluck('section_name', 'id');

        dd($data->inventoryunit_relation->first()->inventory, $sections->toArray());
        // dd($data->inventoryunit_relation->first()->inventory);
    }

    public function fetchFromZakekeCLI($type, $order)
    {

        $response = null;

        if ($type === "axe") {
            $response = shell_exec("zakeke -user 65580 -key zccXIpB1k2J-quu2BBbwuNZVpvussjoWgTJpCS1lYyM. -data " . $order->short_order);
        } else {
            if ($type === "pws") {
                $response = shell_exec("zakeke -user 44121 -key 2d91PpFG6QJ0NmXsImWCXSzAMPCiRwMuX6D7DUHSIcM. -data " . $order->short_order);
            }
        }
        return $response;
    }

    /*
     * This is being used by the cron job, to automatically
     * fetch the graphics for PWS & Axe n Co
     */
    public function fetchAll(string $type)
    {
        //
        //        if (!Cache::get("ZAKEKE_" . strtoupper($type))) {
        //
        //            Log::info("Zakeke Status was off for  " . print_r(\request()->all(), true));
        //
        //            dd(
        //                [
        //                    "Status" => true,
        //                    "Message" => "The cronjob has been turned off, cannot fetch"
        //                ]
        //            );
        //        }

        /*
         * Turned on, can now continue
         */

        if (strtolower($type) == "axe") {
            $orders = Order::with("items")
                ->where('is_deleted', '0')
                ->storeId("axe-co")
                ->whereIn("order_status", [23]) //23 = other hold, 4 = to be processed
                ->get();
        } else {
            if (\request()->has("switch_store")) {
                $orders = Order::with("items")
                    ->where('is_deleted', '0')
                    ->storeId("axe-co")
                    ->whereIn("order_status", [23]) //23 = other hold, 4 = to be processed
                    ->get();
            } else {
                $orders = Order::with("items")
                    ->where('is_deleted', '0')
                    ->storeId("Etsy")
                    ->whereIn("order_status", [23, 4])
                    ->get();
            }
        }

        //        foreach ($orders as $order) {
        //            foreach ($order->items as $item) {
        //                $b = Batch::where("batch_number", $item->batch_number)->first();
        //
        //                $b->station_id = 295;
        //                $b->save();
        //            }
        //        }
        //
        //        $batch = Batch::where("batch_number", 653904)->get();
        //        dd("stop here", count($orders), $batch);



        //        $newOrders = [];
        //
        //        foreach ($orders as $order) {
        //            if($order->id == 1185551) {
        //                $newOrders[] = $order;
        //            }
        //        }
        //
        //        $testOrder = Order::where("id", 1185551)->first();
        //
        //        $options = json_decode($testOrder->items[0]->item_option, true);
        //        dd($options, isset($options['PWS Zakeke']));


        // Working filter

        //        $orders = $orders->filter(function ($order) {
        //           return $order->short_order == 2623287047;
        //        });

        //        $newOrder = [];
        //        foreach ($orders as $order) {
        //            $newOrder[] = $order->short_order;
        //        }
        //        dd($newOrder);


        //    dd($orders[0]->items[0]);

        $filteredNum = count($orders);


        //    $orderBefore = clone $orders;
        //    $orders = $orders->filter(function ($order) {
        //        if(count($order->items) >= 2) {
        //            return true;
        //        }
        //
        //        foreach ($order->items as $item) {
        //            if($item->item_quantity >= 2) {
        //                return true;
        //            }
        //        }
        //
        //        return false;
        //    });



        $before = [];
        foreach ($orders as $order) {
            $temp = (string) $order->short_order;
            if (strlen($temp) > 5) {
                $before[] = $order->short_order;
            }
        }

        $filteredNum = $filteredNum - count($before);
        $zakekeFilters = implode(",", $before);


        $hasGraphic = [];
        $skipped = [];
        $willNotUpdate = [];


        if ($type === "axe") {
            $response = shell_exec("zakeke -user 65580 -key zccXIpB1k2J-quu2BBbwuNZVpvussjoWgTJpCS1lYyM. -data " . $zakekeFilters);
        } else {
            if ($type === "pws") {
                $response = shell_exec("zakeke -user 44121 -key 2d91PpFG6QJ0NmXsImWCXSzAMPCiRwMuX6D7DUHSIcM. -data " . $zakekeFilters);
            } else {
                $response = null;
            }
        }

        $data = @json_decode($response, true);


        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            Log::info("Data from zakeke returned null " . print_r(\request()->all(), true));
            return response()->json(['Zakeke API seems to be down, try again later!']);
        } else {
            foreach ($orders as $order) {

                foreach ($order->items as $index => $item) {

                    // Make sure the batch is not empty
                    if ($item->batch_number !== "") {

                        if (!isset($data[$order->short_order])) {
                            continue;
                        }

                        $options = json_decode($item->item_option, true);


                        /*
                         * Check to make sure the item graphic has not yet been updated
                         */
                        if (isset($options['Internal_Zakeke_Fetch'])) {
                            //                        $date = Carbon::parse($options['Internal_Zakeke_Fetch']);
                            //
                            //                        if($date instanceof Carbon) {
                            //                            if($date->day == Carbon::now()->day) {
                            //                                $skipped[$order->id] = $options;
                            //                               continue;
                            //                            }
                            //                        }

                        }


                        /*
                         * Checks and fixes the index of the item if not exist
                         */
                        if ($index !== 0 && !isset($data[$order->short_order]['Links'][$index]['PDF']) or $data[$order->short_order]['Links'][$index]['PDF'] === "") {
                            $index = 0;
                        }

                        /*
                         * Ensure it exists, and the PNG link is not empty
                         */

                        if (isset($data[$order->short_order]['Links'][$index]['PDF']) && $data[$order->short_order]['Links'][$index]['PDF'] !== "") {


                            $link = $data[$order->short_order]['Links'][$index]['PDF'];


                            $linkEncoded = base64_encode($link);
                            $batch = $item->batch_number;
                            $id = $item->id;

                            $hasGraphic[$order->id] = "https://order.monogramonline.com/orders/details/" . $order->id;


                            if ($order->order_status !== 4) {
                                $order->order_status = 4;
                                $order->save();
                            }

                            $ctx = stream_context_create(array(
                                'http' =>
                                array(
                                    'timeout' => 1200,  //1200 Seconds is 20 Minutes
                                )
                            ));

                            $options['Custom_EPS_download_link'] = $link;
                            $options['Internal_Zakeke_Fetch'] = Carbon::now()->toDateTimeString();

                            $item->item_option = json_encode($options);
                            $item->save();

                            file_get_contents("http://order.monogramonline.com/lazy/link?link=$linkEncoded&batch_number=$batch&item_id=$id&updated_by=Cron", false, $ctx);
                        }
                    }
                }
                // sleep(1);
            }
        }


        Log::info("---------------------------------------");
        Log::info("          ZAKEKE MASS                  ");
        Log::info("Successfully fetched " . count($hasGraphic) . " out of " . count($orders));
        Log::info("Total Orders (did not match filter) " . abs($filteredNum));
        Log::info("Total Graphic Updated" . count($hasGraphic));
        Log::info("Orders Ids Updated" . print_r($hasGraphic, true));
        Log::info("Orders that was in array " . implode(",", array_keys($before)));
        Log::info("Skipped order (already updated) " . implode(",", array_keys($skipped)));
        Log::info("---------------------------------------");

        return response()->json(
            [
                "Status" => true,
                "Message" => "Successfully fetched " . count($hasGraphic) . " out of " . count($orders),
                "Total Orders (did not match filter)" => abs($filteredNum),
                "Total Graphic Updated" => count($hasGraphic),
                "Orders that was in array" => implode(",", array_values($before)),
                "Orders Ids Updated" => implode(",", array_keys($hasGraphic)),
                "Data" => $hasGraphic,
                "Skipped order (already updated)" => $skipped,
                "Will not update" => $willNotUpdate
            ]
        );
    }

    public function require_all_files()
    {
        require("/var/www/order.monogramonline.com/library/LaravelShipStation/ShipStation.php");
    }

    public static function getShipStationCarriers(): array
    {

        $tag = "41195"; // Personalized;
        $username = self::SHIP_STATION_API_KEY;
        $password = self::SHIP_STATION_API_SECRET;


        $curl = curl_init();


        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://ssapi.shipstation.com/carriers",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_USERPWD => $username . ":" . $password
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        $data = json_decode($response, true);

        return $data ?? [];
    }

    public static function getShipStationOrders(): array
    {

        $tag = "41195"; // Personalized;
        $username = self::SHIP_STATION_API_KEY;
        $password = self::SHIP_STATION_API_SECRET;


        $curl = curl_init();


        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://ssapi.shipstation.com/orders/listbytag?orderStatus=awaiting_shipment&tagId=$tag&page=1&pageSize=500&sortBy=233671",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_USERPWD => $username . ":" . $password
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        $data = json_decode($response, true);

        return $data ?? [];
    }


    public static function setOrderAsImportedShipStation($orderId)
    {
        $curl = curl_init();

        $dt = ZakekeController::getShipStationOrders();

        /*
         * Processes/get the real real order ID from ship station
         */
        foreach ($dt['orders'] as $theOrder) {
            if ($theOrder['orderNumber'] === $orderId) {
                $orderId = $theOrder['orderId'];
                break;
            }
        }


        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://ssapi.shipstation.com/orders/addtag",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode(
                [
                    "orderId" => (int) $orderId,
                    "tagId" => 52335
                ]
            ),
            CURLOPT_USERPWD => self::SHIP_STATION_API_KEY . ":" . self::SHIP_STATION_API_SECRET,
            CURLOPT_HTTPHEADER => array(
                "Host: ssapi.shipstation.com",
                "Content-Type: application/json"
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        //  dd($response);
    }

    public static function setOrderAsShippedShipStation($orderId, $trackingNumber)
    {
        $curl = curl_init();

        $dt = ZakekeController::getShipStationOrders();

        /*
         * Processes/get the real real order ID from ship station
         */
        foreach ($dt['orders'] as $theOrder) {
            if ($theOrder['orderNumber'] === $orderId) {
                $orderId = $theOrder['orderId'];
                break;
            }
        }


        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://ssapi.shipstation.com/orders/markasshipped",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode(
                [
                    "orderId" =>  $orderId,
                    "carrierCode" => "usps",
                    "trackingNumber" => $trackingNumber,
                    "notifyCustomer" => true,
                    "notifySalesChannel" => true
                ]
            ),
            CURLOPT_USERPWD => self::SHIP_STATION_API_KEY . ":" . self::SHIP_STATION_API_SECRET,
            CURLOPT_HTTPHEADER => array(
                "Host: ssapi.shipstation.com",
                "Content-Type: application/json"
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        // dd($response);
    }

    public function shipStationCheckOrder()
    {

        $data = ZakekeController::getShipStationOrders();

        if ($data !== null && isset($data['orders'])) {
            $csvData = [];
            $line = [
                'order',
                'name',
                'address1',
                'address2',
                'city',
                'state',
                'zip',
                'country',
                'phone',
                'comment',
                'color',
                'sku',
                'child_sku',
                'qty',
                'price',
                'thumbnail',
                'graphic',
                // new entries
                'ship via',
                'Ship By Date',
                'pws_zakeke'
            ];

            $csvData[] = $line;

            /*
             * Loop through the order now
             */



            foreach ($data['orders'] as $order) {

                // For testing only
                //  if($order['orderNumber'] !== "2539606068") continue;

                foreach ($order['items'] as $item) {

                    /*
                     * Weird bug with ship station, inserts blank line items
                     */
                    if ($item['sku'] === null) continue;

                    $zipcode = $order['shipTo']['postalCode'];


                    if (stripos($zipcode, "-") !== false) {
                        $zipcode = explode("-", $zipcode)[0];
                    }

                    $itemInfo = StoreItem::searchStore("axe-co")
                        ->where('is_deleted', '0')
                        ->where("vendor_sku", $item['sku'])
                        ->first();

                    $price = $itemInfo['cost'];

                    if (!$price || $price == 0) {
                        $price = $item['price'] ?? 0;
                    }
                    $shipDate = Dropship::getShipDateFromStarting(Carbon::parse($order['createDate']))->toDateTimeString();

                    $status = false;
                    foreach ($order['tagIds'] as $id) {
                        if ($id == '64962') {
                            $status = true;
                        }
                    }

                    if ((bool) $status === true) {
                        $line = [
                            $order['orderNumber'],
                            $order['shipTo']['name'],
                            $order['shipTo']['street1'],
                            $order['shipTo']['street2'],
                            $order['shipTo']['city'],
                            $order['shipTo']['state'],
                            (string) $zipcode,
                            $order['shipTo']['country'],
                            $order['shipTo']['phone'] ?? "",
                            "", // $order['customerNotes'] ?? "", // he (Sholomi said remove it
                            "",
                            $item['sku'],
                            $item['sku'],
                            1, //$item["quantity"],  /* Quantity should always be 1, if >= 1, add another line item
                            $price,
                            $item['imageUrl'],
                            $item['imageUrl'],
                            $order['serviceCode'],
                            $shipDate,
                            'true' // PWS ESTY
                        ];
                    } else {
                        $line = [
                            $order['orderNumber'],
                            $order['shipTo']['name'],
                            $order['shipTo']['street1'],
                            $order['shipTo']['street2'],
                            $order['shipTo']['city'],
                            $order['shipTo']['state'],
                            (string) $zipcode,
                            $order['shipTo']['country'],
                            $order['shipTo']['phone'] ?? "",
                            "", // $order['customerNotes'] ?? "", // he (Sholomi said remove it
                            "",
                            $item['sku'],
                            $item['sku'],
                            1, //$item["quantity"],  /* Quantity should always be 1, if >= 1, add another line item
                            $price,
                            $item['imageUrl'],
                            $item['imageUrl'],
                            $order['serviceCode'],
                            $shipDate,
                        ];
                    }


                    /* -----------------------------------------------------------------------------------
                     * FYI                                                                               -
                     * In Excel you cannot have leading zeros in numbers, so it will ignore it           -
                     * -----------------------------------------------------------------------------------
                     */


                    if ($item["quantity"] > 1) {
                        /*
                         * Duplicate line items until it maxes the quantity
                         */
                        for ($i = 0; $i < $item['quantity']; $i++) {
                            $csvData[] = $line;
                        }
                    } else {
                        $csvData[] = $line;
                    }
                    unset($line);
                    unset($zipcode);
                    unset($status);
                }
            }


            $filename = 'ShipStation_' . "Axe" . '_' . date('ymd_His') . '.' . uniqid() . '.csv';
            $csv = new CSV;

            $path = storage_path() . "/EDI/General/ShipStation/";

            $path = $csv->createFile($csvData, $path, null, $filename, ',');

            $import = new ShipStationImport();
            $import->importCsv($path);

            return response()->json(
                [
                    "Status" => true,
                    "Messages" => [
                        "Successfully pushed orders if there were any",
                        $import
                    ],
                    "Order Dump" => $path
                ]
            );
        } else {
            return response()->json(
                [
                    "Status" => false,
                    "Message" => "No orders were found, or we're being rate-limited by Ship Station"
                ]
            );
        }
    }
}
