<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Order;
use App\Models\Parameter;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use library\Helper;
use Illuminate\Http\Request;
use Ship\ImageHelper;
use Ship\Shipper;

class pullShopifyOrderByToday extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pull-shopify';

    private $domain = "https://7papi.monogramonline.com";
    protected $archiveFilePath = "";
    protected $remotArchiveUrl = "https://7papi.monogramonline.com/media/archive/";
    protected $sort_root = '/media/RDrive/';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pull shopify order by today';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        logger('Pulling Shopify Order of '. Carbon::today());

        $shopifyOrdeIds = [];
        $ordersIn5p = [];

        $created_at_min =  date("Y-m-d TH:i:s", strtotime('-2 hour'));

        $created_at_max = date("Y-m-d") . " T23:59:59-05:00"; // 2020-03-01

        logger('pulling date here :', ['start date' => $created_at_min, 'end date' => $created_at_max]);

        if ($created_at_max) {

            $array = array(
                "created_at_min" => $created_at_min, #2020-04-01T00:00:00-05:00
                "created_at_max" => $created_at_max, #2020-04-13T23:59:59-05:00
                "limit" => 250,
                "fields" => "created_at,id,name,total-price"
            );

            $helper = new Helper;
            $orderInfo = $helper->shopify_call("/admin/api/2022-01/orders.json", $array, 'GET');
            $orderInfo = json_decode($orderInfo['response'] ?? [], JSON_PRETTY_PRINT);

            if (isset($orderInfo['errors'])) {
                logger('Error : ' . $orderInfo['errors'] . ' and  Order not found');
                return;
            }

            $shopifyOrdeIdsWithName = [];
            foreach ($orderInfo['orders'] as $key => $order) {
                $shopifyOrdeIds[$order['id']] = $order['id'];
                $shopifyOrdeIdsWithName[$order['name']] = $order['id'];
                #Log::info("Order_id from Shopify = ".$order['id']);
            }
            $shopifyOrdeIdsx = $shopifyOrdeIds;
            ########### Code for get list of orders numbers by Date ###################
            $created_at_min = substr($created_at_min, 0, 10);
            $created_at_max = substr($created_at_max, 0, 10);
            $existingOrders = Order::where('orders.is_deleted', '0')
                ->where('orders.order_date', '>=', $created_at_min . ' 00:00:00')
                ->where('orders.order_date', '<=', $created_at_max . ' 23:59:59')
                ->where('orders.store_id', '=', '52053153')
                ->latest('orders.created_at')
                ->limit(5000)
                ->get([
                    'orders.short_order',
                    'orders.order_id',
                    'order_date',
                ])->toArray();

            foreach ($existingOrders as $key => $orderId) {
                $ordersIn5p[] = $orderId['short_order'];
                if (isset($shopifyOrdeIds[$orderId['short_order']])) {
                    unset($shopifyOrdeIds[$orderId['short_order']]);
                }
            }
            if (empty($shopifyOrdeIds)) {
                logger([
                    'message' => "Nothing to insert",
                    "Number of orders in shopify= " . count($shopifyOrdeIdsx) . " - Number of orders in 5p= " . count($existingOrders) . " = diff = " . (count($shopifyOrdeIdsx) - count($existingOrders)),
                    "Missing Orders = ",
                    $shopifyOrdeIds,
                    "Following already inserted: ",
                    $shopifyOrdeIdsWithName
                ]);
                return;
            }

            foreach ($shopifyOrdeIds as $orderId) {
                $request = new Request();
                $data = $request->merge(['orderid' => $orderId]);
                $this->getshopifyorder($data);
            }

            logger([
                'message' => 'Order Synced',
                'data' => "Number of orders in shopify= " . count($shopifyOrdeIdsx) . " - Number of orders in 5p= " . count($existingOrders) . " = diff = " . (count($shopifyOrdeIdsx) - count($existingOrders)),
                "Missing Orders = ",
                $shopifyOrdeIds,
                $shopifyOrdeIdsx,
                $ordersIn5p,
                $shopifyOrdeIdsWithName,
                $array,
                count($existingOrders)
            ]);
        } else {
            logger([
                'message' => 'Order not found',
                'data' => []
            ]);
        }
    }

    public function getShopifyOrder(Request $request)
    {
        if ($request->get('orderid')) {
            $ids = $request->get('orderid');
        } else {
            return response()->json([
                'message' => 'Order id not found',
                'data' => []
            ]);
        }


        ################# for Order ######################
        #'2112301105285'
        $array = ['ids' => $ids];
        $helper = new Helper;
        $orderInfo = $helper->shopify_call("/admin/api/2023-01/orders.json", $array, 'GET');

        try {
            $orderInfo = json_decode($orderInfo['response'], JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            Log::error('getShopifyOrderError = ' . $e->getMessage());
        }
        if (isset($orderInfo['errors'])) {
            return response()->json([
                'message' => $orderInfo['errors'],
                'data' => []
            ]);
        }

        if (empty($orderInfo['orders'])) {
            logger([
                'message' => "Order not found with ID " . $ids,
                'data' => []
            ]);
        }
        //        dd('hello');
        //        dd($orderInfo);

        // return response()->json([
        //     'message' => "This is response data. Just remove this return  in line 778 in getShopifyOrder function in OrderController to store these records",
        //     'data' => $orderInfo,
        // ]);
        //        $this->jdbg("orderInfo: ",$orderInfo);

        $this->pushPurchaseToOms($orderInfo);
    }

    public function pushPurchaseToOms($orderInfos)
    {
        info("pushPurchaseToOms");
        $helper = new Helper;

        foreach ($orderInfos as $orderIds) {
            //            try {
            foreach ($orderIds as $orderId) {


                info("pushPurchaseToOms2");

                if (!isset($orderId['billing_address'])) {
                    $billingAddress = $orderId['shipping_address'];
                } else {
                    $billingAddress = $orderId['billing_address'];
                }

                if (isset($orderId['discount_codes'])) {
                    $discounts = $orderId['discount_codes'];
                } else {
                    $discounts = '';
                }


                $customerDetails = $orderId['customer'];

                $purchaseData['Bill-Address1'] = $billingAddress['address1'];
                $purchaseData['Bill-Address2'] = $billingAddress['address2'];
                $purchaseData['Bill-City'] = $billingAddress['city'];
                $purchaseData['Bill-Company'] = '';
                $purchaseData['Bill-Country'] = $billingAddress['country'];
                $purchaseData['Bill-Email'] = $customerDetails['email'];
                $purchaseData['Bill-Firstname'] = $billingAddress['first_name'];
                $purchaseData['Bill-Lastname'] = $billingAddress['last_name'];
                $purchaseData['Bill-Name'] = $billingAddress['first_name'] . " " . $billingAddress['last_name'];
                $purchaseData['Bill-Phone'] = $billingAddress['phone'];
                $purchaseData['Bill-State'] = $billingAddress['phone'];
                $purchaseData['Bill-Zip'] = $billingAddress['phone'];
                $purchaseData['Bill-maillist'] = 'no';
                $purchaseData['Card-Expiry'] = 'xx/xxxx';
                $purchaseData['Card-Name'] = 'PayPal';
                $purchaseData['Comments'] = $orderId['note']; #
                $purchaseData['Coupon-Description'] = (isset($discounts[0]['type'])) ? $discounts[0]['type'] : ""; //$discounts['type'];
                $purchaseData['Coupon-Id'] = (isset($discounts[0]['code'])) ? $discounts[0]['code'] : ""; //$discounts['code'];
                $purchaseData['Coupon-Value'] = (isset($discounts[0]['amount'])) ? $discounts[0]['amount'] : 0; //$discounts['amount'];
                $purchaseData['Date'] = $orderId['created_at'];
                $purchaseData['ID'] = "52053153-" . $orderId['id'];
                $purchaseData['Purchase-Order'] = $orderId['order_number']; // This is temp for 6p, in 5p not required. we will check later.
                $purchaseData['IP'] = $orderId['browser_ip'];


                $shippingMethod = (isset($orderId['shipping_lines'][0]['code'])) ? $orderId['shipping_lines'][0]['code'] : "";  // Shipping method which one
                //                $shippingprice = $orderId['shipping_lines'][0]['price'];    # How to get the value, foreach loop or direct?
                //                $shippingprice = $this->calShippingCost($orderItems, $shippingMethod); // shipping pirce set need to use for loop

                $index = 1;
                $orderItems = $orderId['line_items'];
                foreach ($orderItems as $item) {
                    //                    dd($item);
                    //                    $productId = $item['id']; QUESTIONS
                    //                    $productObject = Mage::getModel('catalog/product')->load($productId);

                    if (empty($item['sku'])) {
                        continue;
                    }
                    $purchaseData['Item-Code-' . $index] = $item['sku'];
                    $purchaseData['Item-Description-' . $index] = $item['name'];
                    $purchaseData['Item-Id-' . $index] = $item['id'];
                    $purchaseData['Item-Quantity-' . $index] = $item['quantity'];
                    $purchaseData['Item-Taxable-' . $index] = ($item['taxable']) ? 'Yes' : 'No';
                    $purchaseData['Item-Unit-Price-' . $index] = $item['price'];
                    $purchaseData['Item-Url-' . $index] = "https://monogramonline.myshopify.com/products/" . preg_replace('/\W+/', '-', strtolower($item['name'])); #$this->getImaeUrl($item['properties']);
                    //                        $purchaseData['Item-Thumb-' . $index] =  $this->getImaeUrl($item['product_id']); #$this->getImaeUrl($item['properties']);
                    $purchaseData['Item-Thumb-' . $index] = "<img border=0 width=70 height=70 src=" . $this->getImaeUrl($item['product_id']) . ">";

                    //                        dd($item['properties']);
                    // Another for loop for Parameter Options
                    $itemOptions = $item['properties'];

                    if (count($itemOptions) > 0) {
                        foreach ($itemOptions as $value) {
                            ########## Add Sure3d url for Download image ##################
                            if (isset($value['name'])) {
                                if ($value['name'] == "_pdf") {
                                    $purchaseData['Item-Option-' . $index . '-' . trim(str_replace(":", "", "Custom_EPS_download_link"))] = $helper->getUrlWithoutParaMeter($value['value']);
                                }
                            }
                            ########## Add Sure3d url for Download image ##################
                            // Add filter later
                            //                                $helper->jdbg("Key Name =:", $value['name']);
                            //                                $helper->jdbg("Key value =:",$value['value']);
                            //                                Log::info("---------------------------------------------------------------------------------");
                            $keyName = trim(ucwords(strtolower($value['name'])));
                            #Log::info("Key After =: ".$keyName);
                            #Log::info("Key After1 =: ".trim(str_replace(":", "", $keyName)));

                            ##### Don't save following key in item option #####
                            if ($helper->isKeyExist($item['sku'], $value['name'], $value['value'])) {
                                continue;
                            }
                            ##### Don't save following key in item option #####
                            if ($value['name'] == "Preview") {
                                $purchaseData['Item-Option-' . $index . '-' . trim(str_replace(":", "", $keyName))] = $helper->getUrlWithoutParaMeter($value['value']);
                            } elseif ($value['name'] == '_zakekeZip') {
                                $purchaseData['Item-Option-' . $index . '-' . trim(str_replace(":", "", $keyName))] = $value['value'];
                            } else {
                                //                                    $helper->jdbg("Key Name =:". $value['name']. " value= ",$value['value']);
                                //                                    Log::info("---------------------------------------------------------------------------------");
                                $purchaseData['Item-Option-' . $index . '-' . trim(str_replace(":", "", $keyName))] = $helper->optionsValuesFilter($value['value']);
                            }
                        }
                    }
                    $index++;
                }

                $purchaseData['Item-Count'] = ($index - 1);
                $purchaseData['Numeric-Time'] = strtotime($orderId['created_at']);

                #What to do for paypal
                $purchaseData['PayPal-Address-Status'] = 'Confirmed';
                $purchaseData['PayPal-Auth'] = '8F4701569X6000947';
                $purchaseData['PayPal-Merchant-Email'] = 'pablo@dealtowin.com';
                $purchaseData['PayPal-Payer-Status'] = 'Unverified';
                $purchaseData['PayPal-TxID'] = '75692712YB5948433';
                #-------------------------------------------------

                $shippingAddress = $orderId['shipping_address'];
                $purchaseData['Ship-Address1'] = $shippingAddress['address1'];
                $purchaseData['Ship-Address2'] = $shippingAddress['address2'];
                $purchaseData['Ship-City'] = $shippingAddress['city'];
                $purchaseData['Ship-Company'] = '';
                $purchaseData['Ship-Country'] = $shippingAddress['country'];
                $purchaseData['Ship-Firstname'] = $shippingAddress['first_name'];
                $purchaseData['Ship-Lastname'] = $shippingAddress['last_name'];
                $purchaseData['Ship-Name'] = $shippingAddress['first_name'] . " " . $shippingAddress['last_name'];
                $purchaseData['Ship-Phone'] = $shippingAddress['phone'];
                $purchaseData['Ship-State'] = $shippingAddress['province'];
                $purchaseData['Ship-Zip'] = $shippingAddress['zip'];
                $purchaseData['Shipping'] = (isset($orderId['shipping_lines'][0]['code'])) ? $orderId['shipping_lines'][0]['code'] : "";
                $purchaseData['Shipping-Charge'] = (isset($orderId['shipping_lines'][0]['code'])) ? $orderId['shipping_lines'][0]['price'] : 0; #$orderObject->getShippingAmount(); # Which Shipping Charge
                $purchaseData['Space-Id'] = '';
                $purchaseData['Store-Id'] = '52053153'; //$storeID
                $purchaseData['Store-Name'] = 'www.monogramonline.myshopify.com'; // http://dev.monogramonline.com/stores/14/edit?
                $purchaseData['Tax-Charge'] = $orderId['total_tax'];
                $purchaseData['Total'] = $orderId['total_price'];
                //                    dd($purchaseData);
                //                    Log::info(print_r($purchaseData, true));

                //                    dd($orderIds, $purchaseData, $orderIds[0]['line_items']);
                ###################
                // CRON-TODO : Need to check this domain

                //                    dd("pushPurchaseToOms3");
                $request = new Request();
                $data = $request->merge($purchaseData);
                $result = $this->hook($data);


                // $res = Http::post($url, $purchaseData);
                // $result = $res->json(); // If the response is JSON

//                dd($result);
                info("pushPurchaseToOms4");
                //                    dd($result);
                //
                //                    $json = json_decode($result, TRUE);
                Log::info("------------ Insert status----------------  " .  $orderId['id'] . " created_at= " . $orderId['created_at']);
                //                    Log::info($result['message']);
            }
            //            } catch (Exception $e) {
            //                Log::info('Shopify Order push error = (' . $e->getMessage() . ') sent');
            //            }
        }
    }

    public function hook(Request $request)
    {
        //        dd($request->all());
        //        try {
        $helper = new Helper;
        set_time_limit(0);
        $order_id = $request->get('ID');

        $previous_order = Order::with('items')->where('order_id', $order_id)->where(
            'is_deleted',
            '0'
        )->orderBy('created_at', 'DESC')->first();

        if ($previous_order) {
            ########
            Log::info('Hook: Duplicate order can not inserted (status) = ' . $order_id);
            return response()->json([
                'error' => true,
                'message' => "Order in DB, status can not in process, data can't be inserted",
            ], 200);
            ########
            $batched = $previous_order->items->filter(function ($item) {
                return $item->batch_number != '0';
            })->count();

            if ($batched > 0) {
                Log::info('Hook: Duplicate order not inserted (batched) ' . $order_id);
                return response()->json([
                    'error' => true,
                    'message' => "Batch exists data can't be inserted",
                ], 200);
            } else {
                if ($previous_order->order_status != 4) {
                    Log::info('Hook: Duplicate order not inserted (status)' . $order_id);
                    return response()->json([
                        'error' => true,
                        'message' => "Order in DB, status not in process, data can't be inserted",
                    ], 200);
                } else {
                    Order::where('order_id', $order_id)->update(['is_deleted' => 1]);
                    Item::where('order_id', $order_id)->update(['is_deleted' => 1]);
                    Customer::where('order_id', $order_id)->update(['is_deleted' => 1]);
                }
            }
        }
        $this->jdbg(__LINE__, "----------------Start Hook Call for order = " . $request->get('ID') . "--------------------");
        try {
            $exploded = explode("-", $order_id);
            if (isset($exploded[2])) {
                $short_order = $exploded[2];
            } else {
                $short_order = $exploded[1];
            }
        } catch (Exception $e) {
            Log::error('Undefined offset when trying to create short order. Order ' . $order_id);
            Log::error($request);
            if (strlen($order_id) < 1) {
                exit('no order');
            } else {
                $short_order = $order_id;
            }
        }

        // -------------- Customers table data insertion started ----------------------//
        $customer = new Customer();
        $customer->order_id = $request->get('ID');
        $customer->ship_full_name = str_replace('&', '+', $request->get('Ship-Name'));
        $customer->ship_first_name = $request->get('Ship-Firstname');
        $customer->ship_last_name = $request->get('Ship-Lastname');
        $customer->ship_company_name = $request->get('Ship-Company');
        $customer->ship_address_1 = $request->get('Ship-Address1');
        $customer->ship_address_2 = $request->get('Ship-Address2');
        $customer->ship_city = $request->get('Ship-City');
        $customer->ship_state = Helper::stateAbbreviation($request->get('Ship-State'));
        $customer->ship_zip = $request->get('Ship-Zip');
        $customer->ship_country = $request->get('Ship-Country');
        $customer->ship_phone = $request->get('Ship-Phone');
        $customer->ship_email = $request->get('Ship-Email');
        $customer->shipping = $request->get('Shipping', "N/A");
        $customer->bill_full_name = $request->get('Bill-Name');
        $customer->bill_first_name = $request->get('Bill-Firstname');
        $customer->bill_last_name = $request->get('Bill-Lastname');
        $customer->bill_company_name = $request->get('Bill-Company');
        $customer->bill_address_1 = $request->get('Bill-Address1');
        $customer->bill_address_2 = $request->get('Bill-Address2');
        $customer->bill_city = $request->get('Bill-City');
        $customer->bill_state = $request->get('Bill-State');
        $customer->bill_zip = $request->get('Bill-Zip');
        $customer->bill_country = $request->get('Bill-Country');
        $customer->bill_phone = $request->get('Bill-Phone');
        $customer->bill_email = $request->get('Bill-Email');
        $customer->bill_mailing_list = $request->get('Bill-maillist');
        $customer->save(); # Save Later
        // -------------- Customers table data insertion ended ----------------------//
        // -------------- Orders table data insertion started ----------------------//
        $order = new Order();
        $order->order_id = $request->get('ID');
        try {
            $order->customer_id = $customer->id;
        } catch (Exception $exception) {
            Log::error('Failed to insert customer id in hook');
        }
        $purchase_order = null;
        if ($request->has('Purchase-Order')) {
            $purchase_order = $request->get('Purchase-Order');
        }
        $order->short_order = $short_order;
        $order->purchase_order = $purchase_order;
        $order->item_count = $request->get('Item-Count');
        $order->coupon_description = $request->get('Coupon-Description');
        $order->coupon_id = $request->get('Promotions-Code');
        $order->coupon_value = abs($request->get('Promotions-Value'));
        $order->promotion_id = $request->get('Coupon-Id');
        $order->promotion_value = abs($request->get('Coupon-Value'));
        $order->shipping_charge = $request->get('Shipping-Charge');
        $order->tax_charge = $request->get('Tax-Charge');
        $order->total = $request->get('Total');
        $order->card_name = $request->get('Card-Name');
        $order->card_expiry = $request->get('Card-Expiry');
        $order->order_comments = $request->get('Comments');
        $order->order_date = date('Y-m-d H:i:s', strtotime($request->get('Date')));
        //$order->order_numeric_time = strtotime($request->get('Numeric-Time'));
        // 06-22-2016 Change by Jewel
        $order->order_numeric_time = ($request->get('Numeric-Time'));
        $order->order_ip = $request->get('IP');
        $order->paypal_merchant_email = $request->get('PayPal-Merchant-Email', '');
        $order->paypal_txid = $request->get('PayPal-TxID', '');
        $order->space_id = $request->get('Space-Id');
        $order->store_id = $request->get('Store-Id');
        $order->store_name = $request->get('Store-Name');
        $order->order_status = 4;

        if (empty($request->get('shipping')) && ($request->get('Store-Id') == "52053153")) {
            Log::info('xShipping77 = : ' . $request->get('shipping'));
            $order->carrier = "US";
            $order->method = "FIRST_CLASS";
        }
        Log::info('Inserted purchase order ' . $purchase_order);
        $order->save();  # Save Later
        try {
            $order_5p = $order->id;  # Save Later
        } catch (Exception $exception) {
            $order_5p = '0';
            Log::error('Failed to get 5p order id in hook');
        }
        // -------------- Orders table data insertion ended ----------------------//
        // -------------- Items table data insertion started ------------------------//
        $upsell = array();
        $upsell_price = 0;

        for ($item_count_index = 1; $item_count_index <= $request->get('Item-Count'); $item_count_index++) {
            $ItemOption = [];
            $pdfUrl = "";

            foreach ($request->all() as $key => $value) {
                if ($item_count_index < 10) {
                    $len = 14;
                } else {
                    $len = 15;
                }

                if ("Item-Option-" . $item_count_index . "-" == substr($key, 0, $len)) {
                    if (substr(strtolower($key), $len, 14) == 'special_offer_') {
                        $upsell[substr($key, $len)] = $value;
                        if (strpos($value, 'Yes') !== false) {
                            Log::info('YES UPSELL ITEM ' . $value);
                        }
                        Order::note(substr($key, $len) . ' - ' . $value, $order->id, $order->order_id);
                    } elseif (substr(strtolower($key), $len, 14) == '_zakekezip') {
                        Log::info('Waiting for Zakeke personalization file ' . $value);
                        sleep(120);

                        $pdfUrl = $this->_processZakekeZip($value);

                        if (!is_string($pdfUrl)) {
                            while (!is_string($this->_processZakekeZip($value))) {
                                Log::info('Processing to get _processZakekeZip' . $value);
                                $pdfUrl = $this->_processZakekeZip($value);
                                sleep(120);
                            }
                        }

                        Log::info('Zakeke PDF URL ' . $pdfUrl);
                        $ItemOption['zakekezip'] = addslashes($value);

                        //  $childSku = Helper::getChildSku($item);

                        $ItemOption['Custom_EPS_download_link'] = $pdfUrl;
                    } else {
                        if (strpos(str_replace([' ', ','], '', strtolower($value)), 'nothankyou') === false) {
                            $ItemOption[preg_replace(
                                '/[\x00-\x1F\x7F-\xFF\xA0]/ u ',
                                '',
                                substr($key, $len)
                            )] = preg_replace_callback(
                                '/\\\\u([0-9a-fA-F]{4})/',
                                function ($match) {
                                    return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
                                },
                                str_replace(["\u00a0", "\u0081", "\u0091"], '', $value)
                            );
                        } else {
                            Log::info('Deleted option: ' . $value);
                        }
                    }
                }
            }

            $manufacture_id = Product::where(
                'product_model',
                $request->get('Item-Code-' . $item_count_index)
            )->first()->manufacture_id ?? null;
            $item = new Item();
            $item->order_5p = $order_5p;
            $item->order_id = $request->get('ID');
            $item->store_id = $order->store_id;
            $item->manufacture_id = $manufacture_id;
            $item->item_code = $request->get('Item-Code-' . $item_count_index);
            $item->item_description = $request->get('Item-Description-' . $item_count_index);
            $item->item_id = $request->get('Item-Id-' . $item_count_index);
            //                    $item->item_option = json_encode($ItemOption);
            $item->item_quantity = $request->get('Item-Quantity-' . $item_count_index);
            //                    $item->item_thumb = $item_thumb;
            $item->item_unit_price = $request->get('Item-Unit-Price-' . $item_count_index);
            $item->item_url = $request->get('Item-Url-' . $item_count_index);
            $item->item_taxable = $request->get('Item-Taxable-' . $item_count_index);
            $item->data_parse_type = 'hook';
            $item->child_sku = Helper::getChildSku($item);
            $item->save();  # Save Later
            //                dd($item);
            ########## Create a New logic if Custom_EPS_download_link exist with valid url download the image in Archive file #######

            if (isset($ItemOption['Custom_EPS_download_link'])) {
                $headers = @get_headers($ItemOption['Custom_EPS_download_link']);
                if ($headers && strpos($headers[0], '200') !== false) {
                    $fileName = basename(parse_url($ItemOption['Custom_EPS_download_link'], PHP_URL_PATH));
                    $fileName = $short_order . "_" . $item->id . "_" . $fileName;
                    $this->archiveFilePath = "/media/RDrive/archive/" . $fileName;
                    //$this->jdbg(__LINE__." ** ".$image_path." -- ".$ItemOption['Custom_EPS_download_link']." --> ", $ItemOption);
                    $fleSaveStatus = $helper->dowFileToDir(
                        $ItemOption['Custom_EPS_download_link'],
                        public_path() . $this->archiveFilePath
                    );
                    if ($fleSaveStatus == 200) {
                        $ItemOption['Custom_EPS_download_link'] = $this->remotArchiveUrl . $fileName;
                    }
                    //$this->jdbg(__LINE__." -- After check  --", $headers[0]);
                    //$this->jdbg(__LINE__." -- fleSaveStatus --", $fleSaveStatus);

                } else {
                    unset($ItemOption['Custom_EPS_download_link']);
                }
            }
            #######################################################################################################################
            $matches = [];

            preg_match(
                "~.*src\s*=\s*(\"|\'|)?(.*)\s?\\1.*~im",
                $request->get('Item-Thumb-' . $item_count_index),
                $matches
            );

            ### Start code for create Image Thumbnail
            ####*********########
            if (file_exists($this->archiveFilePath)) {
                try {
                    $thmFileName = basename($this->archiveFilePath);
                    $filenameWithoutExtension = pathinfo($thmFileName, PATHINFO_FILENAME);
                    //                        $thumb = '/assets/images/template_thumbs/' . $filenameWithoutExtension . '.jpg';
                    $thumb = '/assets/images/template_thumbs/' . $item->order_id . "-" . $item->id . '.jpg';
                    ImageHelper::createThumb(public_path() . $this->archiveFilePath, 0, public_path() . $thumb, 350);
                    $item_thumb = $this->domain . $thumb;
                } catch (Exception $e) {
                    $item_thumb = $this->domain . '/assets/images/no_image.jpg';
                    Log::error(sprintf(
                        "Hook found undefinded offset 2 on item thumb %s Order# %s.",
                        $request->get('Item-Thumb-' . $item_count_index),
                        $order_5p
                    ));
                    Log::error('Batch uploadFile createThumb: ' . $e->getMessage());
                }
            } else {
                if (isset($matches[2])) {
                    $item_thumb = trim($matches[2], ">");
                } else {
                    $item_thumb = $this->domain . '/assets/images/no_image.jpg';
                    Log::error(sprintf(
                        "Hook found undefinded offset 2 on item thumb %s Order# %s.",
                        $request->get('Item-Thumb-' . $item_count_index),
                        $order_5p
                    ));
                }
            }
            $this->jdbg("Hook Thumb for order= " . $order->short_order, $item_thumb);
            //                $this->jdbg("Hook Thumb from API = ". $order->short_order, $matches[2]);
            ####*********########

            //                if(file_exists(base_path() . 'public_html/assets/images/product_thumb/' . $request->get('Item-Code-' . $item_count_index) . '.jpg')) {
            //                    $item_thumb = 'http://' . $this->domain . '/assets/images/product_thumb/' . $request->get('Item-Code-' . $item_count_index) . '.jpg';
            //                } else {
            //                    if(file_exists(base_path() . 'public_html/assets/images/product_thumb/' . $request->get('Item-Code-' . $item_count_index) . '.png')) {
            //                        $item_thumb = 'http://' . $this->domain . '/assets/images/product_thumb/' . $request->get('Item-Code-' . $item_count_index) . '.png';
            //                    } else {
            //                        if(isset($matches[2])) {
            //                            $item_thumb = trim($matches[2], ">");
            //                        } else {
            //                            $item_thumb = 'http://' . $this->domain . '/assets/images/no_image.jpg';
            //                            Log::error(sprintf("Hook found undefinded offset 2 on item thumb %s Order# %s.",
            //                                $request->get('Item-Thumb-' . $item_count_index), $order_5p));
            //                        }
            //                    }
            //                }
            ### End code for create Image Thumbnail

            $item->item_option = json_encode($ItemOption);
            $item->item_thumb = $item_thumb;
            //                dd('item :', $item);
            $item->save();  # Save Later

            try {
                $item_id = $item->id;
            } catch (Exception $exception) {
                $item_id = null;
                Log::error('Failed to get item id in hook');
            }

            $childSku = Helper::getChildSku($item);
            //                if(ZakekeController::hasSure3D($childSku, $request)) {
            //                    if(isset($ItemOption['Custom_EPS_download_link']) && (strpos(strtolower($item->item_description),
            //                                'photo') || Option::where('child_sku', $item->child_sku)->first()->sure3d == '1')) {
            //                        $item->sure3d = html_entity_decode($ItemOption['Custom_EPS_download_link']);
            //                        $item->save();  # Save Later
            //                    } else {
            //                        if($pdfUrl !== "") {
            //                            $ItemOption['Custom_EPS_download_link'] = $pdfUrl;
            //                        }
            //                    }
            //                } else {
            //                    if(isset($ItemOption['Custom_EPS_download_link'])) {
            //                        unset($ItemOption['Custom_EPS_download_link']);
            //                    }
            //                }

            if (count($upsell) > 0) {
                $upsell_price = $this->upsellItems($upsell, $order, $item);
                $item->item_unit_price = $item->item_unit_price - $upsell_price;
            }

            //                $item->item_option = json_encode($ItemOption);
            //                $item->save();  # Save Later

            if ($item->item_option == '[]' || $item->item_option == '0') {
                $data = [];
                $file = $this->domain . "/BypassOption.json";
                if (file_exists($file)) {
                    $data = json_decode(file_get_contents($file), true);
                }

                $bypass = false;

                if (isset($data[$item->child_sku])) {
                    if ($data[$item->child_sku]) {
                        $bypass = true;
                    }
                }

                if ($bypass) {
                    $order->order_status = 15;
                    $order->save();  # Save Later
                }
            }

            // -------------- Items table data insertion ended ---------------------- //

            $product = Product::where('product_model', $item->item_code)->first();
            // where('id_catalog', $item->item_id)

            // no product found matching model
            if (!$product) {
                $product = new Product();
                $product->product_model = $item->item_code;
                $product->manufacture_id = 1;
            }

            if ($product->id_catalog == null || $item->store_id == '52053152') {
                $product->id_catalog = $item->item_id;
            }
            //$this->jdbg(__LINE__, $matches);
            //$this->jdbg(__LINE__, $item->item_thumb);

            if (isset($matches[2])) {
                if (!empty($matches[2])) {
                    $product->product_thumb = trim($matches[2], ">");
                }
            }
            //                else {
            //                    $product->product_url = $this->domain . '/assets/images/no_image.jpg';
            //                }
            $product->product_url = $item->item_url;
            $product->product_name = $item->item_description;
            $product->product_price = $item->item_unit_price;
            $product->is_taxable = ($item->item_taxable == 'Yes' ? 1 : 0);
            $product->save();  # Save Later
        }

        // -------------- Order Confirmation email sent Start   ---------------------- //
        if (substr($item->item_code, 0, 3) != 'KIT') {
            //                Notification::orderConfirm($order);
        }
        // -------------- Order Confirmation email sent End---------------------- //
        try {
            $isVerified = Shipper::isAddressVerified($customer);
        } catch (Exception $exception) {
            $isVerified = 0;
        }

        if ($isVerified) {
            $customer->is_address_verified = 1;
        } else {
            $customer->is_address_verified = 0;
            $order->order_status = 11;
            $order->save();  # Save Later
        }

        $customer->save();  # Save Later

        // -------------- Hold Free Orders   ---------------------- //

        $this->jdbg(__LINE__, "----------------End Hook Called for order " . $request->get('ID') . "--------------------");

        return response()->json([
            'error' => false,
            'message' => 'data inserted',
        ], 200);
        //        } catch (Exception $e) {
        //            // Notification::orderFailure($order_id);
        //            Log::error('Hook: ' . $e->getMessage());
        //
        //            return response()->json([
        //                'error' => true,
        //                'message' => 'error',
        //            ], 200);
        //        }
    }

    private function jdbg($label, $obj)
    {
        $logStr = "5p -- {$label}: ";
        switch (gettype($obj)) {
            case 'boolean':
                if ($obj) {
                    $logStr .= "(bool) -> TRUE";
                } else {
                    $logStr .= "(bool) -> FALSE";
                }
                break;
            case 'integer':
            case 'double':
            case 'string':
                $logStr .= "(" . gettype($obj) . ") -> {$obj}";
                break;
            case 'array':
                $logStr .= "(array) -> " . print_r($obj, true);
                break;
            case 'object':
                try {
                    if (method_exists($obj, 'debug')) {
                        $logStr .= "(" . get_class($obj) . ") -> " . print_r($obj->debug(), true);
                    } else {
                        $logStr .= "Don't know how to log object of class " . get_class($obj);
                    }
                } catch (Exception $e) {
                    $logStr .= "Don't know how to log object of class " . get_class($obj);
                }
                break;
            case 'NULL':
                $logStr .= "NULL";
                break;
            default:
                $logStr .= "Don't know how to log type " . gettype($obj);
        }

        Log::info($logStr);
    }

    private function _processZakekeZip($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true); // Videos are needed to transfered in binary
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($ch);
        $filename = explode('/', curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
        $filename = array_pop($filename);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        /*
         * Return null if the image is not found
         * AKA: The image is not yet ready,
         * returning null will schedule to try again next time.
         */
        if ((bool)$httpcode != 200) {
            return null;
        }

        $result = ["file" => $response, "filename" => $filename];

        $fp = fopen(sys_get_temp_dir() . DIRECTORY_SEPARATOR . $result['filename'], 'w');
        fwrite($fp, $result['file']);
        fclose($fp);

        system('unzip -o ' . sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename . ' -d ' . sys_get_temp_dir() . DIRECTORY_SEPARATOR . pathinfo(
                $filename,
                PATHINFO_FILENAME
            ));

        $tmpDir = scandir(sys_get_temp_dir() . DIRECTORY_SEPARATOR . pathinfo($filename, PATHINFO_FILENAME), true);

        $matches = preg_grep('/^[0-9]+.*pdf$/i', $tmpDir);
        $pdfFile = array_shift($matches);

        $pdfFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . pathinfo(
                $filename,
                PATHINFO_FILENAME
            ) . DIRECTORY_SEPARATOR . $pdfFile;

        if (copy(
            $pdfFilePath,
            public_path() . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'zakeke' . DIRECTORY_SEPARATOR . $pdfFile
        )) {
            return  $this->domain . '/media/zakeke/' . $pdfFile;
        } else {
            return false;
        }
    }

    private function upsellItems($values, $order, $order_item)
    {

        $options = json_decode($order_item->item_option, TRUE);

        $total_price = 0;

        $parameters = Parameter::where('is_deleted', '0')
            ->selectRaw("LOWER(parameter_value) as parameter")
            ->get()
            ->toArray();

        foreach ($options as $key => $value) {
            $k = strtolower($key);

            if (in_array($k, $parameters) && !strpos($k, 'style') && !strpos($k, 'color')) {
                unset($options[$key]);
            } else if ($key == 'Confirmation_of_Order_Details' || $key == 'couponcode') {
                unset($options[$key]);
            } else if (strpos($value, '$') || strpos(str_replace([' ', ','], '', strtolower($value)), 'nothankyou')) {
                unset($options[$key]);
            }
        }

        Log::info($options);

        foreach ($values as $key => $value) {

            if (!strpos(strtolower($value), 'yes')) {
                continue;
            }

            Log::info('OrderController: Upsell Item found ' . $order->id);

            $price = substr($value, strrpos($value, '$') + 1, strrpos($value, '.', strrpos($value, '$')) - strrpos($value, '$') + 2);

            $start = stripos($value, ':') + 1;

            $sku = trim(substr($value, $start, stripos($value, ' ', $start + 2) - $start));

            if (substr(strtolower($key), 0, 15) == 'special_offer_-') {
                $desc = trim(str_replace('_', ' ', substr($key, 16)));
            } else {
                $desc = trim(str_replace('_', ' ', $key));
            }


            Log::info("Upsell: price = $price, sku = $sku, desc = $desc");

            $product = Product::where('product_model', $sku)
                ->first();

            if (!$product) {
                Log::error('Upsell Product not in 5p: ' . $order->order_id);
                continue;
            }

            try {
                $item = new Item();
                $item->order_5p = $order->id;
                $item->order_id = $order->order_id;
                $item->store_id = $order->store_id;
                $item->item_code = $sku;
                $item->item_description = $product->product_name;
                $item->item_id = $product->id_catalog;
                $item->item_option = json_encode($options);
                $item->item_quantity = $order_item->item_quantity;
                $item->item_thumb = isset($product->product_thumb) ? $product->product_thumb : 'http://order.monogramonline.com/assets/images/no_image.jpg';
                $item->item_unit_price = $price;
                $item->item_url = isset($product->product_url) ? $product->product_url : null;
                $item->data_parse_type = 'hook';
                $item->child_sku = Helper::getChildSku($item);
                $item->save();

                $total_price += $price;
            } catch (Exception $e) {
                Log::error('Upsell: could not add item ' . $sku);
                Log::error($item);
            }
        }

        return $total_price;
    }

    protected function getImaeUrl($productId)
    {
        $productInfo = $this->getShopifyproduct($productId);
        //        dd($productInfo);
        return $productInfo['image']['src'];
    }

    public function getShopifyproduct($productId)
    {
        //5551613214883
        $array = [];

        $helper = new Helper;
        $products = $helper->shopify_call("/admin/api/2023-01/products/" . $productId . ".json", $array, 'GET');
        $products = json_decode($products['response'], JSON_PRETTY_PRINT);
        if (isset($products['errors'])) {
            //            dd("errors", $products);
            return false;
        } else {
            //            dd("products", $products['product']);
            return $products['product'];
        }
    }
}
