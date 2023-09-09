<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\EmailTemplate;
use App\Models\Item;
use App\Models\Note;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Parameter;
use App\Models\Product;
use App\Models\Store;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use library\Helper;
use Ship\Batching;
use Ship\ImageHelper;
use Ship\Shipper;

class OrderController extends Controller
{
    // private $domain = "http://7p.test";
    private $domain = "https://7papi.monogramonline.com";
    protected $archiveFilePath = "";
    protected $remotArchiveUrl = "https://7papi.monogramonline.com/media/archive/";
    protected $sort_root = '/media/RDrive/';

    private static $state_abbrev = array(
        'alabama' => 'AL',
        'alaska' => 'AK',
        'arizona' => 'AZ',
        'arkansas' => 'AR',
        'california' => 'CA',
        'colorado' => 'CO',
        'connecticut' => 'CT',
        'delaware' => 'DE',
        'florida' => 'FL',
        'georgia' => 'GA',
        'hawaii' => 'HI',
        'idaho' => 'ID',
        'illinois' => 'IL',
        'indiana' => 'IN',
        'iowa' => 'IA',
        'kansas' => 'KS',
        'kentucky' => 'KY',
        'louisiana' => 'LA',
        'maine' => 'ME',
        'maryland' => 'MD',
        'massachusetts' => 'MA',
        'michigan' => 'MI',
        'minnesota' => 'MN',
        'mississippi' => 'MS',
        'missouri' => 'MO',
        'montana' => 'MT',
        'nebraska' => 'NE',
        'nevada' => 'NV',
        'new hampshire' => 'NH',
        'new jersey' => 'NJ',
        'new mexico' => 'NM',
        'new york' => 'NY',
        'north carolina' => 'NC',
        'north dakota' => 'ND',
        'ohio' => 'OH',
        'oklahoma' => 'OK',
        'oregon' => 'OR',
        'pennsylvania' => 'PA',
        'rhode island' => 'RI',
        'south carolina' => 'SC',
        'south dakota' => 'SD',
        'tennessee' => 'TN',
        'texas' => 'TX',
        'utah' => 'UT',
        'vermont' => 'VT',
        'virginia' => 'VA',
        'washington' => 'WA',
        'west virginia' => 'WV',
        'wisconsin' => 'WI',
        'wyoming' => 'WY',
        'british columbia' => 'BC',
        'newfoundland and labrador' => 'NL',
        'prince edward island' => 'PE',
        'nova scotia' => 'NS',
        'new brunswick' => 'NB',
        'quebec' => 'QC',
        'ontario' => 'ON',
        'manitoba' => 'MB',
        'saskatchewan' => 'SK',
        'alberta' => 'AB',
        'yukon' => 'YT',
        'northwest territories' => 'NT',
        'nunavut' => 'NU',
        'district of columbia' => 'DC',
        'virgin islands' => 'VI',
        'guam' => 'GU',
    );
    public static function stateAbbreviation($state)
    {
        if (isset(static::$state_abbrev[strtolower($state)])) {
            return static::$state_abbrev[strtolower($state)];
        } else {
            return $state;
        }
    }

    public static function listMethods($carrier = null)
    {
        $methods = array(
            '' => 'DEFAULT SHIPPING',
            'MN*' => 'MANUAL SHIPPING',
            'US*FIRST_CLASS' => 'USPS FIRST_CLASS',
            'US*PRIORITY' => 'USPS PRIORITY',
            'US*EXPRESS' => 'USPS EXPRESS',
            'UP*S_GROUND' => 'UPS GROUND',
            'UP*S_3DAYSELECT' => 'UPS 3DAYSELECT',
            'UP*S_AIR_2DAY' => 'UPS AIR_2DAY',
            'UP*S_AIR_2DAYAM' => 'UPS AIR_2DAYAM',
            'UP*S_AIR_1DAYSAVER' => 'UPS AIR_1DAYSAVER',
            'UP*S_AIR_1DAY' => 'UPS AIR_1DAY',
            'UP*S_SUREPOST' => 'UPS SUREPOST',
            'FX*_SMART_POST' => 'FEDEX SMARTPOST',
            'FX*_GROUND_HOME_DELIVERY' => 'FEDEX GROUND_HOME_DELIVERY',
            'FX*_FEDEX_GROUND' => 'FEDEX GROUND',
            'FX*_FEDEX_2_DAY' => 'FEDEX 2_DAY',
            'FX*_FEDEX_EXPRESS_SAVER' => 'FEDEX EXPRESS_SAVER',
            'FX*_STANDARD_OVERNIGHT' => 'FEDEX STANDARD_OVERNIGHT',
            'FX*_PRIORITY_OVERNIGHT' => 'FEDEX PRIORITY_OVERNIGHT',
            'DL*_SMARTMAIL_PARCEL_EXPEDITED_MAX' => 'DHL SMARTMAIL PARCEL EXPEDITED MAX',
            'DL*_SMARTMAIL_PARCEL_EXPEDITED' => 'DHL SMARTMAIL PARCEL EXPEDITED',
            'DL*_SMARTMAIL_PARCEL_GROUND' => 'DHL SMARTMAIL PARCEL GROUND',
            'DL*_SMARTMAIL_PARCEL_PLUS_EXPEDITED' => 'DHL SMARTMAIL PARCEL PLUS EXPEDITED',
            'DL*_SMARTMAIL_PARCEL_PLUS_GROUND' => 'DHL SMARTMAIL PARCEL PLUS GROUND',
            'DL*_PARCEL_INTERNATIONAL_DIRECT' => 'DHL PARCEL INTERNATIONAL DIRECT',
            'EN*USFC' => 'ENDCIA USPS FIRST CLASS',
            'EN*USPM' => 'ENDCIA USPS PRIORITY',
            'EN*USCG' => 'ENDCIA USPS GROUND',
        );

        return $methods;
    }

