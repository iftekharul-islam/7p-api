<?php namespace Ship;

use Ups\Shipping;
use Ship\Shipper;
use Monogram\Helper;
use Illuminate\Support\Facades\Log;

class UPS extends CarrierInterface
{
  public function getLabel($store, $order, $unique_order_id, $method = null, $packages = [0]) {
		
		$return = false;
    
    $customer = $order->customer;
     
		if(!isset($customer['ship_zip'])){
			return false;
		}
	   
		$shipment = new \Ups\Entity\Shipment();
	
		// Set shipper
		$shipper = $shipment->getShipper();
    
    $wgtmultiplier = 1;
    
    if ($store->ups_type == 'P') {
      $shipper->setShipperNumber($store->ups_account);
    } else if ($store->company == 1) {
      $shipper->setShipperNumber('20627V');
    } else {
		  $shipper->setShipperNumber(env('SHIPPER_NUMBER'));
    }
    
    $shipper->setName(substr($store->ship_name,0,35));
    $shipper->setAttentionName('Customer Service Dept');
    $shipperAddress = $shipper->getAddress();
    
    // if ($store->ups_type != 'S') {
      // $shipperAddress->setAddressLine1('575 Underhill Blvd');
      // $shipperAddress->setAddressLine2('suite 325');
      // $shipperAddress->setPostalCode('11791');
      // $shipperAddress->setCity('syosset');
      // $shipperAddress->setCountryCode('US');
      // $shipperAddress->setStateProvinceCode('NY');
      // $shipper->setPhoneNumber('8563203210');
    // } else {
  		$shipperAddress->setAddressLine1($store->address_1);
  		$shipperAddress->setAddressLine2($store->address_2);
  		$shipperAddress->setPostalCode($store->zip);
  		$shipperAddress->setCity($store->city);
  		$shipperAddress->setCountryCode('US');
  		$shipperAddress->setStateProvinceCode($store->state);
      $shipper->setPhoneNumber($store->phone);
		// } 
    
    if (strpos($store->email, '@')) {
      $shipper->setEmailAddress($store->email);
    } else {
      $shipper->setEmailAddress('cs@Monogramonline.com');
    }

    $shipper->setAddress($shipperAddress);
    
		$shipment->setShipper($shipper);
	
		// To address
		$address = new \Ups\Entity\Address();
		$address->setAddressLine1(trim($customer['ship_address_1']));
		if(isset($customer['ship_address_2'])){
			$address->setAddressLine2(trim($customer['ship_address_2']));
		}else{
			$address->setAddressLine2('');
		}
		$address->setAddressLine3('');
		$address->setPostalCode($customer['ship_zip']);
		$address->setCity(trim($customer['ship_city']));
		$country_code = Shipper::getcountrycode($customer['ship_country']);
		$address->setCountryCode($country_code);
		$address->setStateProvinceCode($customer['ship_state']);
		$shipTo = new \Ups\Entity\ShipTo();
		$shipTo->setAddress($address);
		if ($customer['ship_company_name'] != '') {
			$shipTo->setCompanyName(substr($customer['ship_company_name'], 0, 35));
		} else {
			$shipTo->setCompanyName('-');
		}
		$shipTo->setAttentionName(substr(Helper::removeSpecial($customer['ship_full_name']), 0, 35));
		$shipTo->setEmailAddress($customer['bill_email']);
    
    if (strlen(str_replace(['(','-',')',' '], '', $customer['ship_phone'])) != 10) {
      $shipTo->setPhoneNumber('8563203200');
    } else {
  		$shipTo->setPhoneNumber($customer['ship_phone']);
    }
	  
    $shipment->setShipTo($shipTo);
     
		// Set service
		$service = new \Ups\Entity\Service;
		$wgtunit = new \Ups\Entity\UnitOfMeasurement;

		if ($country_code == 'US') {
			
			if ($method != null) {
        
        if ($method == 'S_SUREPOST') {
          if ($packages[0] == null || max($packages) == 0) {
            return 'ERROR: Weight Required for UPS Surepost';
          }
          
          if (max($packages) < 1) {
            $service->setCode(constant("\Ups\Entity\Service::S_SUREPOST_0"));
            $wgtunit->setCode(\Ups\Entity\UnitOfMeasurement::UOM_OZS);
            $wgtmultiplier = 16;		
          } else {
            $service->setCode(constant("\Ups\Entity\Service::S_SUREPOST_1"));
            $wgtunit->setCode(\Ups\Entity\UnitOfMeasurement::UOM_LBS);
          }
        } else {
          $service->setCode(constant("\Ups\Entity\Service::" . $method));
          $wgtunit->setCode(\Ups\Entity\UnitOfMeasurement::UOM_LBS);
        }
				
				$mailclass = 'UP' . str_replace("_", " ", $method);
				
				$packageType = \Ups\Entity\PackagingType::PT_PACKAGE;	
				
			} else {
        
				$service->setCode(\Ups\Entity\Service::S_EXPEDITED_MAIL_INNOVATIONS); 
				$mailclass = 'UPS Expedited Mail Innovations';
				
				$shipment->setShipmentUSPSEndorsement('5');
        
        if (max($packages) == 0 || $packages[0] == null || max($packages) < 1) {
          $wgtunit->setCode(\Ups\Entity\UnitOfMeasurement::UOM_OZS);	
          $wgtmultiplier = 16;
          $packageType = \Ups\Entity\PackagingType::PT_IRREGULARS;
        } else {
          $wgtunit->setCode(\Ups\Entity\UnitOfMeasurement::UOM_LBS);
          $packageType = \Ups\Entity\PackagingType::PT_PARCEL_POST;	
        }
        
			}
      
      $referenceNumber1 = new \Ups\Entity\ReferenceNumber;
      $referenceNumber1->setCode(\Ups\Entity\ReferenceNumber::CODE_PURCHASE_ORDER_NUMBER); 
      $referenceNumber1->setValue($order->short_order);
      
      if ($order->purchase_order != null) {
        $referenceNumber2 = new \Ups\Entity\ReferenceNumber;
        $referenceNumber2->setCode(\Ups\Entity\ReferenceNumber::CODE_PURCHASE_REQUEST_NUMBER); 
        $referenceNumber2->setValue($order->purchase_order);
      } 
      
		} else {
						
			$soldTo = new \Ups\Entity\SoldTo();
			$soldTo->setAddress($address);
			if ($customer['ship_company_name'] != '') {
				$soldTo->setCompanyName(substr($customer['ship_company_name'], 0, 35));
			} else {
				$soldTo->setCompanyName('-');
			}
			$soldTo->setAttentionName(substr(Helper::removeSpecial($customer['ship_full_name']), 0, 35));
			$soldTo->setEmailAddress($customer['ship_email']);
			$soldTo->setPhoneNumber($customer['ship_phone']);
			$shipment->setSoldTo($soldTo);

			if ($method != null) {
				$service->setCode(constant("\Ups\Entity\Service::" . $method));
				$mailclass = 'UP' . str_replace("_", " ", $method);
				
			} else {
				$service->setCode(\Ups\Entity\Service::S_ECONOMY_MAIL_INNOVATIONS); 
				$mailclass = 'UPS Economy Mail Innovations'; 
				$shipment->setMILabelCN22Indicator(1);
				$shipment->setShipmentUSPSEndorsement('5');
			}
			
			$international_forms = new \Ups\Entity\InternationalForms();
			$international_forms->setType('09');
			$international_forms->setInvoiceDate(new \DateTime());
			$international_forms->setReasonForExport(\Ups\Entity\InternationalForms::RFE_GIFT);
			$international_forms->setInvoiceNumber($unique_order_id);
			
			$cn22 = new \Ups\Entity\CN22Form();
			$cn22->setLabelSize('6');
			$cn22->setPrintsPerPage('1');
			$cn22->setLabelPrintType('zpl');
			$cn22->setCN22Type('1');
			$content = new \Ups\Entity\CN22Content();
			$content->setContentQuantity('1');
			$content->setCN22ContentDescription('Personalized Gift');
			$content->setCN22ContentTotalValue('1.61');
			$content->setCN22ContentCurrencyCode('USD');
			$content->setCN22ContentCountryOfOrigin('US');
			$cn22->setCN22Content($content);
			
			$international_forms->setCN22Form($cn22);
			
			$product = new \Ups\Entity\Product();
			$product->setDescription1('Personalized Gift');
			$product->setCommodityCode('6310.90.20');
			$product->setPartNumber('111');
			$product->setOriginCountryCode('US');
			
			$product_unit = new \Ups\Entity\Unit();
			$product_unit->setNumber('1');
			$product_unit->setValue('1.61');
			$product_UOM = new \Ups\Entity\UnitOfMeasurement;
			$product_UOM->setCode(\Ups\Entity\UnitOfMeasurement::PROD_PIECE);
			$product_unit->setUnitOfMeasurement($product_UOM);
			$product->setUnit($product_unit);
			$international_forms->addProduct($product);
			
			$service_options = new \Ups\Entity\ShipmentServiceOptions();
			$service_options->setInternationalForms($international_forms);
			$shipment->setShipmentServiceOptions($service_options);
			
			$packageType = \Ups\Entity\PackagingType::PT_PARCELS;
			$wgtunit->setCode(\Ups\Entity\UnitOfMeasurement::UOM_LBS);
		} // end international
		
		$service->setDescription($service->getName());
		$shipment->setService($service);
		
		$invoice = new \Ups\Entity\InvoiceLineTotal();
		$invoice->setCurrencyCode('USD');
		$invoice->setMonetaryValue('1.61');
		$shipment->setInvoiceLineTotal($invoice);
		
		// Mark as a return (if return)
		if ($return) {
			$returnService = new \Ups\Entity\ReturnService;
			$returnService->setCode(\Ups\Entity\ReturnService::PRINT_RETURN_LABEL_PRL);
			$shipment->setReturnService($returnService);
		}

		// Set description
		$shipment->setDescription($unique_order_id.' Gift Item');
		$ID = str_replace('-', '', $unique_order_id);	
		$shipment->setPackageID($ID);
		
		// Set dimensions
		$dimensions = new \Ups\Entity\Dimensions();
		$dimensions->setHeight(1);
		$dimensions->setWidth(5);
		$dimensions->setLength(5);
		$dimunit = new \Ups\Entity\UnitOfMeasurement;
		$dimunit->setCode(\Ups\Entity\UnitOfMeasurement::UOM_IN);
		$dimensions->setUnitOfMeasurement($dimunit);
		
    foreach($packages as $index => $weight) {
      if ($weight == 0 && $index == 0) {
        $weight = .3;
      } else if ($weight == 0) {
        return 'ERROR: Multiple zero weight packages';
      }
      $package = new \Ups\Entity\Package();
      $package->getPackagingType()->setCode($packageType);	
      $package->getPackageWeight()->setWeight($weight * $wgtmultiplier);
      $package->getPackageWeight()->setUnitOfMeasurement($wgtunit);
      $package->setDimensions($dimensions);
  		$package->setDescription('Box/Envelope');
      if (isset($referenceNumber2)) {
        $package->setReferenceNumber1($referenceNumber1);
      }
      if (isset($referenceNumber2)) {
        $package->setReferenceNumber2($referenceNumber2);
      }
  		$shipment->addPackage($package);
    }
    
		// Set payment information
		if (($method == NULL && $store->ups_type != 'P') || $store->ups_account == null || $store->ups_account == '') {
			$shipment->setPaymentInformation(new \Ups\Entity\PaymentInformation('prepaid', (object)array('AccountNumber' => env('SHIPPER_NUMBER'))));
			$shipment->setCostCenter(substr(preg_replace("/[^0-9a-z]+/i", '', $store->store_name), 0, 30));
    } else {			
      if ($store->ups_type == 'T' || $store->ups_type == 'S') {
        $storeAddress = new \Ups\Entity\Address;
  			$storeAddress->setPostalCode($store->zip);
  			$storeAddress->setCountryCode('US');
        $billthirdParty = new \Ups\Entity\BillThirdParty;
  			$billthirdParty->setThirdPartyAddress($storeAddress);
  			$billthirdParty->setAccountNumber($store->ups_account);
  			$payment = new \Ups\Entity\PaymentInformation('billThirdParty');
  			$payment->setBillThirdParty($billthirdParty);
  			$shipment->setPaymentInformation($payment);
      } else if ($store->ups_type == 'P') {
        $shipment->setPaymentInformation(new \Ups\Entity\PaymentInformation('prepaid', (object)array('AccountNumber' => $store->ups_account)));
      }
		}
		
    $rateInformation = new \Ups\Entity\RateInformation;
    $rateInformation->setNegotiatedRatesIndicator(1);
    $shipment->setRateInformation($rateInformation);
    
		$labelspec = new \Ups\Entity\ShipmentRequestLabelSpecification('ZPL');
		$labelspec->setStockSizeHeight(4);
		$labelspec->setStockSizeWidth(6);
		
		// if ($method != null) {
			// 	dd($shipment);
		// }
		
		try {
			// Create logger
			// $log = new \Monolog\Logger('ups');
			// $log->pushHandler(new \Monolog\Handler\StreamHandler(storage_path() . '/logs/ups.log', \Monolog\Logger::DEBUG));
			 
			// $api = new \Ups\Shipping($accessKey, $userId, $password);
			// $api = new \Ups\Shipping(env('UPS_ACCESS_KEY'), env('UPS_USER_ID'), env('UPS_PASSWORD'), false, null, $log);
			$api = new \Ups\Shipping(env('UPS_ACCESS_KEY'), env('UPS_USER_ID'), env('UPS_PASSWORD'), false, null);
			if ($store->validate_addresses == '1' && strpos(strtolower(str_replace([' ', '.'], '', $customer['ship_address_1'])), 'pobox') === FALSE) {
				$confirm = $api->confirm(\Ups\Shipping::REQ_VALIDATE, $shipment, $labelspec);
				// 			var_dump($confirm); // Confirm holds the digest you need to accept the result
			} else {
				$confirm = $api->confirm(\Ups\Shipping::REQ_NONVALIDATE, $shipment, $labelspec);
			}
		} catch (\Exception $e) {
				Log::error($e->getMessage());
        // Log::error($shipment);
				return 'ERROR: ' . $e->getMessage();
		}
		
		if ($confirm) {
			
			try {
				$accept = $api->accept($confirm->ShipmentDigest); //dd($accept);
			} catch (\Exception $e) {
				Log::error($e->getMessage());
				return 'ERROR: ' . $e->getMessage();
			}
			
			$trackingInfo['image'] = $this->decodeUpsLabel('label', Helper::generate_valid_xml_from_array($accept));
			$trackingInfo['unique_order_id'] = $unique_order_id;
      if (isset($accept->PackageResults->TrackingNumber)) {
			     $trackingInfo['tracking_number'] = $accept->PackageResults->TrackingNumber;
      } else {
           $trackingInfo['tracking_number'] = $accept->PackageResults[0]->TrackingNumber;
      }
			$trackingInfo['mail_class'] =  $mailclass;
			
			if ($country_code == 'US' && $mailclass == 'UPS Expedited Mail Innovations') {
				$trackingInfo['shipping_id'] =  $accept->PackageResults->USPSPICNumber;
			} else if ($country_code != 'US') {
				$trackingInfo['shipping_id'] = $accept->ShipmentIdentificationNumber;
				$trackingInfo['image'] .= $this->decodeUpsLabel('form', Helper::generate_valid_xml_from_array($accept));
			} else if (isset($accept->PackageResults->TrackingNumber)) {
        $trackingInfo['shipping_id'] =  $accept->PackageResults->TrackingNumber;
      } else if (isset($accept->PackageResults[0]->TrackingNumber)) {
				$trackingInfo['shipping_id'] =  $accept->PackageResults[0]->TrackingNumber;
			}
			
			return $trackingInfo;
		}
	}
  
