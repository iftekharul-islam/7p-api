<?php namespace Ship;

use EasyPost\EasyPost;
use EasyPost\Address;
use EasyPost\Shipment;
use EasyPost\Error;
use EasyPost\CustomsInfo;
use EasyPost\CustomsItem;
use Ship\Shipper;
use Illuminate\Support\Facades\Log;

class Post extends CarrierInterface
{ 
	protected $api_key = 'EZAKcf12d106bf264af5b027250ce8bcc958jg55lbapz7Z4MNQuc9Z9DA';
	
	protected $domestic_methods = array (
		'FIRST_CLASS' => 'First',
		'PRIORITY'    => 'Priority',
		'EXPRESS'     => 'Express'
	);
	
	protected $intl_methods = array (
		'FIRST_CLASS' => 'FirstClassPackageInternationalService',
		'PRIORITY'    => 'PriorityMailInternational',
		'EXPRESS'     => 'ExpressMailInternational'
	);
	
	public function verifyAddress ($address_params) {
		
		EasyPost::setApiKey($this->api_key);

		try {
			
			$address = Address::create($address_params);
			
			$verified_on_create = Address::create_and_verify($address_params);

		} catch (Exception $e) {
				echo "Status: " . $e->getHttpStatus() . ":\n";
				echo $e->getMessage();
				if (!empty($e->param)) {
						echo "\nInvalid param: {$e->param}";
				}

		}
		
	}
	
	public function getLabel($store, $order, $unique_order_id, $method, $weight = 0, $params=[]) { 
		
		if ($weight == 0) {
			return 'ERROR: Weight Required';
		} else {
			$weight = $weight * 16;
		}
		
		EasyPost::setApiKey($this->api_key);
		
		$customer = $order->customer;
		
		$country = Shipper::getcountrycode($customer->ship_country);
		
		if ($weight > 13 && $method == 'FIRST_CLASS') {
			$method = 'PRIORITY';
		}
		
		if ($country == 'US') {
			
			$method = $this->domestic_methods[$method];
			
		} else {
			
			$method = $this->intl_methods[$method];

		}
		
		if ($country == 'US' && !in_array(trim($customer->ship_state),['AP','AE','AA','GU'])) {
			
			$customs_info = null;
			
		} else {
			
			$customs_info = CustomsInfo::create(array(
				"eel_pfc" => 'NOEEI 30.37(a)',
				"customs_certify" => true,
				"customs_signer" => 'Shlomi Matalon',
				"contents_type" => 'merchandise',
				"contents_explanation" => '',
				"restriction_type" => 'none',
				"customs_items" => CustomsItem::create(array(
						"description" => 'Personalized Gift',
						"quantity" => 1,
						"weight" => $weight, 
						"value" => 1.61,
						"hs_tariff_number" => '631090',
						"origin_country" => 'US'
					))
			));
			
		}
		
		//if first and over 13 oz -> priority?
		
		try {
			$shipmentData = array_merge(array(
                'return_address' => array(
                    "company" => substr($store->ship_name,0,35),
                    "street1" => $store->address_1,
                    "street2" => $store->address_2,
                    "city"    => $store->city,
                    "state"   => $store->state,
                    "zip"     => $store->zip,
                    "country" => 'US',
                    "phone"   => $store->phone
                ),
                'to_address' => array(
                    "name"    => $customer->ship_full_name,
                    "street1" => $customer->ship_address_1,
                    "street2" => $customer->ship_address_2,
                    "city"    => $customer->ship_city,
                    "state"   => $customer->ship_state,
                    "zip"     => $customer->ship_zip,
                    "country" => $country,
                    "phone"   => $customer->ship_phone
                ),
                'from_address' => array(
                    "company" => substr($store->ship_name,0,35),
                    "street1" => $store->address_1,
                    "street2" => $store->address_2,
                    "city"    => $store->city,
                    "state"   => $store->state,
                    "zip"     => $store->zip,
                    "phone"   => $store->phone
                ),
                'parcel' => array(
               //     'length' => 5,
             //       'width' => 5,
             //       'height' => 1,
                    'weight' => $weight
                ),
                'options' => array(
                    'label_format'  => 'ZPL',
                    'label_size'    => '4x6',
                    'invoice_number'=> $unique_order_id
                ),
                'customs_info' => $customs_info
            ), $params);
            
		$shipment = Shipment::create($shipmentData);
		    
            	
			$shipment->buy($shipment->lowest_rate(array('USPS'), array($method)));
			
			$label = $this->download_zpl($shipment->postage_label->label_zpl_url);
			
			if (substr($label, 0, 7) == '<Error>') {
				$doc = simplexml_load_string($label);
				return 'EasyPost Label Error: ' . $doc->Code . ' - ' . $doc->Message;
			} else {
				$trackingInfo['image'] = $label;
				$trackingInfo['unique_order_id'] = $unique_order_id;
				$trackingInfo['tracking_number'] = $shipment->tracker->tracking_code;
				$trackingInfo['mail_class'] =  $shipment->selected_rate->service;
				$trackingInfo['shipping_id'] =  $shipment->tracker->tracking_code;
				return $trackingInfo;
			}
			
		} catch (Error $e) {
			return 'ERROR: EasyPost ' . $e->getMessage();
		}
		
		return 'Uncaught EasyPost Error';
	}
	