    public function index(Request $request)
    {
        if ($request->has('start_date')) {
            $start = $request->get('start_date');
        } else if (
            !$request->has('search_for_first') && !$request->has('search_for_second') &&
            !$request->has('store') && !$request->has('status')
        ) {
            $start = date("Y-m-d");
        } else {
            $start = null;
        }

        if (
            $request->has('status') || $request->has('search_for_first') ||
            $request->has('search_for_second') || $request->has('store')
        ) {
            $status = $request->get('status');
        } else {
            $status = 'not_cancelled';
        }
        logger('test start date', [$start, $request->get('end_date')]);

        $orders = Order::with('store', 'items.product', 'customer')
            ->where('is_deleted', '0')
            ->storeId($request->get('store'))
            ->status($status)
            ->searchShipping($request->get('shipping_method'))
            ->withinDate($start, $request->get('end_date'))
            ->search($request->get('search_for_first'), $request->get('operator_first'), $request->get('search_in_first'))
            ->search($request->get('search_for_second'), $request->get('operator_second'), $request->get('search_in_second'));
        // ->get();
        $orders = $orders->paginate($request->get('perPage', 10));

        $total = Order::where('is_deleted', '0')
            ->storeId($request->get('store'))
            ->status($status)
            ->searchShipping($request->get('shipping_method'))
            ->withinDate($start, $request->get('end_date'))
            ->search($request->get('search_for_first'), $request->get('operator_first'), $request->get('search_in_first'))
            ->search($request->get('search_for_second'), $request->get('operator_second'), $request->get('search_in_second'))
            ->selectRaw('SUM(total) as money, SUM(shipping_charge) as shipping, SUM(tax_charge) as tax')
            ->first();

        return [
            'order' => $orders,
            'total' => $total
        ];
    }

    public function show(string $id)
    {
        $order = Order::with(
            'customer',
            'items',
            'items.shipInfo',
            'items.batch.station',
            'items.product',
            'items.allChildSkus',
            'items.parameter_option',
            'notes.user',
            'store'
        )
            ->where('is_deleted', '0')
            ->find($id);
        if (!$order) {
            return response()->json([
                'message' => 'Order Not Found',
                'status' => 203,
                'data' => []
            ], 203);
        }
        $batched = $order->items->filter(function ($item) {
            return $item->batch_number != '0';
        })->count();

        $order['batched'] = $batched;

        return $order;
    }