  private function decodeUpsLabel ($type, $full_xml_source)
  {
    
    $xml = simplexml_load_string($full_xml_source);
    $json = json_encode($xml);
    $array = json_decode($json, true);
    if ($type == 'label' && isset($array['PackageResults']['LabelImage'])) {
      if ( $array['PackageResults']['LabelImage']['GraphicImage'] ) {
        $graphicImage = base64_decode($array['PackageResults']['LabelImage']['GraphicImage']);
        return $graphicImage;
      } else {
        return False;
      }
    } elseif ($type == 'label' && isset($array['PackageResults']['node'])) {
      $graphicImage = '';
      foreach ($array['PackageResults']['node'] as $package) {
        if ( $package['LabelImage']['GraphicImage'] ) {
          $graphicImage .= base64_decode($package['LabelImage']['GraphicImage']);
        } 
      }
      return $graphicImage;
    } elseif ($type == 'form') {
      if ( $array['Form']['Image']['GraphicImage'] ) {
        $graphicImage = base64_decode($array['Form']['Image']['GraphicImage']);
        return $graphicImage;
      } else {
        return False;
      }
    } else {
      return False;
    }
  }
  
  public function void($store, $tracking_number) {
    try {
			$api = new \Ups\Shipping(env('UPS_ACCESS_KEY'), env('UPS_USER_ID'), env('UPS_PASSWORD'), false, null);
			return $api->void($tracking_number);
		} catch (\Exception $e) {
				Log::error($e->getMessage());
				return 'ERROR: ' . $e->getMessage();
		}
  }
}