<?php

namespace Market;

use App\Models\Collection;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\StoreItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Ship\CSV;
use Ship\Shipper;

class Dropship
{



    public static $lookup =
    array(
        'upcg' => ['UP', 'S_GROUND'],
        'u3ds' => ['UP', 'S_3DAYSELECT'],
        'ufds' => ['UP', 'S_FIRST_CLASS'],
        'upds' => ['UP', 'S_PRIORITY'],
        'u2da' => ['UP', 'S_AIR_2DAY'],
        'u2aa' => ['UP', 'S_AIR_2DAYAM'],
        'UPSS' => ['UP', 'S_GROUND'],
        'UPSP' => ['UP', 'S_SUREPOST'],
        'USPL' => ['UP', 'S_SUREPOST'],
        'GCG' => ['UP', 'S_GROUND'],
        'GND' => ['UP', 'S_AIR_1DAY'],
        'GSE' => ['UP', 'S_AIR_2DAY'],
        'UNAS' => ['UP', 'S_AIR_1DAYSAVER'],
        'FESP' => ['FX', '_SMART_POST'],
        'FEHD' => ['FX', '_GROUND_HOME_DELIVERY'],
        'FECG' => ['FX', '_FEDEX_GROUND'],
        'FEES' => ['FX', '_FEDEX_EXPRESS_SAVER'],
        'FE2D' => ['FX', '_FEDEX_2_DAY'],
        'F2DA' => ['FX', '_FEDEX_2_DAY_AM'],
        'FESO' => ['FX', '_STANDARD_OVERNIGHT'],
        'FEPO' => ['FX', '_PRIORITY_OVERNIGHT'],
        'FEFO' => ['FX', '_FIRST_OVERNIGHT'],
    );

    public static $import =
    [
        'upcg' => [
            'carrier' => 'UP',
            'method' => 'S_GROUND'
        ],
        'ufds' => [
            'carrier' => 'UP',
            'method' => 'S_FIRST_CLASS'
        ],
        'upds' => [
            'carrier' => 'UP',
            'method' => 'S_PRIORITY'
        ],
        'u3ds' => [
            'method' => 'UP',
            'carrier' => 'S_3DAYSELECT'
        ],
        'u2da' => [
            'method' => 'UP',
            'carrier' => 'S_AIR_2DAY'
        ],
        'u2aa' => [
            'method' => 'UP',
            'carrier' => 'S_AIR_2DAYAM'
        ],
        'UPSS' => [
            'carrier' => 'UP',
            'method' =>  'S_GROUND'
        ],
        'UPSP' => [
            'carrier' => 'UP',
            'method' => 'S_SUREPOST'
        ],
        'USPL' => [
            'carrier' => 'UP',
            'method' => 'S_SUREPOST'
        ],
        'GCG' => [
            'carrier' => 'UP',
            'method' =>  'S_GROUND'
        ],
        'GND' => [
            'carrier' => 'UP',
            'method' => 'S_AIR_1DAY'
        ],
        'GSE' => [
            'carrier' => 'UP',
            'method' => 'S_AIR_2DAY'
        ],
        'UNAS' => [
            'carrier' => 'UP',
            'method' => 'S_AIR_1DAYSAVER'
        ],
        'FESP' => [
            'carrier' => 'FX',
            'method' => '_SMART_POST'
        ],
        'FEHD' => [
            'carrier' => 'FX',
            'method' => '_GROUND_HOME_DELIVERY'
        ],
        'FECG' => [
            'carrier' => 'FX',
            'method' => '_FEDEX_GROUND'
        ],
        'FEES' => [
            'carrier' => 'FX',
            'method' => '_FEDEX_EXPRESS_SAVER'
        ],
        'FE2D' => [
            'carrier' => 'FX',
            'method' => '_FEDEX_2_DAY'
        ],
        'F2DA' => [
            'carrier' => 'FX',
            'method' => '_FEDEX_2_DAY_AM'
        ],
        'FESO' => [
            'carrier' => 'FX',
            'method' => '_STANDARD_OVERNIGHT'
        ],
        'FEPO' => [
            'carrier' => 'FX',
            'method' => '_PRIORITY_OVERNIGHT'
        ],
        'FEFO' => [
            'carrier' => 'FX',
            'method' => '_FIRST_OVERNIGHT'
        ],
    ];