    public function store(Request $request, string $id)
    {
        if ($id == 'create') {
            $manual_order_count = Order::where('short_order', "LIKE", sprintf("%%WH%%"))
                ->orderBy('id', 'desc')
                ->first();

            $order = new Order;
            $order->short_order = sprintf("WH%d", (10000 + $manual_order_count->id));
            $order->order_id = sprintf("%s-%s", $request->get('store'), $order->short_order);
            $order->order_numeric_time = strtotime('Y-m-d h:i:s', strtotime("now"));
            $order->store_id = $request->get('store');
            $order->order_status = 4;

            if ($request->has('order_date')) {
                $order->order_date = date('Y-m-d h:i:s', strtotime($request->get('order_date') . date(" H:i:s")));
            } else {
                $order->order_date = date('Y-m-d h:i:s', strtotime("now"));
            }

            if ($request->has("ship_message")) {
                $order->ship_message = $request->get("ship_message", "");
            }

            $customer = new Customer();
            $customer->order_id = $order->order_id;
            $customer->save();

            try {
                $order->customer_id = $customer->id;
            } catch (Exception $exception) {
                Log::error('Failed to insert customer id - Update new');
            }
        } else {
            $order = Order::with('store', 'customer')
                ->where('id', $id)
                ->latest()
                ->first();

            $customer = Customer::find($order->customer_id);
        }

        $customer->ship_company_name = $request->get('ship_company_name');
        $customer->bill_company_name = $request->get('bill_company_name');
        $customer->ship_full_name = $request->get('ship_full_name');
        $customer->ship_first_name = $request->get('ship_first_name');
        $customer->ship_last_name = $request->get('ship_last_name');
        $customer->bill_first_name = $request->get('bill_first_name');
        $customer->bill_last_name = $request->get('bill_last_name');
        $customer->ship_address_1 = $request->get('ship_address_1');
        $customer->bill_address_1 = $request->get('bill_address_1');
        $customer->ship_address_2 = $request->get('ship_address_2');
        $customer->bill_address_2 = $request->get('bill_address_2');
        $customer->ship_city = $request->get('ship_city');
        $customer->ship_state = $this->stateAbbreviation($request->get('ship_state'));
        $customer->bill_city = $request->get('bill_city');
        $customer->bill_state = $request->get('bill_state');
        $customer->ship_zip = $request->get('ship_zip');
        $customer->bill_zip = $request->get('bill_zip');
        $customer->ship_country = $request->get('ship_country');
        $customer->bill_country = $request->get('bill_country');
        $customer->ship_phone = $request->get('ship_phone');
        $customer->bill_phone = $request->get('bill_phone');
        $customer->bill_email = $request->get('bill_email');
        $customer->save();

        if ($order->store->validate_addresses == '1') {

            try {
                $isVerified = Shipper::isAddressVerified($customer);
            } catch (Exception $exception) {
                $isVerified = 0;
            }

            if (!$isVerified && $customer->ignore_validation == FALSE) {
                $customer->is_address_verified = '0';
                if ($order->order_status == 4) {
                    $order->order_status = 11;
                }
            } else if (!$isVerified) {
                $customer->is_address_verified = '0';
            }

            $customer->save();
        } else {
            $isVerified = 1;
        }

        $order->order_comments = $request->get('order_comments');


        $order->ship_message = $request->get("ship_message") ?? '';


        if ($request->has('purchase_order')) {
            $order->purchase_order = $request->get('purchase_order');
        }

        if ($request->has('coupon_id')) {
            $order->coupon_id = $request->get('coupon_id');
        }

        if ($request->has('coupon_value')) {
            $order->coupon_value = floatval($request->get('coupon_value'));
        }

        if ($request->has('promotion_value')) {
            $order->promotion_value = floatval($request->get('promotion_value'));
        }

        if ($request->has('shipping_charge')) {
            $order->shipping_charge = floatval($request->get('shipping_charge'));
        }

        if ($request->has('adjustments')) {
            $order->adjustments = floatval($request->get('adjustments'));
        }

        if ($request->has('insurance')) {
            $order->insurance = floatval($request->get('insurance'));
        }

        if ($request->has('gift_wrap_cost')) {
            $order->gift_wrap_cost = floatval($request->get('gift_wrap_cost'));
        }

        if ($request->has('tax_charge')) {
            $order->tax_charge = floatval($request->get('tax_charge'));
        }


        if ($request->get('shipping') != null) {
            if ($request->get('shipping') != 'MN*') {
                Log::info('xShipping2 = : ' . $request->get('shipping'));
                $order->carrier = substr($request->get('shipping'), 0, 2);
                $order->method = substr($request->get('shipping'), 3);
            } else {
                Log::info('xShipping3 = : ' . $request->get('shipping'));
                $order->carrier = 'MN';
                $order->method = 'Give to ' . auth()->user()->username;
            }
        }

        $order->save();

        $grand_sub_total = 0;

        if (!count($request->get('items') ?? [])) {
            return response()->json([
                'message' => 'Empty Items',
                'status' => 203,
                'data' => $order
            ], 203);
        }
        foreach ($request->get('items') as $index => $data) {

            $options = [];
            $exploded = explode("\r\n", trim($data['options'] ?? '', "\r\n"));

            foreach ($exploded ?? [] as $key => $line) {
                $pieces = explode("=", trim($line));
                if (isset($pieces[1])) {
                    $item_option_key = trim($pieces[0]);
                    $item_option_value = trim($pieces[1]);
                } else if (isset($pieces[0]) && strlen(trim($pieces[0])) > 0) {
                    $item_option_key = 'comment_' . $key;
                    $item_option_value = trim($pieces[0]);
                } else {
                    continue;
                }

                $key = str_replace(
                    ["\u00a0", "\u0081", "\u0091"],
                    '',
                    str_replace(" ", "_", preg_replace("/\s+/", " ", $item_option_key))
                );

                if (strpos(str_replace([' ', ','], '', strtolower($item_option_value)), 'nothankyou') === FALSE) {
                    $options[$key] = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
                        return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
                    }, str_replace(["\u00a0", "\u0081", "\u0091"], '', $item_option_value));
                } else {
                    Log::info('Deleted option: ' . $item_option_value);
                }
            }

