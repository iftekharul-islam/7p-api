<?php


namespace Monogram\Ship;


use Exception;
use Monogram\Helper;
use Ship\Shipper;
use Illuminate\Support\Facades\Log;
use App\Ship;
use App\DhlManifest;

class DHL
{
    protected $username = 'api.dealtowin';

    protected $password = '0kKv6s2uEM9pZp9I';

    protected $accessToken = '';

    protected  $dhlManifestArray = [];

    protected $getServiceMethodCode = array(
        '_SMARTMAIL_PARCEL_EXPEDITED_MAX' => 631,
        '_SMARTMAIL_PARCEL_EXPEDITED' => 81,
        '_SMARTMAIL_PARCEL_GROUND' => 82,
        '_SMARTMAIL_PARCEL_PLUS_EXPEDITED' => 36,
        '_SMARTMAIL_PARCEL_PLUS_GROUND' => 83,
        '_PARCEL_INTERNATIONAL_DIRECT' => 60,
    );

    public function getLabel($store, $order, $unique_order_id, $method = null, $packages = [0])
    {

//        dd($store, $order, $unique_order_id, $method, $packages);
        $customer = $order->customer;
        $customerName = substr(Helper::removeSpecial($customer['ship_full_name']), 0, 35);
        $customerAddress1 = trim($customer['ship_address_1']);

        $country_code = Shipper::getcountrycode($customer['ship_country']);
        if (isset($customer['ship_address_2'])) {
            $customerAddress2 = trim($customer['ship_address_2']);
        } else {
            $customerAddress2 = "";
        }
        if ($customer['ship_company_name'] != '') {
            $customer_company_name = substr($customer['ship_company_name'], 0, 35);
        } else {
            $customer_company_name = "";
        }
        if (strlen(str_replace(['(', '-', ')', ' '], '', $customer['ship_phone'])) != 10) {
            $customer_phone = '8563203200';
        } else {
            $customer_phone = $customer['ship_phone'];
        }
        #-------------------------------------WEIGHT and Dimensions--------------------------------------------------------#
        $weightMultiplier = 1;


        if (max($packages) < 1) {
            $weightUnit = 'OZ';
            $weightMultiplier = 16;
        } else {
            $weightUnit = 'LB';
        }

        if (max($packages) == 0 || $packages[0] == null || max($packages) < 1) {
            $weightUnit = 'OZ';
            $weightMultiplier = 16;
        } else {
            $weightUnit = 'LB';
        }

        if (array_sum($packages) == 0) {
            $weight = .99;
        } else {
            $weight = array_sum($packages) * $weightMultiplier;
        }

        #---------------------------------------------------------------------------------------------#

        $billingRef1 = $order->short_order;

        if ($order->purchase_order != null) {
            $billingRef2 = $order->purchase_order;
        } else {
            $billingRef2 = '';
        }

        $packageId = "40Monogramonline-".$order->short_order;#str_replace('-', '', $order->id);
        $packageDescription = "Order ".$packageId;

        if (!isset($this->getServiceMethodCode[$method])){
            $method = "_SMARTMAIL_PARCEL_EXPEDITED";
            $this->getServiceMethodCode[$method];
        }

        $json_array = array(
            'shipments' =>
                array(
                    0 =>
                        array(
                            'pickup' => '5326256',
                            'distributionCenter' => 'USEWR1',
                            'packages' =>
                                array(
                                    0 =>
                                        array(
                                            'consigneeAddress' =>
                                                array(
                                                    'name' => $customerName,//'Mohammad Tarikul Islam Jewel',
                                                    'companyName' => $customer_company_name,
                                                    'address1' => $customerAddress1,
                                                    'address2' => $customerAddress2,
                                                    'city' => trim($customer['ship_city']),
                                                    'country' => $country_code,
                                                    'phone' => $customer_phone,
                                                    'postalCode' => $customer['ship_zip'],
                                                    'state' => $customer['ship_state'],
                                                    'email' => $customer['bill_email'],
                                                ),
                                            'packageDetails' =>
                                                array(
                                                    'currency' => 'USD',
                                                    'dutiesPaid' => 'N',
                                                    'orderedProduct' => $this->getServiceMethodCode[$method],//'631',
                                                    'packageId' => $packageId,
                                                    'packageDesc' => $packageDescription,
                                                    'mailtype' => 2, // Irregular Parcel
                                                    'weight' => 1,
                                                    'weightUom' => "OZ", #$weightUnit,
                                                    'dimensionUom' => 'IN',
                                                    'height' => 1,
                                                    'width' => 5,
                                                    'length' => 5,
                                                    'billingRef1' => $billingRef1,
                                                    'billingRef2' => $billingRef2
                                                ),
                                            'returnAddress' =>
                                                array(
                                                    'name' => 'Customer Service Dept',
                                                    'companyName' => substr($store->ship_name, 0, 35),
                                                    'address1' => $store->address_1,
                                                    'address2' => $store->address_2,
                                                    'city' => $store->city,
                                                    'state' => $store->state,
                                                    'country' => 'US',
                                                    'phone' => $store->phone,
                                                    'postalCode' => $store->zip,


                                                ),
                                            'customsDetails' =>
                                                array(
                                                    0 =>
                                                        array(
                                                            'itemDescription' => $packageDescription,
                                                            'countryOfOrigin' => 'US',
//                                                            'hsCode' => 'hscode',
                                                            'packagedQuantity' => 1,
                                                            'itemValue' => 1,
                                                            'skuNumber' => '1P',
                                                        ),
                                                ),
                                        ),
                                ),
                        ),
                ),
        );


        $token = $this->getAccessToken();
        $this->accessToken = $token;
        // Get cURL resource
        $ch = curl_init();
        // Set url
        curl_setopt($ch, CURLOPT_URL, 'https://api.dhlglobalmail.com/v2/label/multi/zpl.json?access_token=' . $token . '&client_id=59995');
        // Set method
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        // Set options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // Set headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json; charset=utf-8",
                "Accept: application/zpl",
            ]
        );

        $body = json_encode($json_array);
        try {
            // Set body
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            // Send the request & save response to $resp
            $resp = curl_exec($ch);
            $decoded_response = json_decode($resp, false, 512, JSON_BIGINT_AS_STRING);

//            echo "<pre>";
//            echo "Respond Result = ";
//            print_r($decoded_response);
//            echo "</pre>";
//            dd($decoded_response, $resp);



            if (!$resp) {
                Log::error('Error: "' . curl_error($ch) . '" - Code: ' . curl_errno($ch));
                return 'Error: "' . curl_error($ch) . '" - Code: ' . curl_errno($ch);
            } else {
                $zpl_label = $decoded_response->data->shipments[0]->packages[0]->responseDetails->labelDetails[0]->labelData;
                $tracking_number = $decoded_response->data->shipments[0]->packages[0]->responseDetails->trackingNumber;
                curl_close($ch);
                $trackingInfo = array();
                $trackingInfo['image'] = $zpl_label;
                if($method == "_PARCEL_INTERNATIONAL_DIRECT"){
                    $trackingInfo['tracking_number'] = $packageId;
                    $trackingInfo['shipping_id'] = $packageId; // Need clarification on this.
                }else{
                    $trackingInfo['tracking_number'] = $tracking_number;
                    $trackingInfo['shipping_id'] = $tracking_number; // Need clarification on this.
                }
                $trackingInfo['unique_order_id'] = $unique_order_id;
                $trackingInfo['mail_class'] = $method;

                $trackingInfo['type'] = 'DHL';
//            dd($trackingInfo, $decoded_response);
//            echo "Response HTTP Status Code : " . curl_getinfo($ch, CURLINFO_HTTP_CODE);
//            echo "\nResponse HTTP Body : " . json_encode($resp);
                return $trackingInfo;
            }

            // Close request to clear up some resources
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return 'ERROR: ' . $e->getMessage();
        }


    }


    //$carrier->getLabel($order->store, $order, $unique_order_id, $order->method);

    public function getAccessToken()
    {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.dhlglobalmail.com/v2/auth/access_token?username=" . $this->username . "&password=" . $this->password . "&state=NY");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));

        $result = curl_exec($ch);

        $api_key = json_decode($result);

        $accessToken = $api_key->data->access_token;

        $error = curl_error($ch);
        curl_close($ch);
        if (empty($error)) {
            return $accessToken;
        } else {
            return false;
        }

    }

    public function trackByNumber($tracking)
    {

        // Get cURL resource
        $ch = curl_init();
//        $testUrl = 'https://api.dhlglobalmail.com/v2/mailitems/track?number='.$tracking.'&access_token='.$token.'&client_id=59995';
//        dd($testUrl);
        // Set url
        curl_setopt($ch, CURLOPT_URL, 'https://api.dhlglobalmail.com/v2/mailitems/track?number=' . $tracking . '&access_token=' . $this->accessToken . '&client_id=59995');
        // Set method
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        // Set options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // Set headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json; charset=utf-8",
                "Accept: application/json",

            ]
        );


        // Send the request & save response to $resp
        $resp = curl_exec($ch);
        dd($resp);

        curl_close($ch);

    }


    public function getDhlManifest($dhlManifestDate)
    {
        $allTracking = Ship::whereNotNull('tracking_number')
            ->where('postmark_date','like',$dhlManifestDate)
            ->where('tracking_type',"DHL")
            ->where('manifestStatus',0)
            ->where('mail_class','!=',"_PARCEL_INTERNATIONAL_DIRECT")
            ->groupBy('unique_order_id')
            ->latest('created_at')
            ->limit(9000)
            ->get([
                'shipping.shipping_id AS packageId',
            ])->toArray();

//        $single = [
//            [
//                "packageId" => "420926209374869903505395463479"
//            ]
//        ];
////$allTracking = $single;
//          dd($dhlManifestDate, $allTracking, json_encode($allTracking));

        if(empty($allTracking)){
          #    echo "No DHL data found";
            Log::error("No DHL data found");
          #    exit();
            return "No DHL data found";
        }


        $json_array['closeoutRequests'][0] = [
                'packages' =>
                    $allTracking
        ];
          #dd($json_array);
        $token = $this->getAccessToken();
        $this->accessToken = $token;
        // Get cURL resource
        $ch = curl_init();
        // Set url
          #curl_setopt($ch, CURLOPT_URL, 'https://api.dhlglobalmail.com/v2/locations/5326256/closeout/all?access_token=' . $token . '&client_id=59995');
        curl_setopt($ch, CURLOPT_URL, 'https://api.dhlglobalmail.com/v2/locations/5326256/closeout/multi.json?access_token=' . $token . '&client_id=59995');
        // Set method
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
          #curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        // Set options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // Set headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json; charset=utf-8",
                "Accept: application/zpl",
            ]
        );

        $body = json_encode($json_array);

        try {
            // Set body
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            // Send the request & save response to $resp
            $resp = curl_exec($ch);
            $decoded_response = json_decode($resp, false, 512, JSON_BIGINT_AS_STRING);
            curl_close($ch);
          #    dd($decoded_response);
            if ($decoded_response->meta->code != '200') {
                if($decoded_response->meta->error[0]->error_message == "There are no items to closeout"){
                    return $decoded_response->meta->error[0]->error_message;
                }

                Log::error('Error DhlManifestPdfUrl: "' . $decoded_response->meta->error[0]->error_message);
          #        return 'Error DhlManifestPdfUrl: "' . curl_error($ch) . '" - Code: ' . curl_errno($ch);
                echo "<pre>";
                    echo "\nPlease Send this error message to Jewel\n\n\n";
                    echo 'API URL with token = https://api.dhlglobalmail.com/v2/locations/5326256/closeout/multi.json?access_token=' . $token . '&client_id=59995';
                    echo "\nRequest Body as Json = ";
                    print_r($body);
                echo "</pre>";

                echo ('Error DhlManifestPdfUrl: "' . $decoded_response->meta->error[0]->error_message);
                    echo "<pre>";
                    echo "\n\n\nRespond Result = ";
                    print_r($decoded_response);
                echo "</pre>";
                dd($decoded_response);
            } else {
                $dhlManifestPdfUrl = $decoded_response->data->closeouts[0]->manifests[0]->url;
                $manifestId = $decoded_response->data->closeouts[0]->manifests[0]->manifestId;

                $this->dhlManifestArray =[];
                $this->dhlManifestArray['manifestDate'] = $dhlManifestDate;
                $this->dhlManifestArray['store_id'] = "52053152";
                $this->dhlManifestArray['mail_class'] = "_SMARTMAIL_PARCEL_EXPEDITED";
                $this->dhlManifestArray['manifestId'] = $manifestId;
                $this->dhlManifestArray['pdf_path'] = $dhlManifestPdfUrl;
                $this->dhlManifestArray['user'] = auth()->user()->id;
                $this->dhlManifestArray['created_at'] = date("Y-m-d H:i:s");
                $this->dhlManifestArray['updated_at'] = date("Y-m-d H:i:s");
                $this->saveDhlManifest($this->dhlManifestArray);
                $this->updateDHLMainfestStatus($dhlManifestDate);
                return "success";
            }

            // Close request to clear up some resources
        } catch (Exception $e) {
            Log::error($e->getMessage());
          #    return 'ERROR: ' . $e->getMessage();
            return $error['error'] = $e->getMessage();
        }


    }

    public function getDhlInternationalManifest($dhlManifestDate)
    {
        $allTracking = Ship::whereNotNull('tracking_number')
            ->where('postmark_date','like',$dhlManifestDate)
            ->where('tracking_type',"DHL")
            ->where('manifestStatus',0)
            ->where('mail_class',"_PARCEL_INTERNATIONAL_DIRECT")
            ->groupBy('unique_order_id')
            ->latest('created_at')
            ->limit(9000)
            ->get([
                'shipping.shipping_id AS packageId',
            ])->toArray();

          #dd($dhlManifestDate, $allTracking, json_encode($allTracking));

        if(empty($allTracking)){
          #    echo "No DHL data found";
            Log::error("No DHL data found");
          #    return "error";
          #    dd($dhlManifestDate, $allTracking, json_encode($allTracking));
            $error = [];
            return "No DHL data found";
        }
        #dd($allTracking);

        $json_array['closeoutRequests'][0] = [
            'packages' =>
                $allTracking

        ];
          #dd($json_array);
        $token = $this->getAccessToken();
        $this->accessToken = $token;
        // Get cURL resource
        $ch = curl_init();
        // Set url
          #curl_setopt($ch, CURLOPT_URL, 'https://api.dhlglobalmail.com/v2/locations/5326256/closeout/all?access_token=' . $token . '&client_id=59995');
        curl_setopt($ch, CURLOPT_URL, 'https://api.dhlglobalmail.com/v2/locations/5326256/closeout/multi.json?access_token=' . $token . '&client_id=59995');
        // Set method
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
          #curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        // Set options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // Set headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json; charset=utf-8",
                "Accept: application/zpl",
            ]
        );

        $body = json_encode($json_array);

        try {
            // Set body
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            // Send the request & save response to $resp
            $resp = curl_exec($ch);
            $decoded_response = json_decode($resp, false, 512, JSON_BIGINT_AS_STRING);
            curl_close($ch);
            #dd($decoded_response, $decoded_response->meta->code);
            if ($decoded_response->meta->code != "200") {
                if($decoded_response->meta->error[0]->error_message == "There are no items to closeout"){
                    return $decoded_response->meta->error[0]->error_message;
                }

                Log::error('Error DhlManifestPdfUrl: "' . $decoded_response->meta->error[0]->error_message);

                echo "<pre>";
                    echo "\nPlease Send this error message to Jewel\n\n\n";
                    echo 'API URL with token = https://api.dhlglobalmail.com/v2/locations/5326256/closeout/multi.json?access_token=' . $token . '&client_id=59995';
                    echo "\nRequest Body as Json = ";
                    print_r($body);
                echo "</pre>";

                echo ('Error DhlManifestPdfUrl: "' . $decoded_response->meta->error[0]->error_message);
                echo "<pre>";
                    echo "\n\n\nRespond Result = ";
                    print_r($decoded_response);
                echo "</pre>";
                dd($decoded_response);

            } else {
                $dhlManifestPdfUrl = $decoded_response->data->closeouts[0]->manifests[0]->url;
                $manifestId = $decoded_response->data->closeouts[0]->manifests[0]->manifestId;

                $this->dhlManifestArray =[];
                $this->dhlManifestArray['manifestDate'] = $dhlManifestDate;
                $this->dhlManifestArray['store_id'] = "52053152";
                $this->dhlManifestArray['mail_class'] = "_PARCEL_INTERNATIONAL_DIRECT";
                $this->dhlManifestArray['manifestId'] = $manifestId;
                $this->dhlManifestArray['pdf_path'] = $dhlManifestPdfUrl;
                $this->dhlManifestArray['user'] = auth()->user()->id;
                $this->dhlManifestArray['created_at'] = date("Y-m-d H:i:s");
                $this->dhlManifestArray['updated_at'] = date("Y-m-d H:i:s");
                $this->saveDhlManifest($this->dhlManifestArray);
                $this->updateDHLMainfestStatus($dhlManifestDate);
                return "success";
            #   return redirect()->action('DhlManifestController@index')->withSuccess('DhlManifest saved successfully.');
            #   $dhlManifestPdfUrl = $decoded_response->data->closeouts[0]->manifests[0]->url;
            #   dd($decoded_response, $dhlManifestPdfUrl);
            }

            #   Close request to clear up some resources
        } catch (Exception $e) {
            Log::error("DHL Error: ". $e->getMessage());
            return $e->getMessage();
        }

    }

    public function saveDhlManifest ($dhlManifestArray)
    {
        $dhlManifest = new DhlManifest();
        $dhlManifest->manifestDate  = trim($dhlManifestArray['manifestDate']);
        $dhlManifest->store_id      = trim($dhlManifestArray['store_id']);
        $dhlManifest->mail_class    = trim($dhlManifestArray['mail_class']);
        $dhlManifest->manifestId    = trim($dhlManifestArray['manifestId']);
        $dhlManifest->pdf_path      = trim($dhlManifestArray['pdf_path']);
        $dhlManifest->user          = trim($dhlManifestArray['user']);
        $dhlManifest->created_at    = trim($dhlManifestArray['created_at']);
        $dhlManifest->updated_at    = trim($dhlManifestArray['updated_at']);
        $dhlManifest->save();
    }

    private function updateDHLMainfestStatus($dhlManifestDate){

        Ship::whereNotNull('tracking_number')
            ->where('postmark_date','like', $dhlManifestDate)
            ->where('tracking_type',"DHL")
            ->where('manifestStatus',0)
            ->update([
                'manifestStatus' => '1',
            ]);

    }

}