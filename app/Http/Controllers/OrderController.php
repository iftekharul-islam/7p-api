<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use library\Helper;
use Ship\Shipper;

class OrderController extends Controller
{
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

        $orders = Order::with('store', 'customer', 'items')
            ->where('is_deleted', '0')
            ->storeId($request->get('store'))
            ->status($status)
            ->searchShipping($request->get('shipping_method'))
            ->withinDate($start, $request->get('end_date'))
            ->search($request->get('search_for_first'), $request->get('operator_first'), $request->get('search_in_first'))
            ->search($request->get('search_for_second'), $request->get('operator_second'), $request->get('search_in_second'))
            ->latest();
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

    public function store(Request $request)
    {
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
        $customer->ship_state = Helper::stateAbbreviation($request->get('ship_state'));
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
                $customer->is_address_verified = 0;
                if ($order->order_status == 4) {
                    $order->order_status = 11;
                }
            } else if (!$isVerified) {
                $customer->is_address_verified = 0;
            }

            $customer->save();
        } else {
            $isVerified = 1;
        }

        $order->order_comments = $request->get('order_comments');


        $order->ship_message = $request->get("ship_message", "");


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

        // $item_ids = $request->get('item_id');
        // $item_skus = $request->get('item_sku');
        // $item_options = $request->get('item_option');
        // $item_quantities = $request->get('item_quantity');
        // $item_descriptions = $request->get('item_description');
        // $item_prices = $request->get('item_price');
        // $child_skus = $request->get('child_sku');

        $grand_sub_total = 0;

        if (count($request->get('items'))) {
            return response()->json([
                'message' => 'Empty Items',
                'staus' => 203,
                'data' => []
            ], 203);
        }
        foreach ($request->get('items') as $index => $item) {

            $options = [];
            $exploded = explode("\r\n", trim($item['options'], "\r\n"));

            foreach ($exploded as $key => $line) {
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



            $product = Product::where('product_model', $item['sku'])->first();

            $item = new Item();
            $item->order_id = $order->order_id;
            $item->store_id = $order->store_id;
            $item->order_5p = $order->id;
            $item->item_code = $item['sku'];
            $item->item_unit_price = $item['price'];
            $item->item_option = json_encode($options);

            if ($product) {
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


            $item->item_quantity = $item['quantity'];
            $item->save();

            $grand_sub_total += ((int)$item->item_quantity * (float)$item->item_unit_price);
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

        $responseType = $isVerified ? "success" : "error";

        Notification::orderConfirm($order);
        $message = $isVerified ? sprintf("Order %s is entered.", $order->order_id) : sprintf("Order %s saved but address is unverified", $order->order_id);
        $note_text = "Order Entered Manually";


        Order::note($note_text, $order->id);

        return redirect()->action('OrderController@details', ['order_id' => $order->id])->with($responseType, $message);
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
}