            if (!isset($data['id'])) {
                $product = Product::where('product_model', $data['child_sku'])->first();
                $item = new Item();
                $item->order_id = $order->order_id;
                $item->store_id = $order->store_id;
                $item->order_5p = $order->id;
                $item->item_unit_price = $data['item_unit_price'];
                $item->item_option = json_encode($options);

                if ($product) {
                    $item->item_code = $product->product_model;
                    $item->item_id = $product->id_catalog;
                    $item->item_description = $product->product_name;
                    $item->item_thumb = $product->product_thumb;
                    $item->item_url = $product->product_url;
                } else {
                    $item->item_description = 'PRODUCT NOT FOUND';
                }
                $item->child_sku = Helper::getChildSku($item);;

                $item->data_parse_type = 'manual';

                if ($order->order_status == 6) {
                    $order->order_status = 4;
                }
            } else {
                $item = Item::find($data['id']);
                $item->child_sku = $data['child_sku'];
                $item->item_option = json_encode($options);
                if (isset($data['item_description'])) {
                    $item->item_description = $data['item_description'];
                }
            }


            $item->item_quantity = $data['item_quantity'];
            $item->save();

            $grand_sub_total += ((int)$item->item_quantity * (float)$item->item_unit_price);
            if (isset($data['id']) && $data['id'] == '') {
                Order::note('CS: Item ' . $item->id . ' added to order', $order->id);
            }
        }
        $order->item_count = count($request->get('items'));
        // $order->sub_total = $order->items->sum;
        $order->total = ($grand_sub_total -
            $order->coupon_value -
            $order->promotion_value +
            $order->gift_wrap_cost +
            $order->shipping_charge +
            $order->insurance +
            $order->adjustments +
            $order->tax_charge);
        $order->save();

        if ($request->has('note')) {
            Order::note(trim($request->get('note')), $order->id);
        }

        $responseType = 201;


        if ($request->get('type') == 'create') {
            //TODO: Send email to customer
            // Notification::orderConfirm($order);
            $message = $isVerified ? sprintf("Order %s is entered.", $order->order_id) : sprintf("Order %s saved but address is unverified", $order->order_id);
            $note_text = "Order Entered Manually";
        } else {
            $message = $isVerified ? sprintf('Order %s is updated', $order->order_id) : sprintf("Order %s updated but address is unverified", $order->order_id);
            $note_text = "Order Info Manually Updated";
        }

        Order::note($note_text, $order->id);

        return response()->json([
            'message' => $message,
            'status' => $responseType,
            'data' => $order
        ], $responseType);
    }

    public function updateStore(Request $request)
    {
        $order = Order::with('items', 'customer')
            ->where('id', $request->get('id'))
            ->where('is_deleted', '0')
            ->first();


        if (!$order) {
            return  response()->json([
                'message' => 'Order not Found',
                'status' => 203,
                'data' => []
            ], 203);
        }

        $old = $order->store_id;
        $new = $request->get('store_select');
        $order->store_id = $new;
        $order->order_id = str_replace($old . '-', '', $order->order_id);
        $order->save();


        foreach ($order->items ?? [] as $item) {
            $item->store_id = $new;
            $item->order_id = str_replace($old . '-', '', $item->order_id);
            $item->save();
        }

        $order->customer->order_id = str_replace($old . '-', '', $order->customer->order_id);
        $order->customer->save();

        $notes = Note::where('order_id', 'LIKE', $old . '%')->get();

        foreach ($notes as $note) {
            $old677676051 = 'test';
            $note->order_id = str_replace($old677676051 . '-', '', $note->order_id);
            $note->save();
        }

        $notes = Note::where('note_text', 'LIKE', '%' . $old . '%')
            ->get();

        foreach ($notes as $note) {
            $note->note_text = str_replace($old . '-', '', $note->note_text);
            $note->save();
        }

        return response()->json(
            [
                'message' => 'Order Store Updated',
                'status' => 201,
                'data' => $order
            ],
            201
        );
    }

    public function updateMethod(Request $request)
    {
        $order = Order::with('store')
            ->where('id', $request->get('id'))
            ->latest()
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Order not Found',
                'status' => 203,
                'data' => []
            ], 203);
        }

        $old_method = $order->carrier != null ? $order->carrier . ' ' . $order->method : 'DEFAULT';

        if ($request->get('method') == 'MN') {
            Order::note('CS: Ship Method set from ' . $old_method . ' to MANUAL', $order->id);
            $order->carrier = 'MN';
            $order->method = $request->get('method_note');
        } else if ($request->get('shipping_method') == '' && strlen($order->carrier) > 0) {
            Order::note('CS: Ship Method set from ' . $old_method . ' to DEFAULT SHIPPING', $order->id);
            $order->carrier = null;
            $order->method = null;
        } else if (
            $request->get('shipping_method') != '' &&
            $request->get('shipping_method') != $order->carrier . '*' . $order->method
        ) {
            $order->carrier = substr($request->get('shipping_method'), 0, strpos($request->get('shipping_method'), '*'));
            $order->method = substr($request->get('shipping_method'), strpos($request->get('shipping_method'), '*') + 1);
            Order::note('CS: Ship Method set from ' . $old_method . ' to ' . $order->carrier . ' ' . $order->method, $order->id);
        }

        $order->save();

        return response()->json([
            'message' => 'Shipping Method Updated',
            'status' => 201,
            'data' => $order
        ], 201);
    }

    public function updateShipDate(Request $request)
    {
        $order = Order::with('store')
            ->where('id', $request->get('id'))
            ->latest()
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Order not Found',
                'status' => 203,
                'data' => []
            ], 203);
        }

        if (substr($request->get('ship_date'), 0, 1) == '0' || $request->get('ship_date') == '') {
            if ($order->ship_date != null) {
                Order::note('CS: Ship Date Unset', $order->id);
                $order->ship_date = null;
            }
        } else if ($order->ship_date != $request->get('ship_date')) {
            Order::note('CS: Ship Date set to ' . $request->get('ship_date'), $order->id);
            $order->ship_date = $request->get('ship_date');
        }

        $order->save();

        return response()->json([
            'message' => 'Shipping Date Updated',
            'status' => 201,
            'data' => $order
        ], 201);
    }

    public function batchedOrder(string $id)
    {
        $order = Order::with('items', 'customer', 'store')
            ->where("id", $id)
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'The order cannot be found or an error happened',
                'status' => 203,
                'data' => []
            ], 203);
        }

        Batching::auto(0, [$order->store->store_id], 1, $order->id);

        return response()->json([
            'message' => 'Order has been successfully batched!',
            'status' => 201,
            'data' => $order
        ], 201);
    }

    public function operatorOption()
    {
        $operatorList = [
            'in' => 'In',
            'not_in' => 'Not In',
            'starts_with' => 'Starts With',
            'ends_with' => 'Ends With',
            'equals' => 'Equals',
            'not_equals' => 'Not Equal',
            'less_than' => 'Less Than',
            'greater_than' => 'Greater Than',
        ];
        $operators = [];
        foreach ($operatorList ?? [] as $key => $value) {
            $operators[] = [
                'label' => $value,
                'value' => $key,
            ];
        };
        return $operators;
    }
    public function searchOption()
    {
        $search = [];
        foreach (Order::$search_in ?? [] as $key => $value) {
            $search[] = [
                'label' => $value,
                'value' => $key,
            ];
        };
        return $search;
    }
    public function statusOption()
    {
        $statuses = [];
        foreach (Order::statuses() ?? [] as $key => $value) {
            $statuses[] = [
                'label' => $value,
                'value' => $key,
            ];
        };
        return $statuses;
    }
    public function storeOption()
    {
        $stores = [];
        foreach (Store::list('%', '%', 'none') ?? [] as $key => $value) {
            $stores[] = [
                'label' => $value,
                'value' => $key,
            ];
        };
        return $stores;
    }

    public function shipOption()
    {
        $stores = [];
        foreach ($this->listMethods() ?? [] as $key => $value) {
            $stores[] = [
                'label' => $value,
                'value' => $key,
            ];
        };
        return $stores;
    }

    public function emailTypeOption()
    {
        $emailTemplates = EmailTemplate::where('is_deleted', '0')->get();
        $data[] = [
            'label' => 'Email',
            'value' => 0,
        ];
        foreach ($emailTemplates ?? [] as $item) {
            $data[] = [
                'label' => $item->message_type,
                'value' => $item->id,
            ];
        };
        return $data;
    }

    public function shippingMethodOption()
    {
        $data = [];
        foreach (Shipper::listMethods() ?? [] as $key => $value) {
            $data[] = [
                'label' => $value,
                'value' => $key,
            ];
        };
        return $data;
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
            return response()->json([
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

    public function curlPost($url, $purchaseData)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $purchaseData);
        //        curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 0);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    public function initialTokenGenerateRequest(Request $request)
    {
        // Set variables for our request
        $shop = "monogramonline"; #$_GET['shop'];
        $api_key = "8d31a3f2242c3b3d1370d6cba9442b47"; #previous --- //"b1f4196ff20279e3747ad1c048e7d0d4";
        //        $scopes = "read_orders,write_products";
        $scopes = "read_orders,write_orders,read_products,write_products,read_customers,write_customers,read_inventory,write_inventory,read_fulfillments,write_fulfillments,read_assigned_fulfillment_orders,write_assigned_fulfillment_orders,read_merchant_managed_fulfillment_orders,write_merchant_managed_fulfillment_orders,read_third_party_fulfillment_orders,write_third_party_fulfillment_orders,read_shipping,write_shipping,read_checkouts,write_checkouts,read_price_rules,write_price_rules,read_discounts,write_discounts,read_product_listings,read_locations";
        //        $redirect_uri = "http://dev.monogramonline.com/generate_shopify_token"; #"http://localhost/generate_token.php";
        $redirect_uri = "https://order.monogramonline.com/generate_shopify_token";

        // Build install/approval URL to redirect to
        $install_url = "https://" . $shop . ".myshopify.com/admin/oauth/authorize?client_id=" . $api_key . "&scope=" . $scopes . "&redirect_uri=" . urlencode($redirect_uri);
        //dd($install_url);
        // Redirect
        //        header("Location: " . $install_url);
        return response()->json([
            'message' => 'Redirecting to Shopify',
            'link' => $install_url,
            'data' => []
        ]);
        return redirect()->away($install_url);
    }

    public function generateShopifyToken(Request $request)
    {
        // Set variables for our request
        $api_key = "8d31a3f2242c3b3d1370d6cba9442b47"; #previous --- //"b1f4196ff20279e3747ad1c048e7d0d4";
        $shared_secret = "7cf2c4f1481efe48b38afc6d2287a419"; #previous --- //"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";
        $params = $request->all();  #$_GET; // Retrieve all request parameters
        $hmac = $request->get('hmac');    #$_GET['hmac']; // Retrieve HMAC request parameter

        $params = array_diff_key($params, array('hmac' => '')); // Remove hmac from params
        ksort($params); // Sort params lexographically

        $computed_hmac = hash_hmac('sha256', http_build_query($params), $shared_secret);
        //dd($hmac, $computed_hmac);
        // Use hmac data to check that the response is from Shopify or not
        if (hash_equals($hmac, $computed_hmac)) {

            // Set variables for our request
            $query = array(
                "client_id" => $api_key, // Your API key
                "client_secret" => $shared_secret, // Your app credentials (secret key)
                "code" => $params['code'] // Grab the access key from the URL
            );

            // Generate access token URL
            $access_token_url = "https://" . $params['shop'] . "/admin/oauth/access_token";

            // Configure curl client and execute request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_URL, $access_token_url);
            curl_setopt($ch, CURLOPT_POST, count($query));
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($query));
            $result = curl_exec($ch);
            curl_close($ch);

            // Store the access token
            $result = json_decode($result, true);
            $access_token = $result['access_token'];

            // Show the access token (don't do this in production!)
            return response()->json([
                'message' => 'Access Token Created',
                'data' => [
                    'access_token' => $access_token
                ]
            ]);
            // echo $access_token;
        } else {
            // Someone is trying to be shady!
            return response()->json([
                'message' => 'This request is NOT from Shopify!',
                'data' => []
            ]);
        }
    }

    public function getShopifyOrderByOrderNumber(Request $request)
    {

        if ($request->get('orderno')) {
            //            $this->token ="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";
            //            $this->shop = "monogramonline";            //no 'myshopify.com' or 'https'
            $array = [
                'ids' => $request->get('orderno')
            ];

            $helper = new Helper;
            $date = $request->get("date", "2023-01");
            $orderInfo = $helper->shopify_call("/admin/api/2023-01/orders.json", $array, 'GET');
            $orderInfo = json_decode($orderInfo['response'], JSON_PRETTY_PRINT);

            return response()->json([
                'message' => "This is response data",
                'data' => [
                    'orderInfo' => $orderInfo,
                    "rderInfo['orders'][0]['line_items']" => $orderInfo['orders'][0]['line_items'] ?? null
                ]
            ]);
        } else {
            //https://order.monogramonline.com/getShopifyorderbyordernumber?orderno=4903244300451
            return response()->json([
                'message' => 'Order id not found',
                'data' => []
            ]);
        }
    }

    public function synOrderByDate(Request $request)
    {
        //        return false;
        # https://order.monogramonline.com/synorderbydate?created_at_max=2023-01-20&created_at_min=2020-03-20
        $shopifyOrdeIds = [];
        $ordersIn5p = [];

        if ($request->get("created_at_min")) {
            $created_at_min = $request->get("created_at_min");
        } else {
            #$created_at_min = date("Y-m-d"); // 2020-03-01
            $created_at_min =  date("Y-m-d TH:i:s", strtotime('-2 hour'));
        }

        if ($request->get("created_at_max")) {
            $created_at_max = $request->get("created_at_max") . " T23:59:59-05:00";
        } else {
            $created_at_max = date("Y-m-d") . " T23:59:59-05:00"; // 2020-03-01
        }

        if ($created_at_max) {

            $array = array(
                "created_at_min" => $created_at_min, #2020-04-01T00:00:00-05:00
                "created_at_max" => $created_at_max, #2020-04-13T23:59:59-05:00
                "limit" => 10,
                "fields" => "created_at,id,name,total-price"
            );

            $helper = new Helper;
            $orderInfo = $helper->shopify_call("/admin/api/2022-01/orders.json", $array, 'GET');
            $orderInfo = json_decode($orderInfo['response'] ?? [], JSON_PRETTY_PRINT);
            //            dd($orderInfo);


            if (isset($orderInfo['errors'])) {
                return response()->json([
                    'error' => $orderInfo['errors'],
                    'message' => "Order not found",
                ]);
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
                return response()->json([
                    'message' => "Nothing to insert",
                    "Number of orders in shopify= " . count($shopifyOrdeIdsx) . " - Number of orders in 5p= " . count($existingOrders) . " = diff = " . (count($shopifyOrdeIdsx) - count($existingOrders)),
                    "Missing Orders = ",
                    $shopifyOrdeIds,
                    "Following already inserted: ",
                    $shopifyOrdeIdsWithName
                ]);
            }

            foreach ($shopifyOrdeIds as $orderId) {
                $data = $request->merge(['orderid' => $orderId]);
                $this->getshopifyorder($data);
            }

            return response()->json([
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
            return response()->json([
                'message' => 'Order not found',
                'data' => []
            ]);
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
            return 'http://' . $this->domain . '/media/zakeke/' . $pdfFile;
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

    public function shopifyOrderById($id, $flag = false)
    {
        $array = ['ids' => $id];
        $helper = new Helper;
        $orderInfo = $helper->shopify_call("/admin/api/2023-01/orders.json", $array, 'GET');
        try {
            $orderInfo = json_decode($orderInfo['response'], JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            Log::error('getShopifyOrderError = ' . $e->getMessage());
            dd($e->getMessage());
        }
        if ($flag) {
            return $orderInfo;
        }

        return response()->json([
            'message' => 'Order found',
            'data' => $orderInfo
        ]);
    }

    public function shopifyThumb($orderId, $item_id, $flag = false)
    {
        $data = $this->shopifyOrderById($orderId, true);
        info($data['orders']);
        if (!isset($data['orders'])) {
            Log::error('order is not available');
            return response()->json([
                'message' => "Order is not available",
            ], 200);
        }
        if (!isset($data['orders'][0])) {
            return false;
        }
        $order = $data['orders'][0];
        if (!empty($order)) {
            $items = $order['line_items'];
            if (count($items)) {
                foreach ($items as $item) {
                    if ($item['id'] == $item_id) {
                        $productInfo = $this->getShopifyproduct($item['product_id']);
                        if ($flag) return $productInfo['image']['src'];
                        return response()->json([
                            'message' => 'Order Thumb found',
                            'link' => $productInfo['image']['src'],
                            'data' => []
                        ]);
                    }
                }
            } else {
                Log::error('order line items is not available');
                return false;
            }
        } else {
            Log::error('order is not available');
            return false;
        }
    }

    public function updateShopifyThumb($orderId, $item_id)
    {
        $dummy_Image = $this->domain . '/assets/images/no_image.jpg';
        $item = Item::where('item_id', $item_id)->first();
        if ($item) {
            $product = Product::where('product_model', $item->item_code)->first();
            if ($product) {
                $thumb = $this->shopifyThumb($orderId, $item_id, true);
                $product->product_thumb = $thumb ? $thumb : $dummy_Image;
                $product->save();

                return response()->json([
                    'message' => 'Order Thumb successfully Updated',
                    'status' => 201
                ], 201);
            } else {
                Log::error('product is not available');
                return response()->json([
                    'message' => "Product is not available",
                    'status' => 203
                ], 203);
            }

            return response()->json([
                'message' => 'Ship Date Updated',
                'status' => 201
            ], 201);
        } else {
            Log::error('item is not available');
            return response()->json([
                'message' => "Item is not available",
                'status' => 203
            ], 203);
        }
    }

    public function synOrderBetweenId(Request $request)
    {
        # https://order.monogramonline.com/synOrderBetweenId?since_id_from=2940557361315&since_id_to=2947019079843
        $shopifyOrdeIds = [];
        $ordersIn5p = [];

        if ($request->get("since_id_from")) {
            $sinceIdFrom = $request->get("since_id_from");
        } else {
            return response()->json([
                'message' => 'since_id_from = not exist ',
            ]);
        }

        if ($request->get("since_id_to")) {
            $sinceIdTo = $request->get("since_id_to");
        } else {
            return response()->json([
                'message' => 'since_id_to = not exist ',
            ]);
        }

        // echo "The time is " . date("H");

        $created_at_min = date("Y-m-d T H:i:s-05:00", strtotime('-2 hour'));
        $created_at_max = date("Y-m-d", strtotime('-0 days'));

        $array = array(
            "created_at_min" => $created_at_min, #2020-04-01T00:00:00-05:00
            "created_at_max" => $created_at_max . "T23:59:59-05:00", #2020-04-13T23:59:59-05:00
            "limit" => $request->get("limit") ?? 5,
            "fields" => "created_at,id,name,total-price"
        );
        //dd("synOrderBetweenId", $array, $created_at_min, $sinceIdFrom, $sinceIdTo);

        if ($request->get('since_id_from')) {

            $helper = new Helper;

            $array = array(
                "since_id" => 2942795514019,
                "limit" => $request->get("limit") ?? 5,
                "fields" => "created_at,id,name,total-price,limit"
            );

            $orderInfo = $helper->shopify_call("/admin/api/2023-01/orders.json", $array, 'GET');
            $orderInfo = json_decode($orderInfo['response'], JSON_PRETTY_PRINT);

            if (isset($orderInfo['errors'])) {
                return response()->json([
                    'message' => $orderInfo['errors'], " Order not found",
                ]);
            }

            $shopifyOrdeIdsWithName = [];
            foreach ($orderInfo['orders'] as $key => $order) {
                $shopifyOrdeIds[$order['id']] = $order['id'];
                $shopifyOrdeIdsWithName[$order['name']] = $order['id'];
                //                Log::info("Order_id from Shopify = ".$order['id']);
            }

            $shopifyOrdeIdsx = $shopifyOrdeIds;
            //dd($array, $orderInfo, $shopifyOrdeIds,$shopifyOrdeIdsWithName);
            $created_at_min = "sdsd";
            $created_at_max = "sdsds";

            ########### Code for get list of orders numbers by Date ###################
            $existingOrders = Order::where('orders.is_deleted', '0')->where(
                'orders.order_date',
                '>=',
                $created_at_min . ' 00:00:00'
            )->where(
                'orders.order_date',
                '<=',
                $created_at_max . ' 23:59:59'
            )->where(
                'orders.store_id',
                '=',
                '52053153'
            )->latest('orders.created_at')->limit(5000)->get([
                'orders.short_order',
                'orders.order_id',
                'order_date',
            ])->toArray();
            foreach ($existingOrders as $key => $orderId) {
                //                Log::info("Order_id from 5p = ".$orderId['short_order']);
                $ordersIn5p[] = $orderId['short_order'];
                if (isset($shopifyOrdeIds[$orderId['short_order']])) {
                    unset($shopifyOrdeIds[$orderId['short_order']]);
                }
            }
            ########### Code for get list of orders numbers by Date ###################
            if (empty($shopifyOrdeIds)) {
                return response()->json([
                    'message' => "Nothing to insert",
                    'data' => "Nothing to insert",
                    "Number of orders in shopify= " . count($shopifyOrdeIdsx) . " - Number of orders in 5p= " . count($existingOrders) . " = diff = " . (count($shopifyOrdeIdsx) - count($existingOrders)),
                    "Missing Orders = ",
                    $shopifyOrdeIds,
                    "Following already inserted: ",
                    $shopifyOrdeIdsWithName
                ]);
            }

            $ch = curl_init();
            foreach ($shopifyOrdeIds as $key => $orderId) {
                $url = $this->domain . "/getshopifyorder?orderid=" . $orderId;
                curl_setopt($ch, CURLOPT_URL, $url);
                $result = curl_exec($ch);
                Log::info(print_r($result));
            }
            curl_close($ch);

            return response()->json([
                'message' => 'Order Synced',
                'data' => [
                    "Number of orders in shopify= " . count($shopifyOrdeIdsx) . " - Number of orders in 5p= " . count($existingOrders) . " = diff = " . (count($shopifyOrdeIdsx) - count($existingOrders)),
                    "Missing Orders = ",
                    $shopifyOrdeIds,
                    $shopifyOrdeIdsx,
                    $ordersIn5p,
                    $shopifyOrdeIdsWithName
                ]
            ]);
        } else {
            return response()->json([
                'message' => 'Order Not found',
            ]);
        }
    }
    public function deleteOrderById($id)
    {
        $order = Order::where('short_order', $id)->first();
        if ($order) {
            $order->is_deleted = "1";
            $order->save();
            Item::where('order_5p', $order->id)->update(['is_deleted' => 1]);
            Customer::where('id', $order->customer_id)->update(['is_deleted' => 1]);
            return response()->json([
                'message' => 'Order Deleted',
                'status' => 201
            ], 201);
        } else {
            return response()->json([
                'message' => 'Order Not found',
                'status' => 203
            ], 203);
        }
    }

    public function deleteOrderByDate(Request $request)
    {
        $orders = Order::where('is_deleted', '0')->where(
            'order_date',
            '>=',
            $request->get('from') . ' 00:00:00'
        )->where(
            'order_date',
            '<=',
            $request->get('to') . ' 23:59:59'
        )->get();

        $error_list = [];
        $success_list = [];

        foreach ($orders as $order) {
            $data = $this->deleteOrderById($order->short_order);
            if ($data->getStatusCode() == 203) {
                $error_list[] = $order->order_id;
            }
            if ($data->getStatusCode() == 201) {
                $success_list[] = $order->order_id;
            }
        }

        return response()->json([
            'message' => 'Order Deleted',
            'error list' => $error_list,
            'success list' => $success_list,
        ]);
    }
}
