<?php

namespace Market;

use App\Http\Controllers\ZakekeController;
use App\Order;
use App\Customer;
use App\Item;
use App\Ship;
use App\Product;
use App\Parameter;
use App\Store;
use App\StoreItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use LaravelShipStation\ShipStation;
use Monogram\Batching;
use Monogram\Helper;
use Monogram\CSV;
use Excel;
use Monogram\Sure3d;


class ShipStationImport
{
    protected $dir = '/EDI/';

    const CONVERSION  = [
        "usps_first_class_mail" => "US*FIRST_CLASS",
        "ups_ground_saver" => "UP*S_GROUND",
        "ups_ground" => "UP*S_GROUND",
        "ups_3_day_select" => "UP*S_3DAYSELECT",
        "ups_2nd_day_air" => "UP*S_AIR_2DAY",
        "ups_next_day_air_saver" => "UP*S_AIR_1DAYSAVER",
        "ups_next_day_air" => "UP*S_AIR_1DAY",
    ];

    public function pushTracking($order, $tracking) {

        $order = Order::where("id", $order)
            ->first();

                $username = ZakekeController::SHIP_STATION_API_KEY;
                $password = ZakekeController::SHIP_STATION_API_SECRET;

                $curl = curl_init();


                $orderId = "";
                $dt = ZakekeController::getShipStationOrders();

                /*
                 * Processes/get the real real order ID from ship station
                 */
                foreach ($dt['orders'] as $theOrder) {
                    if($theOrder['orderNumber'] === $order->short_order) {
                        $orderId = $theOrder['orderId'];
                        break;
                    }
                }

               $date = Carbon::now()->toDateTimeString();
                $fields = '{"orderNumber":"{ORDER}","carrierCode":"ups_walleted","shipDate":"{DATE}","trackingNumber":"{TRACKING}","notifyCustomer":true,"notifySalesChannel":true}';
                $fields = str_replace(
                    [
                        "{ORDER}",
                        "{DATE}",
                        "{TRACKING}",
                    ],
                    [
                        $orderId,
                        $date,
                        $tracking
                    ],
                    $fields
                );

                curl_setopt_array($curl, array(
                    CURLOPT_URL => "https://ssapi.shipstation.com/orders/markasshipped",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_POSTFIELDS => $fields,
                    CURLOPT_USERPWD => $username . ":" . $password,
                    CURLOPT_HTTPHEADER => array(
                        "Content-Type: application/json"
                    ),
                ));
               curl_setopt($curl, CURLOPT_POST, 1);

              $response = curl_exec($curl);
             curl_close($curl);
             $data = json_decode($response, true);

             dd($data);
           Order::note('Successfully updated tracking number in Ship Station to ' . $tracking, $order->id, $order->order_id);
    }

    public function importCsv($file) {

        if (!file_exists($file)) {
            return ['errors' => "Error, connection is not working", 'order_ids' => []];
        }

        $store = Store::where("store_id", "axe-co")->first();

        $csv = new CSV;
        $results = $csv->intoArray($file, ',');

        $error = array();
        $order_ids = array();
        $id = '';

        set_time_limit(0);

        $valid_keys = [ 'order',
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
            // Andre added
             'ship via',
             'Ship By Date',
            "pws_zakeke"
            //  'Status',
            //  'P1',
            // 'P2',
            // 'P3',
            //'P4',
            //  'P5',
            // 'P6',
        ];

        if (!$results[0] == $valid_keys) {
            $error[] = 'File does not have valid column headers';
            return ['errors' => $error, 'order_ids' => $order_ids];
        }

        foreach ($results as $line)  {

            if ($line[0] == 'order') {
                continue;
            }

            if (!isset($line[0]) || $line[0] == '') {
                $error[] = 'Order ID not set, first column is blank.';
                continue;
            }

         //   Log::info('ShipStation Import: Processing order ' . $line[0]);


            if ($id == '' || $line[0] != $id) {

                $previous_order = Order::join('customers', 'orders.customer_id', '=', 'customers.id')
                    ->where('orders.is_deleted', '0')
                    ->where('orders.order_id', $store->store_id . '-' . $line[0])
                    ->first();

                if ( $previous_order ) {
               //    Log::info('ShipStation Import : Order number already in Database ' . $line[0]);
                    $error[] = 'Order number already in Database ' . $line[0];
                    continue;
                }

                $order_5p = $this->insertOrder($store->store_id, $line);
                $order_ids[] = $order_5p;
                $id = $line[0];
            }

            $usePWSZakeke = false;
            if(isset($line[19])) {
                $usePWSZakeke = (bool) $line[19];
            }

            if (!$this->insertItem($store->store_id, $order_5p, $line, $usePWSZakeke)) {
                $error[] = $item_result;
            }

            $this->setOrderTotals($order_5p);

        }

        if (count($order_ids) == 0) {
            $error[] = 'No Orders Imported from File';
            return ['errors' => $error, 'order_ids' => $order_ids];
        }


        /*
         * Automatically batch them
         */
        $orders = Order::with('items', 'customer', 'store')
            ->whereIn('id', $order_ids)
            ->get();
        $store_ids = array_unique($orders->pluck('store_id')->toArray());

        foreach ($store_ids as $store_imported) {
            Batching::auto(0, $store_imported);
        }

        foreach ($orders as $order) {

            ZakekeController::setOrderAsImportedShipStation($order->short_order);
            Order::note('Successfully added tag ORDERED on Ship Station', $order->id, $order->order_id);

            /*
             * Batch the order, so Shlomi does not have to worry about it manually.
             */
            shell_exec("curl https://order.monogramonline.com/custom/batch?order=" . $order->id);
            $order->order_status = 23;
            $order->save();
        }

        return ['errors' => $error, 'order_ids' => $order_ids];
    }