	public function testLabel() { 
		
		EasyPost::setApiKey($this->api_key);
		
		$country = 'CA';
		
		if ($country == 'US') {
			
			$method = $this->domestic_methods['EXPRESS'];
			$customs_info = null;
			
		} else {
			
			$method = $this->intl_methods['EXPRESS'];
			
			$customs_info = CustomsInfo::create(array(
				"eel_pfc" => 'NOEEI 30.37(a)',
				"customs_certify" => true,
				"customs_signer" => 'Steve Brule',
				"contents_type" => 'merchandise',
				"contents_explanation" => '',
				"restriction_type" => 'none',
				"non_delivery_option" => 'return',
				"customs_items" => CustomsItem::create(array(
						"description" => 'T-shirt',
						"quantity" => 1,
						"weight" => 5,
						"value" => 10,
						"hs_tariff_number" => '123456',
						"origin_country" => 'US'
					))
				));
		}
		
		try {
			
			$shipment = Shipment::create(array(
				'to_address' => array(
					"name"    => 'Test Customer',
					"street1" => '123 OAK DR',
					"street2" => '',
					"city"    => 'Calgary',
					"state"   => 'AB',
					"zip"     => 'T3M2L8',
					"country" => 'CA',
					"phone"   => '1234567890'
				),
				'from_address' => array(
					"company" => 'Monogramonline.com',
					"street1" => '575 Underhill Blvd',
					"street2" => 'STE 325',
					"city"    => 'SYOSSET',
					"state"   => 'NY',
					"zip"     => '11791',
					"country" => 'US',
					"phone"   => '1234567890'
				),
				'parcel' => array(
					'length' => 9,
					'width' => 6,
					'height' => 5,
					'weight' => 1
				),
				'options' => array(
					'label_format'  => 'ZPL',
					'label_size'    => '4x6',
					'invoice_number'=> '123'
				),
				'customs_info' => $customs_info
			));
			
			$shipment->buy($shipment->lowest_rate(array('USPS'), array($method)));
			echo $shipment;
			
		} catch (Error $e) {
			echo $e->getMessage();
		}
		
	}
	
	public function download_zpl($url) {
		
		$attempts = 0;
			
		do {
      
      try {
				
				$ch = curl_init();
				curl_setopt($ch,CURLOPT_URL,$url);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); 
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);    
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);     
				$zpl=curl_exec($ch);
				curl_close($ch);
				
      } catch (\Exception $e) {
        $attempts++;
        sleep(90);
        Log::info('Post: Error downloading zpl ' . $e->getMessage() . ' - attempt # ' . $attempts);
        continue;
      }
      
      break;
      
    } while($attempts < 10);
    
    if ($attempts == 10) {
			Log::info('Post: Could not download zpl ' . $url);
      return false;
    }
		
		return $zpl;
	}
	
	public function void($store, $tracking_number) 
	{
		EasyPost::setApiKey($this->api_key);

		try {

		    // create refund
		    $refund = \EasyPost\Refund::create(array(
		        "carrier" => "USPS",
		        "tracking_codes" => $tracking_number
		    ));
		    
				return $tracking_number . ' Refunded';

		} catch (\Exception $e) {
		    return 'ERROR: EasyPost ' . $e->getMessage();
		}
	}
}
