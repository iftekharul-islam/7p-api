<?php namespace Ship;

use FedEx\ShipService;
use FedEx\ShipService\ComplexType;
use FedEx\ShipService\SimpleType;
use Ship\Shipper;
use Illuminate\Support\Facades\Log;

class FEDEX extends CarrierInterface
{
  public function getLabel($store, $order, $unique_order_id, $method, $packages = [0]) { 
    
    if ($method == '_GROUND_HOME_DELIVERY') { // || $store->fedex_residential == '1') {
      $residential = 1;
    } else {
      $residential = 0;
    }
    
    $customer = $order->customer;
    
    if ($store->fedex_type == 'T') {
      $userCredential = new ComplexType\WebAuthenticationCredential();
      $userCredential
          ->setKey(env('FEDEX_KEY'))
          ->setPassword(env('FEDEX_PASSWORD'));
      $webAuthenticationDetail = new ComplexType\WebAuthenticationDetail();
      $webAuthenticationDetail->setUserCredential($userCredential);
      $clientDetail = new ComplexType\ClientDetail();
      $clientDetail
          //->setAccountNumber($store->fedex_account)
          ->setAccountNumber(env('FEDEX_ACCOUNT'))
          ->setMeterNumber(env('FEDEX_METER'));
      $account = env('FEDEX_ACCOUNT');
    } else {
      $userCredential = new ComplexType\WebAuthenticationCredential();
      $userCredential
          ->setKey($store->fedex_key)
          ->setPassword($store->fedex_password);
      $webAuthenticationDetail = new ComplexType\WebAuthenticationDetail();
      $webAuthenticationDetail->setUserCredential($userCredential);
      $clientDetail = new ComplexType\ClientDetail();
      $clientDetail
          ->setAccountNumber($store->fedex_account)
          ->setMeterNumber($store->fedex_meter);
      $account = $store->fedex_account;
    }
    
    $version = new ComplexType\VersionId();
    $version
        ->setMajor(12)
        ->setIntermediate(1)
        ->setMinor(0)
        ->setServiceId('ship');
        
    $shipperAddress = new ComplexType\Address();
    $shipperAddress
        ->setStreetLines(['575 Underhill Boulevard', 'Suite #325'])
        ->setCity('Syosset')
        ->setStateOrProvinceCode('NY')
        ->setPostalCode('11791')
        ->setCountryCode('US');
    $shipperContact = new ComplexType\Contact();
    $shipperContact
        ->setCompanyName(substr($store->ship_name,0,35))
        ->setEMailAddress('cs@monogramonline.com')
        //->setPersonName($store->ship_name)
        ->setPhoneNumber('8563203210');
    
    $storeAddress = new ComplexType\Address();
    $storeAddress
        ->setStreetLines($store->address_1)
        ->setCity($store->city)
        ->setStateOrProvinceCode($store->state)
        ->setPostalCode($store->zip)
        ->setCountryCode('US');
    $storeContact = new ComplexType\Contact();
    $storeContact
        ->setCompanyName(substr($store->ship_name,0,35))
        ->setEMailAddress($store->email)
        //->setPersonName($store->ship_name)
        ->setPhoneNumber($store->phone);
        
    $shipper = new ComplexType\Party();
    $shipper
        ->setAccountNumber($account)
        ->setAddress($shipperAddress)
        ->setContact($shipperContact);
    $shippingChargesPayor = new ComplexType\Payor();
    
    if ($store->fedex_account == null || $store->fedex_account == '' || $store->fedex_type == 'P') {
      $shippingChargesPayor->setResponsibleParty($shipper);
      $shippingChargesPayment = new ComplexType\Payment();
      $shippingChargesPayment
          ->setPaymentType(SimpleType\PaymentType::_SENDER)
          ->setPayor($shippingChargesPayor);
    } else {
      $store_shipper = new ComplexType\Party();
      $store_shipper
          ->setAccountNumber($store->fedex_account)
          ->setAddress($storeAddress)
          ->setContact($storeContact);
      $shippingChargesPayor->setResponsibleParty($store_shipper);
      $shippingChargesPayment = new ComplexType\Payment();
      $shippingChargesPayment
          ->setPaymentType(SimpleType\PaymentType::_THIRD_PARTY)
          ->setPayor($shippingChargesPayor);
    }
    
    $recipientAddress = new ComplexType\Address();
    $recipientAddress
        ->setStreetLines([str_replace('&', '+', $customer['ship_address_1']) , str_replace('&', '+', $customer['ship_address_2'])])
        ->setCity($customer['ship_city'])
        ->setStateOrProvinceCode($customer['ship_state'])
        ->setPostalCode($customer['ship_zip'])
        ->setCountryCode(Shipper::getcountrycode($customer['ship_country']))
        ->setResidential($residential);
    $recipientContact = new ComplexType\Contact();
    $recipientContact
        ->setPersonName(str_replace('&', '+', $customer['ship_full_name']))
        ->setPhoneNumber($store->phone);
    $recipient = new ComplexType\Party();
    $recipient
        ->setAddress($recipientAddress)
        ->setContact($recipientContact);     
    $labelSpecification = new ComplexType\LabelSpecification();
    $labelSpecification
        ->setLabelStockType(new SimpleType\LabelStockType(SimpleType\LabelStockType::_STOCK_4X6))
        ->setImageType(new SimpleType\ShippingDocumentImageType(SimpleType\ShippingDocumentImageType::_ZPLII))
        ->setLabelFormatType(new SimpleType\LabelFormatType(SimpleType\LabelFormatType::_COMMON2D));
    
    if ($store->address_1 != null) {
        $returnAddress = new ComplexType\ContactAndAddress();
        $returnAddress->setContact($storeContact);
        $returnAddress->setAddress($storeAddress);
        $labelSpecification->setPrintedLabelOrigin($returnAddress);
    }
    
    $packageLineItem1 = new ComplexType\RequestedPackageLineItem();
    
    if (array_sum($packages) == 0) {
      $weight = .99;
    } else {
      $weight = array_sum($packages);
    }
    
    $packageLineItem1
        ->setSequenceNumber(1)
        ->setItemDescription('Product description')
        ->setDimensions(new ComplexType\Dimensions(array(
            'Width' => 4,
            'Height' => 5,
            'Length' => 6,
            'Units' => SimpleType\LinearUnits::_IN
        )))
        ->setGroupPackageCount(count($packages))
        ->setWeight(new ComplexType\Weight(array(
            'Value' => $weight,
            'Units' => SimpleType\WeightUnits::_LB
        )));
    
    $referenceNumber1 = new ComplexType\CustomerReference();
    $referenceNumber1->setCustomerReferenceType(SimpleType\CustomerReferenceType::_CUSTOMER_REFERENCE);
    $referenceNumber1->setValue($order->short_order);
    
    if ($order->purchase_order != null) {
      $referenceNumber2 = new ComplexType\CustomerReference(); 
      $referenceNumber2->setCustomerReferenceType(SimpleType\CustomerReferenceType::_P_O_NUMBER);
      $referenceNumber2->setValue($order->purchase_order);
      $packageLineItem1->setCustomerReferences([0 => $referenceNumber1, 1 => $referenceNumber2]);
    } else {
      $packageLineItem1->setCustomerReferences([0 => $referenceNumber1]);
    }
    
    $requestedShipment = new ComplexType\RequestedShipment();
    $requestedShipment->setShipTimestamp(date('c'));
    $requestedShipment->setDropoffType(new SimpleType\DropoffType(SimpleType\DropoffType::_REGULAR_PICKUP));
    $requestedShipment->setServiceType(new SimpleType\ServiceType(constant('FedEx\ShipService\SimpleType\ServiceType::' . $method)));
    $requestedShipment->setPackagingType(new SimpleType\PackagingType(SimpleType\PackagingType::_YOUR_PACKAGING));
    $requestedShipment->setShipper($shipper);
    //$requestedShipment->setOrigin($shipper); USE TO CHANGE ADDRESS ON LABEL FOR WALMART
    $requestedShipment->setRecipient($recipient);
    $requestedShipment->setLabelSpecification($labelSpecification);
    $requestedShipment->setRateRequestTypes(array(new SimpleType\RateRequestType(SimpleType\RateRequestType::_ACCOUNT)));
    $requestedShipment->setPackageCount(1);
    
    $requestedShipment->setRequestedPackageLineItems( [ $packageLineItem1 ] );
    $requestedShipment->setShippingChargesPayment($shippingChargesPayment);
    
    if ($method == '_SMART_POST') {
      $smartpost = new ComplexType\SmartPostShipmentDetail();
      if ($weight < 1) {
        $smartpost->setIndicia(new SimpleType\SmartPostIndiciaType(SimpleType\SmartPostIndiciaType::_PRESORTED_STANDARD));
      } else {
        $smartpost->setIndicia(new SimpleType\SmartPostIndiciaType(SimpleType\SmartPostIndiciaType::_PARCEL_SELECT));
      }
      $smartpost->setAncillaryEndorsement(new SimpleType\SmartPostAncillaryEndorsementType(SimpleType\SmartPostAncillaryEndorsementType::_ADDRESS_CORRECTION));
 
      $smartpost->setHubId('5379');
      // $smartpost->setHubId('5087');
      // $smartpost->setHubId('5531');
      $requestedShipment->setSmartPostDetail($smartpost);
    }
    
    $processShipmentRequest = new ComplexType\ProcessShipmentRequest();
    $processShipmentRequest->setWebAuthenticationDetail($webAuthenticationDetail);
    $processShipmentRequest->setClientDetail($clientDetail);
    $processShipmentRequest->setVersion($version);
    $processShipmentRequest->setRequestedShipment($requestedShipment);
    $shipService = new ShipService\Request();
    $shipService->getSoapClient()->__setLocation('https://ws.fedex.com:443/web-services/ship');
    //$shipService->getSoapClient()->__setLocation('https://ws.fedex.com:443/web-services');
    // $shipService->getSoapClient()->__setLocation('https://wsbeta.fedex.com:443/web-services');
    
    try {
        $confirm = $shipService->getProcessShipmentReply($processShipmentRequest);
        // Log::info($processShipmentRequest->toArray());
        // Log::info($confirm->toArray());
    } catch (\Exception $e) {
        Log::error($e->getMessage());
        return 'ERROR: ' . $e->getMessage();
    }
    // print_r($processShipmentRequest);
    // print_r($shipService);
    // print_r($confirm); 
    // dd('done');
    if ($confirm && isset($confirm->CompletedShipmentDetail) &&
          (!isset($confirm->HighestSeverity) || $confirm->HighestSeverity != 'ERROR')) {
      
      $trackingInfo['image'] = '';
      
      foreach($confirm->CompletedShipmentDetail->CompletedPackageDetails as $package) {
        $trackingInfo['image'] .= $package->Label->Parts[0]->Image;
      }
      $trackingInfo['unique_order_id'] = $unique_order_id;
      $trackingInfo['tracking_number'] = $confirm->CompletedShipmentDetail->CompletedPackageDetails[0]->TrackingIds[0]->TrackingNumber;
      $trackingInfo['mail_class'] =  'FEDEX ' . $confirm->CompletedShipmentDetail->CompletedPackageDetails[0]->TrackingIds[0]->TrackingIdType;
      $trackingInfo['shipping_id'] =  $confirm->CompletedShipmentDetail->CompletedPackageDetails[0]->TrackingIds[0]->TrackingNumber;
      return $trackingInfo;
      
    } else {
      $msg = null;
      
      if ($confirm->Notifications) {
        foreach ($confirm->Notifications as $notification) {
          $msg .= $notification->LocalizedMessage . ' ';
          $msg .= $notification->Message . ' ';
        }
      } else {
        $msg = 'Unknown Error';
      }
      
      Log::error('ERROR: Creating Fedex Label ' . $msg . ' ' . $unique_order_id);
      return 'ERROR: Creating Fedex Label ' . $msg;
    }
  }
  