    private function insertOrder($storeid, $data) {

        // -------------- Customers table data insertion started ----------------------//
        $customer = new Customer();

        $customer->order_id = $storeid . '-' . $data[0];
        $customer->ship_full_name = isset($data[1]) ? $data[1] : null;
        if (isset($data[1]) && $data[1] != '') {
            $customer->ship_last_name = (strpos($data[1], ' ') === false) ? '' : preg_replace('#.*\s([\w-]*)$#', '$1', $data[1]);

            $first = explode(" ", $data[1]);

            $first = $first[0] ?? "PLEASE_UPDATE_NAME_ERROR";
            $customer->ship_first_name = $first;
        }
        $customer->ship_address_1 = isset($data[2]) ? $data[2] : null;
        $customer->ship_address_2 = isset($data[3]) ? $data[3] : null;
        $customer->ship_city = isset($data[4]) ? $data[4] : null;
        $customer->ship_state = isset($data[5]) ? Helper::stateAbbreviation($data[5]) : null;
        $customer->ship_zip = isset($data[6]) ? $data[6] : null;
        $customer->ship_country = (isset($data[7])  && $data[7] != '') ? $data[7] : 'US';
        $customer->ship_phone = isset($data[8]) ? $data[8] : null;

        $customer->bill_email = 'DROPSHIP@MONOGRAMONLINE.COM';

        // -------------- Customers table data insertion ended ----------------------//
        // -------------- Orders table data insertion started ----------------------//
        $order = new Order();
        $order->order_id = $storeid . '-' . $data[0];
        $order->short_order = $data[0];
        $order->item_count = 1;
        $order->shipping_charge = '0';
        $order->tax_charge = '0';
        $order->total = 0;
        $order->order_date = date("Y-m-d H:i:s");
        $order->store_id = $storeid;
        $order->ship_state = $data[5];
        $order->order_comments = $data[9];
        $order->order_status = 4;

        if(isset($data[17])) {

            $carrier = "";
            $method = "";

            if(isset(self::CONVERSION[$data[17]])) {
                $shipinfo = explode('*', self::CONVERSION[$data[17]]);
                $carrier = $shipinfo[0] ?? "";
                $method = $shipinfo[1] ?? ""; // Error maybe check
            }

            $order->carrier = $carrier;
            $order->method = $method;
        }


        if(isset($data[18])) {
            $order->ship_date = Carbon::parse($data[18])->toDateTimeString();
        }


        // The above is using it instead
        if(isset($data[19])) {
            $status = [
                4,6,7,8,9,10,11,12,15,13,23
            ];

            if(in_array($data[19], $status)) {
                $order->order_status = $data[19];
            }
        }

        // Batch option (20)
        $batch = $data[20] ?? false;

        /*
         * Handle batching the order if it's true
         */
        if($batch) {

        }

        $customer->save();

        try {
            $order->customer_id = $customer->id;
        } catch ( \Exception $exception ) {
            Log::error('Failed to insert customer id in ShipStation');
        }

        /*
         * 17 IS SHIPPED VIA
         */
        $order->save();

        try {
            $order_5p = $order->id;
        } catch ( \Exception $exception ) {
            $order_5p = '0';
            Log::error('Failed to get 5p order id in ShipStation');
        }

        Order::note('Order imported automatically from Ship Station API', $order->id, $order->order_id);

        return $order->id;
    }

    private function insertItem($storeid, $order_5p, $data, $usePwsZakeke = false) {

        $product = null;

        if (isset($data[11])) {
            $product = Helper::findProduct($data[11]);
        }

        if ($product) {
            $sku = $product->product_model;
            $item_id = $product->id_catalog;
            $url = $product->product_url;
            $desc = $product->product_description;
            $thumb = $product->product_thumb;
        } else {
            Log::error('ShipStation: Product not found ' . $data[11]);
            $sku = trim($data[11]);
            $item_id = null;
            $url = null;
            $desc = 'PRODUCT NOT FOUND';
            $thumb = null;
        }

        $options = array();

        if (isset($data[10]) && trim($data[10]) != '') {
            $options['Color'] = $data[10];
        }

        if (isset($data[16]) && trim($data[16]) != '') {
            $options['graphic'] = $data[16];
        }

        if($usePwsZakeke) {
            $options['PWS Zakeke'] = $usePwsZakeke;
        }


        $item = new Item();
        $item->order_id = $storeid . '-' . $data[0];
        $item->store_id = $storeid;
        $item->item_description = $desc;
        $item->item_quantity = isset($data[13]) ? intval($data[13]) : 1;
        $item->data_parse_type = 'CSV';
        $item->item_code = $sku;
        $item->item_id = $item_id;
        $item->item_thumb = isset($data[15]) ? $data[15] : $thumb;
        $item->sure3d = isset($data[16]) ? $data[16] : null;
        $item->item_url = $url;


        $item->item_option = json_encode($options);

        $item->item_unit_price = isset($data[14]) ? $data[14] : 0;

        $item->child_sku = isset($data[12]) ? $data[12] : null;

        $item->order_5p = $order_5p;

        $item->save();

        return true;
    }

    private function setOrderTotals ($order_5p) {
        if ($order_5p != null) {
            $order = Order::with('items')
                ->where('id', $order_5p)
                ->first();
            if (!$order) {
                Log::error('ShipStation setOrderTotals: order not found!');
                return;
            }
            $order->item_count = $order->items->count();
            $order->total = $order->items->sum( function ($i) { return $i->item_quantity * $i->item_unit_price; });
            $order->save();
            return;
        }
    }
}