    public static $shippingConversion = [
        "S_GROUND" => "UP*S_GROUND",
        "S_3DAYSELEC" => "UP*S_3DAYSELECT",
        "S_FIRST_CLASS" => "US*FIRST_CLASS",
        "PRIORITY" => "US*PRIORITY",
        "S_PRIORITY" => "US*PRIORITY",
        "S_AIR_2DAY" => "UP*S_AIR_2DAY",
        "S_AIR_2DAYAM" => "UP*S_AIR_2DAYAM",
        "S_SUREPOST" => "UP*S_SUREPOST",
        "S_AIR_1DAY" => "UP*S_AIR_1DAY",
        "S_AIR_1DAYSAVER" => "UP*S_AIR_1DAYSAVER",
        "_SMART_POST" => "FX*_SMART_POST",
        "_GROUND_HOME_DELIVERY" => "FX*_GROUND_HOME_DELIVERY",
        "_FEDEX_GROUND" => "FX*_FEDEX_GROUND",
        "_FEDEX_EXPRESS_SAVER" => "FX*_FEDEX_EXPRESS_SAVER",
        "_FEDEX_2_DAY" => "FX*_FEDEX_2_DAY",
        "_FEDEX_2_DAY_AM" => "FX*_FEDEX_2_DAY",
        "_STANDARD_OVERNIGHT" => "FX*_STANDARD_OVERNIGHT",
        "_PRIORITY_OVERNIGHT" => "FX*_PRIORITY_OVERNIGHT",
    ];

    public static function getShipDateFromStarting(Carbon $starting_date, int $daysToShip = 3): Carbon
    {


        $finalDate = $starting_date;
        $finalDate->addDays($daysToShip);
        $canAddAdditional = true;

        /*
         * If sunday, add more days to it,
         * since sunday they do not open
         */
        if ($finalDate->dayOfWeek === Carbon::SUNDAY) {
            $finalDate->addDay(1);
            $canAddAdditional = false;
        }
        $yesterday = clone $finalDate;
        $yesterday->subDays(1);

        /*
         * Do not count yesterday,
         * so add another dat to it
         */
        if ($yesterday->dayOfWeek === Carbon::SUNDAY && $canAddAdditional) {
            $finalDate->addDay(1);
        }


        return $finalDate;
    }

    public static function handle(Store $store, $orders)
    {

        /*
         * Mark it as exported on the cache engine, actually, a json file.
         */
        $file = "/var/www/order.monogramonline.com/Shipment.json";

        $data = [];
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
        }
        foreach ($orders as $index => $order) {
            $data[] = $order->id;
        }
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
        Cache::put("SHIPMENT_CACHE", $data, 60 * 8);


        \Log::info("----------------------------");
        \Log::info("   DROP SHIP FILE EXPORT    ");
        \Log::info("----------------------------");

        $line = [];
        $lines = [];

        $line[] = "customer";
        $line[] = 'order_date';
        $line[] = 'po_number';
        $line[] = 'order';
        $line[] = 'name';
        $line[] = 'company';
        $line[] = 'address1';
        $line[] = 'address2';
        $line[] = 'city';
        $line[] = 'state';
        $line[] = 'post_code';
        $line[] = 'country';
        $line[] = 'phone';
        $line[] = 'item_sku';
        $line[] = 'child_sku';
        $line[] = 'quantity';
        $line[] = 'price';
        $line[] = 'vendor_sku';
        $line[] = 'ship_via';
        $line[] = 'ship_carrier';
        $line[] = 'tracking';
        $line[] = 'ship_date';
        $line[] = 'shipping_service_level_code';
        $line[] = 'shipping_internal_method';
        $line[] = 'shipping_internal_carrier';

        $lines[] = $line;