  public function void($store, $tracking_number)
  {
    if ($store->fedex_type == 'T') {
      $userCredential = new ComplexType\WebAuthenticationCredential();
      $userCredential
          ->setKey(env('FEDEX_KEY'))
          ->setPassword(env('FEDEX_PASSWORD'));
      $webAuthenticationDetail = new ComplexType\WebAuthenticationDetail();
      $webAuthenticationDetail->setUserCredential($userCredential);
      $clientDetail = new ComplexType\ClientDetail();
      $clientDetail
          //->setAccountNumber($store->fedex_account)
          ->setAccountNumber(env('FEDEX_ACCOUNT'))
          ->setMeterNumber(env('FEDEX_METER'));
      $account = env('FEDEX_ACCOUNT');
    } else {
      $userCredential = new ComplexType\WebAuthenticationCredential();
      $userCredential
          ->setKey($store->fedex_key)
          ->setPassword($store->fedex_password);
      $webAuthenticationDetail = new ComplexType\WebAuthenticationDetail();
      $webAuthenticationDetail->setUserCredential($userCredential);
      $clientDetail = new ComplexType\ClientDetail();
      $clientDetail
          ->setAccountNumber($store->fedex_account)
          ->setMeterNumber($store->fedex_meter);
      $account = $store->fedex_account;
    }
    
    $version = new ComplexType\VersionId();
    $version
        ->setMajor(12)
        ->setIntermediate(1)
        ->setMinor(0)
        ->setServiceId('ship');
    
    $trackingId = new ComplexType\TrackingId();
    $trackingId
        ->setTrackingNumber($tracking_number)
        ->setTrackingIdType(SimpleType\TrackingIdType::_FEDEX);


    $deleteShipmentRequest = new ComplexType\DeleteShipmentRequest();
    $deleteShipmentRequest->setWebAuthenticationDetail($webAuthenticationDetail);
    $deleteShipmentRequest->setClientDetail($clientDetail);
    $deleteShipmentRequest->setVersion($version);
    $deleteShipmentRequest->setTrackingId($trackingId);
    $deleteShipmentRequest->setDeletionControl(SimpleType\DeletionControlType::_DELETE_ALL_PACKAGES);
    
    $validateShipmentRequest = new ShipService\Request();
    $validateShipmentRequest->getSoapClient()->__setLocation('https://ws.fedex.com:443/web-services/ship');
    
    try {
        $response = $validateShipmentRequest->getDeleteShipmentReply($deleteShipmentRequest);
    } catch (\Exception $e) {
        Log::error($e->getMessage());
        return 'ERROR: ' . $e->getMessage();
    }
  
    return $response;
  }
}