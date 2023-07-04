<?php

namespace Ship;

use App\Models\Batch;
use App\Http\Controllers\ZakekeController;
use App\Models\ItemShip;
use App\Models\Batch as ModelsBatch;
use App\Models\Item;
use App\Models\Order;
use App\Models\Ship;
use App\Models\ShipmentManifest;
use App\Models\Wap;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use library\Helper;
use Monogram\Ship\DHL;
use Monogram\Ship\ENDICIAL;
use Ups\AddressValidation;
use Ups\Entity\Address;
use Ups\Shipping;


class Shipper
{
    /* Accepts 5p Order Id and QC or WAP origin flag
      If comes from QC, also require batch_number
      If comes from WAP accept partial shipment flag */
    public static function isAddressVerified($customer)
    {
        $state = Helper::stateAbbreviation($customer->ship_state);
        $country_code = self::getcountrycode($customer->ship_country);

        if (!$country_code) {
            return FALSE;
        }

        if ($country_code != 'US') {
            return TRUE;
        }

        $usps = new USPS;

        $val = $usps->validate(
            $customer->ship_address_1,
            $customer->ship_address_2,
            $customer->ship_zip,
            $customer->ship_city,
            $customer->ship_state
        );

        if ($val) {

            $customer->ship_address_1 = $val->Address2;
            $customer->ship_address_2 = $val->Address1;
            $customer->ship_city = $val->City;
            $customer->ship_state = $val->State;
            $customer->ship_zip = $val->Zip5 . '-' . $val->Zip4;
            $customer->save();
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public static function getcountrycode($country_name)
    {
        $country_code_null = substr($country_name, 2, 1);
        if ($country_code_null == " ") {

            $country_code = substr($country_name, 0, 2);
            return $country_code;
        } elseif (strtolower(substr($country_name, 0, 2)) == "us") {

            return 'US';
        } elseif (strtolower($country_name) == 'united states') {

            return 'US';
        } elseif (strlen($country_name) == 2) {

            return $country_name;
        } else {

            return false;
        }
    }

    public static function importWS($order_id)
    {
        //look for record in ws import table
    }

    public function createShipment($origin, $order_id, $batch_number = NULL, $packages = [0], $item_ids = null, $params = [])
    {
        if (empty($origin)) {
            return 'ERROR: Origin not set. Order: ' . $order_id . ' Origin: ' . $origin;
        }

        if ($origin != 'QC' && $origin != 'WAP') {
            return 'ERROR: Unrecognized origin.  Order: ' . $order_id . ' Origin: ' . $origin;
        }

        if (empty($order_id)) {
            return 'ERROR: Order ID not set. Origin: ' . $origin;
        }

        $order = Order::with('store', 'customer')
            ->find($order_id);

        if (count($order) == 0) {
            return 'ERROR: Order Not Found Order: ' . $order_id . ' Origin: ' . $origin;
        }

        if (empty($order->customer)) {
            return 'ERROR: Customer Not Found.  Order: ' . $order_id . ' Origin: ' . $origin;
        }

        if (empty($order->store)) {
            return 'ERROR: Store Not Found.  Order: ' . $order_id . ' Origin: ' . $origin;
        }

        // if (0) { //($order->customer->ignore_validation == FALSE) {
        //
        //     $val = $this->validateAddress ($order->customer);
        //
        //     if($val['isAmbiguous']){
        //
        //         Log::info('Address Validation: Ambiguous address ' . $order_id);
        //
        //         return ['ambiguous', $val['ambiguousAddress'], $order->customer_id];
        //     }
        //
        //     if($val['error'] != TRUE) {
        //         $order->order_status = 11;
        //         $order->save();
        //
        //         return 'ERROR: ' . $val['error'] . '. Order: ' . $order_id . ' Origin: ' . $origin;
        //     }
        //
        //     if(!$val['validateAddress']){
        //         $order->order_status = 11;
        //         $order->save();
        //         return 'ERROR: Please call Customer Service Department for Update correct Shipping address. Order: ' . $order_id . ' Origin: ' . $origin;
        //     }
        //
        // }

        if ($origin == 'QC') {

            $all_items = Item::where('order_5p', $order_id)
                ->searchStatus('shippable')
                ->whereIn('id', $item_ids)
                ->searchStatus('production')
                ->where('is_deleted', '0')
                ->get();

            if (empty($batch_number)) {
                return 'ERROR: Batch number not set. Order: ' . $order_id . ' Origin: ' . $origin;
            }

            $items = Item::selectRaw('id, (item_unit_price * item_quantity) as amount, item_description')
                ->where('order_5p', $order_id)
                ->where('is_deleted', '0')
                ->searchStatus('production')
                ->where('batch_number', $batch_number)
                ->get();

            if (count($all_items) > count($items)) {
                return 'ERROR: Shippable items outside of batch, cannot ship this order from QC. Order: ' . $order_id . ' Batch: ' . $batch_number;
            }
        } elseif ($origin == 'WAP' && ($item_ids == null || empty($item_ids))) {

            $items = Item::selectRaw('id, (item_unit_price * item_quantity) as amount, item_description')
                ->where('order_5p', $order_id)
                ->whereIn('id', $item_ids)
                ->where('is_deleted', '0')
                ->searchStatus('wap')
                ->get();
        } elseif ($origin == 'WAP' && ($item_ids != null && !empty($item_ids))) {

            $items = Item::selectRaw('id, (item_unit_price * item_quantity) as amount, item_description')
                ->where('order_5p', $order_id)
                ->whereIn('id', $item_ids)
                ->where('is_deleted', '0')
                ->searchStatus('wap')
                ->get();
        } elseif ($origin == 'OR') {

            if (is_array($item_ids)) {

                $items = Item::selectRaw('id, (item_unit_price * item_quantity) as amount, item_description')
                    ->where('is_deleted', '0')
                    ->searchStatus('shippable')
                    ->get();
            } else {

                $items = Item::selectRaw('id, (item_unit_price * item_quantity) as amount, item_description')
                    ->whereIn('order_5p', $order_id)
                    ->where('is_deleted', '0')
                    ->searchStatus('shippable')
                    ->get();
            }
        }

        if (count($items) < 1) {
            return 'ERROR: No items to Ship. Order: ' . $order_id . ' Batch: ' . $batch_number;
        }

        $unique_order_id = $this->generateShippingUniqueId($order->id);
        Log::info('Shipping Order ' . $unique_order_id);

        $total_amount = $items->sum('amount');
        $item_ids = array_column($items->toArray(), 'id');
        if ($order->carrier == 'UP') {   # UP = UPS
            $carrier = new UPS;
            $trackingInfo = $carrier->getLabel($order->store, $order, $unique_order_id, $order->method, $packages);
        } elseif ($order->carrier == 'FX') {    # FX = FEDEX
            $carrier = new \Ship\FEDEX;;
            $trackingInfo = $carrier->getLabel($order->store, $order, $unique_order_id, $order->method, $packages);
        } elseif ($order->carrier == 'US') {    # USPS = FEDEX
            $carrier = new Post;
            $trackingInfo = $carrier->getLabel($order->store, $order, $unique_order_id, $order->method, $packages[0], $params, $items);

            $shipmentManifest = new ShipmentManifest();
            $shipmentManifest->ship_id = $trackingInfo['ship_id'] ?? uniqid("SHIP");
            $shipmentManifest->tracking_code = $trackingInfo['tracking_number'] ?? uniqid("TRACK");
            $shipmentManifest->invoice_number = $trackingInfo['unique_order_id'] ?? uniqid("INVOICE");
            $shipmentManifest->ship_from = $trackingInfo['ship_from'] ?? uniqid("SHIP-FROM");
            $shipmentManifest->batched = 0;
            $shipmentManifest->save();
        } elseif ($order->carrier == 'DL' || $order->carrier == null) {    # DL = DHL
            $carrier = new DHL;
            $trackingInfo = $carrier->getLabel($order->store, $order, $unique_order_id, $order->method, $packages);
        } elseif ($order->carrier == 'EN') {
            $carrier = new ENDICIAL;
            $trackingInfo = $carrier->getLabel($order->store, $order, $unique_order_id, $order->method, $packages);
            //            dd("trackingInfo = ",$trackingInfo);
        } elseif ($order->carrier == 'MN') {    # MN = MANUAL SHIPPING
            $trackingInfo = array();
            $trackingInfo['unique_order_id'] = $unique_order_id;
            $trackingInfo['tracking_number'] = auth()->user()->id . date("_Ymd_His");
            $trackingInfo['shipping_id'] = 'none';
            $trackingInfo['mail_class'] = $order->method;
            $trackingInfo['image'] = null;
        } else {
            Log::error('Unrecognized Carrier ' . $order->carrier . ' - ' . $unique_order_id);
            return 'ERROR: Unrecognized Carrier. Order: ' . $order_id;
        }

        if (is_array($trackingInfo)) {

            $this->insertTracking($trackingInfo, $item_ids, $order->id, $total_amount, $order->order_id, $packages);

            $image = (string) $trackingInfo['image'];

            if ($order->store->packing_list == 'Z' || $order->store->packing_list == 'B') {
                $slip = $this->packingSlip($order, $item_ids);
            } else {
                $slip = '';
            }

            $store_label = '';
            $reminder = '';

            if ($order->store->ship_label == '1') {
                $className = 'Market\\' . $order->store->class_name;
                $controller = new $className;
                $store_info = $controller->shipLabel($order->store->store_id, $unique_order_id, $order->order_id);
                if (is_array($store_info)) {
                    $reminder = $store_info[0];
                    $store_label = $store_info[1];
                }
            }

            if ($origin === 'WAP') {
                $items = Item::selectRaw('id')
                    ->where('order_5p', $order_id)
                    ->where('is_deleted', '0')
                    ->searchStatus('wap')
                    ->get();
                if (!(count($items) >= 1)) {
                    Wap::emptyBin($order_id);
                }
            }

            $image = trim(preg_replace('/\n+/', ' ', $image));

            //$image = str_replace("\00", " ", $image);

            $labels = str_replace(array("'", "\"", "&quot;"), " ", htmlspecialchars($slip . $store_label)) . $image;

            $this->saveLabels($labels, $unique_order_id);

            return ['reminder' => $reminder, 'unique_order_id' => $unique_order_id];
        } else {
            return $trackingInfo . ' - Order: ' . $order_id . ' Origin: ' . $origin;
        }
    }

    private function generateShippingUniqueId($order_id)
    {
        $ships = Ship::where('order_number', $order_id)
            ->get()
            ->pluck('unique_order_id', 'id')
            ->toArray();

        $lines = count(array_unique($ships));

        if ($lines == 0) {
            return sprintf("%s-%s", $order_id, $lines);
        } else {
            $lastNumber = [];
            $lines = array_unique($ships);

            foreach ($lines as $line) {
                $lastNumber[] = (int)substr($line, -1);
            }
            $maxLastNumber = max($lastNumber);

            return sprintf("%s-%s", $order_id, $maxLastNumber + 1);
        }
    }

    private function insertTracking($trackingInfo, $item_ids, $order_number, $total_amount, $note_order, $packages = [0])
    {
        if ($trackingInfo['unique_order_id']) {


            $order = Order::find($order_number);

            /*
             * This fixes duplication of tracking number when it's
             * being ordered manually.
             * Only touches it on Manual entered orders
             */
            if (isset($trackingInfo['type']) && $trackingInfo['type'] === "Manually Entered") {
                $found = Ship::where("order_number", $order)->get();
                if ($found) {
                    return null;
                }
            }

            $ship = new Ship;
            $ship->order_number = $order_number;
            $ship->unique_order_id = (string) $trackingInfo['unique_order_id'];
            if ($order) {
                $ship->store_id = $order->store_id;
            }
            $ship->tracking_number = (string) $trackingInfo['tracking_number'];
            if (isset($trackingInfo['type'])) {
                $ship->tracking_type = (string) $trackingInfo['type'];
            }
            if (isset($trackingInfo['transaction_id'])) {
                $ship->transaction_id = (string) $trackingInfo['transaction_id'];
            }
            $ship->shipping_id = (string) $trackingInfo['shipping_id'];
            $ship->mail_class = (string) $trackingInfo['mail_class'];
            $ship->post_value = $total_amount;
            $ship->postmark_date = date("Y-m-d");
            $ship->transaction_datetime = date("Y-m-d H:i:s");
            $ship->actual_weight = array_sum($packages);
            $ship->package_count = count($packages);
            $ship->user_id = auth()->user()->id;
            $ship->save();

            $items = Item::whereIn('id', $item_ids)->get();
            foreach ($items as $item) {
                $item->tracking_number = (string) $trackingInfo['tracking_number'];
                $item->item_status = 2;
                $item->save();

                $this->setOrderFulfillment(
                    $order->short_order, # Shopefi Order_ID = 2112301105285
                    $item->item_id,
                    $item->item_quantity,
                    (string) $trackingInfo['tracking_number'],
                    $trackingInfo['mail_class']
                ); // method = $trackingCompany

                if ($item->batch_number != '0') {
                    Batch::isFinished($item->batch_number);
                }

                $relation = new ItemShip;
                $relation->item_id = $item->id;
                $relation->ship_id = $ship->id;
                $relation->save();
            }

            $items_left = Item::where('order_5p', $order_number)
                ->searchStatus('shippable')
                ->where('is_deleted', '0')
                ->count();

            if ($items_left == 0) {
                $order->order_status = 6;
                $order->save();
            } elseif ($order->order_status == 10) {
                $order->order_status = 4;
                $order->save();
            }

            // Add note history by order id
            Order::note("Update Tracking# " . (string) $trackingInfo['tracking_number'], $order_number, $note_order);


            try {
                ZakekeController::setOrderAsShippedShipStation($order->short_order, (string) $trackingInfo['tracking_number']);
            } catch (\Exception $exception) {
            }
            return $ship;
        }
    }

    public function setOrderFulfillment($orderId, $itemLineId, $itemQuantity, $trackingNumber, $trackingCompany)
    {
        $locationId = '37822398597'; // Shipping from which warehouse

        $fulfillment = array(
            'fulfillment' =>
            array(
                'location_id' => $locationId,
                'tracking_number' => (string) $trackingNumber,
                'tracking_company' => $trackingCompany,
                'line_items' => array(array('id' => $itemLineId, 'quantity' => $itemQuantity))
            ),
        );

        $helper = new Helper;
        $post_response = $helper->shopify_call("/admin/api/2020-04/orders/" . $orderId . "/fulfillments.json", json_encode($fulfillment), 'POST', array("Content-Type: application/json"));
        // $post_response = json_decode($post_response['response'], JSON_PRETTY_PRINT); // View Respond result

        return true;
    }

    //   public function validateAddress($customer) {
    //
    //     $cust_val = [];
    //     $cust_val['ship_full_name'] = 		Helper::removeSpecial($customer->ship_full_name);
    //     $cust_val['ship_company_name'] = 	Helper::removeSpecial($customer->ship_company_name);
    //     $cust_val['ship_address_1'] = 		Helper::removeSpecial($customer->ship_address_1);
    //     $cust_val['ship_address_2'] = 		Helper::removeSpecial($customer->ship_address_2);
    //     $cust_val['ship_state'] = 			Helper::removeSpecial($customer->ship_state);
    //     $cust_val['ship_city'] = 			Helper::removeSpecial($customer->ship_city);
    //     $cust_val['ship_country'] = 		Helper::removeSpecial($customer->ship_country);
    //     $cust_val['ship_zip'] = 			Helper::removeSpecial($customer->ship_zip);
    //
    //     $validateStatus['validateAddress'] = true;
    //     $validateStatus['isAmbiguous'] = false;
    //     $validateStatus['ambiguousAddress'] = [];
    //     $validateStatus['error'] = true;
    //
    //     if(!self::getcountrycode($cust_val['ship_country'])){
    //       $validateStatus['error'] = 'Invalid country code <b>'. $cust_val['ship_country'].'</b><br>Please update correct country code format like<br><b>US United States</b><br><b>CA Canada</b><br><b>VI Virgin Islands (U.S.)</b>';
    //       return $validateStatus;
    //     }
    //
    //     $address = new \Ups\Entity\Address();
    //     $address->setAttentionName($cust_val['ship_full_name']);
    //     $address->setBuildingName($cust_val['ship_company_name']);
    //     $address->setAddressLine1($cust_val['ship_address_1']);
    //     $address->setAddressLine2($cust_val['ship_address_2']);
    //     $address->setAddressLine3('');
    //     $address->setStateProvinceCode($cust_val['ship_state']);
    //     $address->setCity($cust_val['ship_city']);
    //     $address->setCountryCode(self::getcountrycode($cust_val['ship_country']));
    //     $address->setPostalCode($cust_val['ship_zip']);
    //     // shipmentDigest
    //     // Ptondereau\LaravelUpsApi\UpsApiServiceProvider
    //     $xav = new \Ups\AddressValidation(env('UPS_ACCESS_KEY'), env('UPS_USER_ID'), env('UPS_PASSWORD'));
    //     $xav->activateReturnObjectOnValidate(); //This is optional
    //     try {
    //       $response = $xav->validate($address, $requestOption = \Ups\AddressValidation::REQUEST_OPTION_ADDRESS_VALIDATION, $maxSuggestion = 5);
    //
    //       if (!$response->isValid()) {
    //         $validateStatus['validateAddress'] = false;
    //       }
    //
    //       if ($response->isAmbiguous()) {
    //         $validateStatus['isAmbiguous'] = true;
    //         $candidateAddresses = $response->getCandidateAddressList();
    //         foreach($candidateAddresses as $address) {
    //           $validateStatus['ambiguousAddress'][] = (array)$address;
    //         }
    //       }
    //
    //
    // // dd($validateStatus);
    //       return $validateStatus;
    //     } catch (\Exception $e) {
    // // 			var_dump($e);
    //       $validateStatus['error'] = $e->getMessage();
    //       return $validateStatus;
    //     }
    //   }

    private function packingSlip($order, $item_ids)
    {
        $name = Helper::removeSpecial($order->customer->ship_full_name);

        $header = "^XA^FX^CF0,70^FO50,50^FDOrder $order->short_order^FS";
        $header .= "^CF0,40^FO50,110^FDCustomer: $name^FS^FO50,150^GB700,1,3^FS";

        $zpl = $header;

        $inshipment = array();
        $shipped = array();
        $unshipped = array();

        foreach ($order->items as $item) {
            if (in_array($item->id, $item_ids)) {
                $inshipment[] = $item;
            } elseif ($item->item_status == 'shipped') {
                $shipped[] = $item;
            } else {
                $unshipped[] = $item;
            }
        }

        $inshipment_header = "^CF0,40^FO50,165^FDIn This Shipment:^FS^FO50,200^GB700,1,3^FS";
        $inshipment_header .= "^CF0,30^FO50,210^FDItem^FS^FO150,210^FDDescription ^FS^FO690,210^FDQTY ^FS^FO50,235^GB700,1,3^FS";

        $zpl .= $inshipment_header;

        $pointer = 245;
        $header_printed = 0;

        foreach ($inshipment as $item) {

            if ($pointer > 1000 || $header_printed == 0) {
                if ($pointer > 1000) {
                    $zpl .= "^XZ^XA";
                }
                $zpl .= $header . $inshipment_header;
                $pointer = 245;
                $header_printed = 1;
            }

            $in = $item->item_description;
            $description = strlen($in) > 35 ? substr($in, 0, 35) . "..." : $in;
            $zpl .= "^CF0,28^FO50,$pointer^FD$item->id^FS^FO150,$pointer^FD$description^FS";
            $zpl .= "^FO700,$pointer^FD$item->item_quantity^FS";
            //   $pointer += 30;
            //   $zpl .= "^FD^FO150,$pointer^FB550,6,,^FD" . Helper::optionTransformer($item->item_option, 1, 0, 0, 1, 0, ' ') . "^FS";
            $pointer += 100;
        }


        if (count($shipped) > 0) {

            $header_printed = 0;

            foreach ($shipped as $item) {
                if ($pointer > 1050 || $header_printed == 0) {
                    if ($pointer > 1050) {
                        $zpl .= "^XZ^XA";
                    }
                    $zpl .= "^FO50,$pointer^GB700,1,3^FS";
                    $pointer += 30;

                    $zpl .= "^CF0,40^FO50,$pointer^FDPreviously Shipped:^FS";
                    $pointer += 35;
                    $zpl .= "^FO50,$pointer^GB700,1,3^FS";
                    $pointer += 10;
                    $zpl .= "^CF0,30^FO50,$pointer^FDItem^FS^FO150,$pointer^FDDescription ^FS^FO690,$pointer^FDQTY ^FS";
                    $pointer += 25;
                    $zpl .= "^FO50,$pointer^GB700,1,3^FS";
                    $pointer += 10;
                    $header_printed = 1;
                }
                $in = $item->item_description;
                $description = strlen($in) > 35 ? substr($in, 0, 35) . "..." : $in;
                $zpl .= "^CF0,22^FO50,$pointer^FD$item->id^FS^FO150,$pointer^FD$description^FS^FO700,$pointer^FD$item->item_quantity^FS";
                $pointer += 30;
            }
        }

        if (count($unshipped) > 0) {

            $header_printed = 0;

            foreach ($unshipped as $item) {

                if ($pointer > 1050 || $header_printed == 0) {
                    if ($pointer > 1050) {
                        $zpl .= "^XZ^XA";
                    }
                    $zpl .= "^FO50,$pointer^GB700,1,3^FS";
                    $pointer += 30;

                    $zpl .= "^CF0,40^FO50,$pointer^FDNot Yet Shipped:^FS";
                    $pointer += 35;
                    $zpl .= "^FO50,$pointer^GB700,1,3^FS";
                    $pointer += 10;
                    $zpl .= "^CF0,30^FO50,$pointer^FDItem^FS^FO150,$pointer^FDDescription ^FS^FO690,$pointer^FDQTY ^FS";
                    $pointer += 25;
                    $zpl .= "^FO50,$pointer^GB700,1,3^FS";
                    $pointer += 10;
                    $header_printed = 1;
                }

                $zpl .= "^CF0,22^FO50,$pointer^FD$item->id^FS^FO150,$pointer^FD$item->item_description^FS^FO700,$pointer^FD$item->item_quantity^FS";
                $pointer += 30;
            }
        }

        $zpl .= "^XZ";

        return $zpl;
    }

    private function saveLabels($graphicImage, $unique_order_id)
    {
        Log::info(sprintf('Saving label %S in %s', $unique_order_id, 'assets/images/shipping_label/' . $unique_order_id . ".zpl"));
        $lock_path = public_path('assets/images/shipping_label/');
        $myfile = fopen($lock_path . $unique_order_id . ".zpl", "wb") or die("Unable to open file!");
        fwrite($myfile, (string) $graphicImage);
        fclose($myfile);
        sleep(3);
        return true;
    }

    // public static function isAddressVerified ($customer)
    // {
    //   $order = $customer->order;
    //   $country_code = self::getcountrycode($customer->ship_country);
    //   if ( ! $country_code ) {
    //     $message = 'Order number ' . $order->order_id . ' invalid country code <b>' . $customer->ship_country . '</b><br>Please update correct country code format like<br><b>US United States</b><br><b>CA Canada</b><br><b>VI Virgin Islands (U.S.)</b>';
    //     throw new \Exception($message);
    //   }
    //
    //   if ($country_code != 'US') {
    //     return true;
    //   }
    //
    //   try {
    //     $address = new Address();
    //   } catch ( \Exception $e ) {
    //     throw $e;
    //   }
    //   $address->setAttentionName($customer->ship_full_name);
    //   $address->setBuildingName($customer->ship_company_name);
    //   $address->setAddressLine1($customer->ship_address_1);
    //   $address->setAddressLine2($customer->ship_address_2);
    //   $address->setAddressLine3('');
    //   $address->setStateProvinceCode($customer->ship_state);
    //   $address->setCity($customer->ship_city);
    //   $address->setCountryCode(self::getcountrycode($customer->ship_country));
    //   $address->setPostalCode($customer->ship_zip);
    //   // shipmentDigest
    //   // Ptondereau\LaravelUpsApi\UpsApiServiceProvider
    //   $addressVerification = new AddressValidation(env('UPS_ACCESS_KEY'), env('UPS_USER_ID'), env('UPS_PASSWORD'));
    //   $addressVerification->activateReturnObjectOnValidate(); //This is optional
    //   try {
    //     $response = $addressVerification->validate($address, $requestOption = AddressValidation::REQUEST_OPTION_ADDRESS_VALIDATION, $maxSuggestion = 15);
    //
    //     if ( $response->isValid() ) {
    //       return true;
    //     } else {
    //       return false;
    //     }
    //
    //     if ( $response->isAmbiguous() ) {
    //       return false;
    //     }
    //
    //   } catch ( \Exception $e ) {
    //     throw  $e;
    //   }
    // }

    public function shipOrder($order, $reship)
    {
        $unique_order_id = $this->generateShippingUniqueId($order->id);

        if ($reship == '0') {

            $items = Item::selectRaw('id, (item_unit_price * item_quantity) as amount')
                ->where('order_5p', $order->id)
                ->where('is_deleted', '0')
                ->searchStatus('shippable')
                ->get();
        } elseif ($reship == '1') {

            $items = Item::selectRaw('id, (item_unit_price * item_quantity) as amount')
                ->where('order_5p', $order->id)
                ->where('is_deleted', '0')
                ->searchStatus('reshipment')
                ->get();
        }

        $total_amount = $items->sum('amount');
        $item_ids = array_column($items->toArray(), 'id');

        if ($order->carrier == 'UP') {
            $carrier = new UPS;
            $trackingInfo = $carrier->getLabel($order->store, $order, $unique_order_id, $order->method);
        } elseif ($order->carrier == 'FX') {
            $carrier = new \Ship\FEDEX;
            $trackingInfo = $carrier->getLabel($order->store, $order, $unique_order_id, $order->method);
        } elseif ($order->carrier == 'US') {
            $carrier = new Post;
            $trackingInfo = $carrier->getLabel($order->store, $order, $unique_order_id, $order->method);
            //$trackingInfo = $carrier->getLabel($order->store, $order, $unique_order_id, $order->method, 1);
        } elseif ($order->carrier == 'DL' || $order->carrier == null) {
            $carrier = new DHL;
            $trackingInfo = $carrier->getLabel($order->store, $order, $unique_order_id, $order->method);
        } elseif ($order->carrier == 'MN') {
            $trackingInfo = array();
            $trackingInfo['unique_order_id'] = $unique_order_id;
            $trackingInfo['tracking_number'] = 'none';
            $trackingInfo['shipping_id'] = 'none';
            $trackingInfo['mail_class'] = $order->method;
            $trackingInfo['image'] = null;
        } elseif ($order->carrier == 'EN') {
            $carrier = new ENDICIAL;
            $trackingInfo = $carrier->getLabel($order->store, $order, $unique_order_id, $order->method);
            //            dd("trackingInfo = ",$trackingInfo);
        } else {
            Log::error('Unrecognized Carrier ' . $order->carrier . ' - ' . $unique_order_id);
            return redirect()->back()->withErrors('ERROR: Unrecognized Carrier');
        }

        if (is_array($trackingInfo)) {

            $this->insertTracking($trackingInfo, $item_ids, $order->id, $total_amount, $order->order_id);
            $image = (string) $trackingInfo['image'];

            $store_label = '';
            $slip = '';
            $reminder = '';

            if ($reship == 0) {
                $slip = $this->packingSlip($order, $item_ids);

                if ($order->store->ship_label == 1) {
                    $className = 'Market\\' . $order->store->class_name;
                    $controller = new $className;
                    $store_info = $controller->shipLabel($order->store->store_id, $unique_order_id, $order->order_id);
                    if (is_array($store_info)) {
                        $reminder = $store_info[0];
                        $store_label = $store_info[1];
                    }
                }
            }

            Wap::emptyBin($order->id);

            $image = trim(preg_replace('/\n+/', ' ', (string) $image));

            $labels = str_replace(array("'", "\"", "&quot;"), " ", htmlspecialchars($slip . $store_label)) . (string) $image;

            $this->saveLabels((string) $labels, $unique_order_id);

            return ['unique_order_id' => $unique_order_id, 'reminder' => $reminder];
        } else {

            return $trackingInfo;
        }
    }

    public function enterTracking($item_id, $order_5p, $track_number, $method)
    {

        info($item_id);

        if ($item_id != 'all') {
            $items = Item::with('order')
                ->where('id', $item_id)
                ->get();
            $order = $items->first()->order;
        } else if ($item_id == 'all') {
            $items = Item::where('order_5p', $order_5p)
                ->where('is_deleted', '0')
                ->searchStatus('shippable')
                ->get();
            $order = Order::find($order_5p);
        } else {
            return 'Item Not Set';
        }

        info("Tracking");
        info($items);
        info($order);

        $item_ids = array();

        foreach ($items as $item) {
            if ($item->item_status == 'wap') {
                Wap::removeItem($item->id, $item->order_5p);
            }

            $item->tracking_number = trim((string) $track_number);
            $item->item_status = 'shipped';

            $item->save();

            if ($item->batch_number != '0') {
                if (!ModelsBatch::isFinished($item->batch_number)) {
                    $item->batch_number = '0';
                }
            }

            $item->save();


            $item_ids[] = $item->id;

            try {
                ZakekeController::setOrderAsShippedShipStation($order->short_order, $track_number);
            } catch (\Exception $exception) {
            }
            Order::note('Tracking number ' . $track_number . ' added to item ' . $item->id, $order->id, $order->order_id);
        }
        $remaining = Item::where('order_5p', $order->id)
            ->searchStatus('shippable')
            ->where('is_deleted', '0')
            ->count();

        if ($remaining == 0) {

            $order->order_status = 6;

            $shipinfo = explode('*', $method);
            $order->carrier = $shipinfo[0];
            if (isset($shipinfo[1])) {
                $order->method = $shipinfo[1];
            } else {
                if ($shipinfo[0] == 'MN') {
                    $order->method = 'Manual Ship';
                }
            }

            $order->save();
        }

        $shipment = Ship::where('shipping_id', trim($track_number))
            ->where('order_number', $order->id)
            ->first();

        if (count($shipment) == 0) {

            $methods = Shipper::listMethods();

            $unique_order_id = $this->generateShippingUniqueId($order->id);

            $trackingInfo['image'] = '';
            $trackingInfo['unique_order_id'] = $unique_order_id;
            $trackingInfo['tracking_number'] = $track_number;
            $trackingInfo['mail_class'] = $methods[$method];
            $trackingInfo['shipping_id'] = $track_number;
            $trackingInfo['type'] = 'Manually Entered';

            $shipment = $this->insertTracking($trackingInfo, $item_ids, $order->id, 0, $order->order_id);
        } else {
            $unique_order_id = $shipment->unique_order_id;
            $shipment->shipping_unique_id = null;
            $shipment->save();
        }

        $label = null;
        $reminder = null;

        if ($order->store->ship_label == '1') {
            $className = 'Market\\' . $order->store->class_name;
            $controller = new $className;
            $store_info = $controller->shipLabel($order->store->store_id, $shipment->unique_order_id, $order->order_id);

            if (is_array($store_info)) {

                $reminder = $store_info[0];
                $labels = str_replace(array("'", "\"", "&quot;"), " ", htmlspecialchars($store_info[1]));

                $this->saveLabels($labels, $shipment->unique_order_id);
            }
        }

        return ['unique_order_id' => $unique_order_id, 'reminder' => $reminder];
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

    public function voidShipment($shipment_id)
    {
        $shipment = Ship::find($shipment_id);

        if (!$shipment) {
            return 'Error: Shipment not Found';
        }

        $mail_class = explode(' ', $shipment->mail_class);
        $store = $shipment->order->store;

        if ($mail_class[0] == 'UPS') {
            $carrier = new UPS;
            $response = $carrier->void($store, $shipment->shipping_id);
        } elseif ($mail_class[0] == 'FEDEX') {
            $carrier = new \Ship\FEDEX;
            $response = $carrier->void($store, $shipment->shipping_id);
        } elseif ($mail_class[0] == 'USPS') {
            $carrier = new Post;
            $response = $carrier->void($store, $shipment->shipping_id);
        } else {
            Log::error('Unrecognized Carrier ' . $mail_class[0] . ' - ' . $shipment->unique_order_id);
            $response = 'Unrecognized Carrier';
        }

        $shipment->order->order_status = 4;
        $shipment->order->save();

        foreach ($shipment->items as $item) {
            if ($item->item_status == 2) {
                $item->item_status = 1;
                $item->tracking_number = null;
                $item->save();
            }
        }

        $shipment->is_deleted = '1';
        $shipment->save();

        Order::note('Shipment ' . $shipment->shipping_id . ' voided', $shipment->order_number);

        return $response;
    }
}