        $inventoryData = [];
        $file2 = "/var/www/order.monogramonline.com/Inventories.json";
        if (file_exists($file2)) {
            $inventoryData = json_decode(file_get_contents($file2), true);
        }
        $cacheInventoryIds = Cache::get('SKU_TO_INVENTORY_ID');

        foreach ($orders as $order) {

            /*
             * Loop through all the items in the orders,
             * so that we can add it to the array (of orders, so we can eventually export to XML)
             */
            foreach ($order->items as $item) {


                /*
                 * Ignore any other line items in the exporting process that
                 * item sku does not have the drop-ship option checked.
                 */
                if (!in_array($item->child_sku, Cache::get('SKU_TO_INVENTORY_ID')['ALL'])) {
                    continue;
                }


                $line = [];

                /*
                 * Getting the method and all
                 */
                if ($order->method != null) {
                    $method = substr($order->method, 2);
                    $service_code = array_search([$order->carrier, $order->method], self::$lookup);
                } else {
                    if ($order->method == "PRIORITY") {
                        $service_code = 'upds';
                        $method = "S_PRIORITY";
                    } else {
                        $method = 'MAIL INNOVATIONS';
                        $service_code = 'GND';
                    }
                }

                $line[] = $store->store_name; // customer
                $line[] = $order->order_date; // Order Date
                $line[] = $order->purchase_order ?? $order->id; // PO Number if not use order ID
                $line[] = $order->id; // Order ID
                $line[] = $order->customer->ship_full_name; // Name
                $line[] = $order->customer->ship_company_name; // Name (use first/last name)
                $line[] = $order->customer->ship_address_1; // Address 1
                $line[] = $order->customer->ship_address_2; // Address 1
                $line[] = $order->customer->ship_city; // City
                $line[] = $order->customer->ship_state; // State
                $line[] = $order->customer->ship_zip; // Post code
                $line[] = $order->customer->ship_country; // Country
                $line[] = $order->customer->ship_phone; // Phone
                $line[] = $item->child_sku; // Item SKU
                $line[] = $item->item_code; // Child SKU
                $line[] = $item->item_quantity; // Quantity

                $inventory = Inventory::where("id", $cacheInventoryIds[$item->child_sku] ?? 0)->first();

                if ($inventory) {
                    $line[] = $inventoryData[$cacheInventoryIds[$item->child_sku] ?? 0]['DROPSHIP_COST'];
                    $line[] = $inventoryData[$cacheInventoryIds[$item->child_sku] ?? 0]['DROPSHIP_SKU'];
                } else {
                    $line[] = $item->total; // PRICE FROM THE FILE SHIT
                    $line[] = $item->child_sku; // VENDOR SKU FROM THE FILE SHIT
                }

                $line[] = $method;
                $line[] = $order->carrier == "UP" ? "UPS" : $order->carrier; // NEW
                $line[] = ""; // Tracking number
                $line[] = ""; // Ship Date
                $line[] = $service_code; // Service code
                $line[] = $order->method; // method
                $line[] = $order->carrier; // method



                $lines[] = $line;
            }
        }

        $path = "";

        if (count($lines) > 0) {
            $filename = 'Shipment_' . $store->store_name . '_' . date('ymd_His') . '.csv';
            $csv = new CSV;

            $path = storage_path() . "/EDI/General/";

            $path = $csv->createFile($lines, $path, null, $filename, ',');
        }
        \Log::info("----------------------------");
        \Log::info("   DROP SHIP FILE EXPORT SUCCESSFUL   ");
        \Log::info("   STORE:" . $store->store_name);
        \Log::info("   STORE ID:" . $store->store_id);
        \Log::info("----------------------------");

        return $path;
    }

    public static function export(Request $request, Store $store)
    {

        $link = "";


        /*
         * Has to return an array,
         * That has the order ids, which will be used to show the others that was recently imported
         */

        $file = $request->file('file');
        $filename = 'Shipment_' . $store->store_name . '_' . date('ymd_His') . '.csv';
        $path =  $path = storage_path() . "/EDI/General/In/";

        move_uploaded_file($file, $path . $filename);

        $csv = new CSV;
        $data = $csv->intoArray($path . $filename, ",");

        $mappedKeys = $data[0];

        // Remove keys from array list, from csv
        unset($data[0]);
        $mappedData = [];

        /*
         * Now map the keys to the right key in the data array
         */



        foreach ($data as $key => $dt) {
            foreach ($dt as $secondKey => $dtData) {
                if (!isset($mappedKeys[$secondKey])) {
                    continue;
                }
                $mappedData[$key][$mappedKeys[$secondKey]] = $dtData;
            }
        }

        $orders = [];
        $ids = [];

        $ids = array_unique($ids);

        foreach ($mappedData as $itemsData) {
            $orders[$itemsData['order']][] = $itemsData;
            $ids[] = $itemsData['order'];
        }

        foreach ($orders as $orderId => $orderList) {
            foreach ($orderList as $order) {

                /*
                 * --------------------------------------|
                 * Not being used right now,             |
                 * but leaving it for future reference.  |
                 * ------------------------------------- |
                 */


                /*
                 * Logic here
                 * i.E: Mark the order as shipped
                 */

                $shipper = new Shipper;

                $info = $shipper->enterTracking(
                    'all',
                    $orderId,
                    $order['tracking'],
                    isset(self::$shippingConversion[$order['shipping_internal_method']]) ? self::$shippingConversion[$order['shipping_internal_method']] : ""
                );
            }
        }

        return [
            "orders" => $ids,
        ];
    }

    public static function getDropShipOrders()
    {

        $tag = "41195"; // Personalized;
        $username = "8f6fd3ba674246bea607af316e4cd311";
        $password = "12554651d87449c5acca216568a5d4e6";


        $curl = curl_init();


        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://ssapi.shipstation.com/orders/listbytag?orderStatus=awaiting_shipment&tagId=$tag&page=1&pageSize=10&sortBy=233671",
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
        ];

        $csvData[] = $line;

        /*
         * Loop through the order now
         */


        /**
         *  A small hack that tries to loop through all the orders
         *  and attempt to fix the zip code.
         *
         */

        //        foreach ($data['orders'] as $index => $order) {
        //
        //            $zipcode = $order['shipTo']['postalCode'];
        //
        //            if(stripos($zipcode, "-") !== false) {
        //                $data['orders'][$index]['shipTo']['postalCode'] = explode("-", $zipcode)[0];
        //            }
        //        }


        foreach ($data['orders'] as $order) {
            foreach ($order['items'] as $item) {


                $zipcode = $order['shipTo']['postalCode'];


                if (stripos($zipcode, "-") !== false) {
                    $zipcode = explode("-", $zipcode)[0];
                }

                $itemInfo = StoreItem::searchStore("axe-co")
                    ->where('is_deleted', '0')
                    ->where("vendor_sku", $item['sku'])
                    ->first();

                $price = $itemInfo['cost'] ?? 0;
                $shipDate = Dropship::getShipDateFromStarting(Carbon::parse($order['createDate']))->toDateTimeString();

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
                    "", // $order['customerNotes'] ?? "", // he said remove it
                    "",
                    $item['sku'],
                    $item['sku'],
                    $item["quantity"],
                    $price,
                    $item['imageUrl'],
                    $item['imageUrl'],
                    "have to work on this....",
                    $shipDate
                ];


                /* -----------------------------------------------------------------------------------
                 * FYI                                                                               -
                 * In Excel you cannot have leading zeros in numbers, so it will ignore it           -
                 * -----------------------------------------------------------------------------------
                 */


                //                if($line[6][0] == 0) {
                //                    $line[6] = "'" . $line[6] . "'";
                //                }

                $csvData[] = $line;
                unset($line);
                unset($zipcode);
            }
        }


        $filename = 'ShipStation_' . "Axe" . '_' . date('ymd_His') . '.' . uniqid() . '.csv';
        $csv = new CSV;

        $path = storage_path() . "/EDI/General/";

        $path = $csv->createFile($csvData, $path, null, $filename, ',');

        return $path;
    }
}
