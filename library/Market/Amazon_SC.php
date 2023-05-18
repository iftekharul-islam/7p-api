<?php 
 /* To add an Amazon Seller Central Store: */
 /* Add EDI/Amazon directory (name = merchantID), Add the store to the marketplace and the credentials to config/amazon-mws.php */
namespace Market; 

use App\Batch;
use App\Order;
use App\Customer;
use App\Item;
use App\Product;
use Ship\Shipper;
use Monogram\Helper;
use Illuminate\Support\Facades\Log;
use ZipArchive;

use Sonnenglas\AmazonMws\AmazonOrderList;
use Sonnenglas\AmazonMws\AmazonFeed;
use Sonnenglas\AmazonMws\AmazonFeedList;
use Sonnenglas\AmazonMws\AmazonFeedResult;

class Amazon_SC extends StoreInterface 
{ 
     
    protected $dir = '/EDI/Amazon/'; 
    
    public function importCsv($store, $file) {
      //
    }
    
    public function getInput($store) {
      
      $list = $this->getAmazonOrders($store);
            
      if ($list) {
          
          foreach ($list as $order) { 
              
              //check if order in DB
              $db_order = Order::with('items.batch')
                            ->where('order_id', $order->getAmazonOrderId())
                            ->where('is_deleted', '0')
                            ->first();
              
              if (!$db_order && $order->getOrderStatus() == 'Unshipped') {
                $this->insertOrder($store, $order);
              } else if ($db_order && $order->getOrderStatus() == 'Canceled' && $db_order->order_status != '8') {
                $this->cancelOrder($db_order);                  
              } 
              
          }
      }
      
    }
    
    private function getAmazonOrders($store) {
      
        try {
            $amz = new AmazonOrderList($store); //store name matches the array key in the config file
            $amz->setLimits('Modified', "- 80 hours"); //accepts either specific timestamps or relative times 
            $amz->setFulfillmentChannelFilter("MFN"); //no Amazon-fulfilled orders
            $amz->setOrderStatusFilter(['Unshipped','PartiallyShipped','Canceled']); //no shipped or pending orders
            $amz->setUseToken(); //tells the object to automatically use tokens right away
            $amz->fetchOrders(); //this is what actually sends the request
            return $amz->getList();
        } catch (\Exception $ex) {
            echo 'There was a problem with the Amazon library. Error: '.$ex->getMessage();
        }
    }
    
    private function insertOrder($store, $input) { 
      
      // -------------- Customers table data insertion started ----------------------//
      $customer = new Customer();
      try {
      $customer->order_id = $input->getAmazonOrderId();
      $address = $input->getShippingAddress();
      $customer->ship_full_name = $address['Name'];
      $customer->ship_last_name = (strpos($address['Name'], ' ') === false) ? '' : preg_replace("/[^a-zA-Z0-9\']/", '', $address['Name']);
      $customer->ship_first_name = trim( preg_replace('#'.$customer->ship_last_name.'#', '', $address['Name'] ) );
      $customer->ship_address_1 = $address['AddressLine1'];
      $customer->ship_address_2 = isset($address['AddressLine2']) ? $address['AddressLine2'] : null;
      $customer->ship_city = $address['City'];
      $customer->ship_state = Helper::stateAbbreviation($address['StateOrRegion']);
      $customer->ship_zip = $address['PostalCode'];
      $customer->ship_country = $address['CountryCode'];
      $customer->ship_phone = $address['Phone'];
      $customer->ship_email = $input->getBuyerEmail();
      $customer->bill_email = $input->getBuyerEmail();
      $customer->save();
      // -------------- Customers table data insertion ended ----------------------//
      // -------------- Orders table data insertion started ----------------------//
      $order = new Order();
      $order->order_id = $input->getAmazonOrderId();

        $order->customer_id = $customer->id;
      } catch ( \Exception $exception ) {
        Log::error('Failed to insert customer id in Amazon '.$exception->getMessage());
      }
      $order->short_order = $input->getAmazonOrderId();
      $order->item_count = $input->getNumberofItemsShipped() + $input->getNumberOfItemsUnshipped();;
      $order->shipping_charge = '0';
      $order->tax_charge = '0';
      $order->total = $input->getOrderTotalAmount();
      $order->order_date = str_replace(['T','Z'], ' ', $input->getPurchaseDate());
      $order->store_id = $store;
      $order->store_name = 'Amazon';
      $order->order_status = 4;
      $order->ship_state = $address['StateOrRegion'];
      $order->ship_date = $input->getLatestShipDate();
      
      if ($input->getShipServiceLevel() == 'Second US D2D Dom') {
        $order->carrier = 'US';
        $order->method = 'PRIORITY';
      }
      
      $order->save();
      
      try {
        $order_5p = $order->id;
      } catch ( \Exception $exception ) {
        $order_5p = '0';
        Log::error('Failed to get 5p order id in Amazon');
      }
      // -------------- Orders table data insertion ended ----------------------//
      // -------------- Items table data insertion started ------------------------//
      
      $fetch = $input->fetchItems(); 
      $items = $fetch->getItems();
      $discount_total = 0;
      $shipping_total = 0;
      $tax_total = 0;
        
      foreach ( $items as $AMZ_item ) { 
        
        // Log::info($AMZ_item);
        
        $options = '{}';
          
        if (isset($AMZ_item['ShippingDiscount'])) {
          $discount_total += $AMZ_item['ShippingDiscount']['Amount'];
        }
        if (isset($AMZ_item['PromotionDiscount'])) {
          $discount_total += $AMZ_item['PromotionDiscount']['Amount'];
        }
        if (isset($AMZ_item['ShippingPrice']['Amount'])) {
          $shipping_total += $AMZ_item['ShippingPrice']['Amount'];
        }
        
        if (isset($AMZ_item['ItemTax']['Amount'])) {
          $tax_total += $AMZ_item['ItemTax']['Amount'];
        }
        
        if (isset($AMZ_item['BuyerCustomizedInfo'])) {
          $options = $this->getPersonalization($store, $AMZ_item['BuyerCustomizedInfo']['CustomizedURL'], $AMZ_item['OrderItemId']);
        } 
        // -------------- Products table data insertion started ---------------------- //
        $product = Helper::findProduct($AMZ_item['SellerSKU']);
        
        if ($product === false) {
          $product = new Product();
          $product->id_catalog = 'Amazon-' . $AMZ_item['ASIN'];
          $product->product_model = str_replace('_', '-', trim($AMZ_item['SellerSKU']));
          $product->product_url = 'https://www.amazon.com/gp/product/' . $AMZ_item['ASIN'] . '?m=' . $store;
          $product->product_name = $AMZ_item['Title'];
          $product->product_asin = $AMZ_item['ASIN'];
          $product->product_thumb = 'http://order.monogramonline.com/assets/images/no_image.jpg';
          //$product->product_upc = $EDI_item['UPC'];
          $product->batch_route_id = Helper::getDefaultRouteId();
          $product->save();
        }
        
        $item = new Item();
        $item->order_5p = $order->id;
        $item->order_id = $order->order_id;
        $item->store_id = $store;
        $item->item_code = str_replace('_', '-', trim($AMZ_item['SellerSKU']));
        $item->item_description = $AMZ_item['Title'];
        $item->item_quantity = $AMZ_item['QuantityOrdered'];
        $item->item_unit_price = $AMZ_item['ItemPrice']['Amount'] / $AMZ_item['QuantityOrdered'];
        $item->data_parse_type = 'API';
        $item->item_option = $options;
        if ($product) {
          //$item->item_id = $product->id_catalog;
          $item->item_thumb = $product->product_thumb;
          $item->item_url = 'https://www.amazon.com/gp/product/' . $AMZ_item['ASIN'] . '?m=' . $store;
          $item->item_code = $product->product_model;
        } else {
          $item->item_code = $AMZ_item['SellerSKU'];
        }
        $item->edi_id = $AMZ_item['OrderItemId'];
        $item->child_sku = Helper::getChildSku($item);

        $item->save();
        
        $image_file = $this->getPhotos($store, $AMZ_item['OrderItemId'], $order->short_order . '-' .  $item->id);
        
        if ($image_file != null) {
          $item->sure3d = $image_file;
          $item->save();
        }
        
        if ($item->item_option == '[]') {
          $order->order_status = 15;
        } 
        
        // -------------- Items table data insertion ended ---------------------- //
        
      }
      
      try {
        $isVerified = Shipper::isAddressVerified($customer);
      } catch ( \Exception $exception ) {
        $isVerified = 0;
      }
      
      if ($isVerified) {
        $customer->is_address_verified = 1;
      } else {
        $customer->is_address_verified = 0;
        if ($order->order_status != 15) {
          $order->order_status = 11;
        }
      }
      $customer->save();
      
      $order->shipping_charge = $shipping_total;
      $order->tax_charge = $tax_total;
      
      if ($discount_total > 0) {
        $order->promotion_id = 'Amazon Discount';
        $order->promotion_value = $discount_total;
      }
      
      $order->save();
      
      return;    
    }
    
    private function getPersonalization ($store, $url, $item_id) {
                
        $options_dir = storage_path() . $this->dir .  $store . '/';
        
        if (!file_exists($options_dir . $item_id . '.zip')) {
          $written = file_put_contents($options_dir . $item_id . '.zip', fopen($url, 'r'));
          
          if (!$written) {
            Log::error('Amazon getPersonalization: Error downloading zip file for item ' . $item_id);
            return json_encode(['error' => 'Options file could not be downloaded']);
          }
        } else {
          Log:info('Amazon getPersonalization: Zip file already exists ' . $item_id);
        }
        
        if (!file_exists($options_dir . $item_id . '/')) {
          $zip = new ZipArchive;
          
          if ($zip->open($options_dir . $item_id . '.zip') === TRUE) {
              $zip->extractTo($options_dir . $item_id . '/');
              $zip->close();
          } else {
              Log::error('Amazon getPersonalization: Error extracting zip file for item ' . $item_id);
              return json_encode(['error' => 'Zip file could not be extracted']);
          }
        } else {
          Log::info('Amazon getPersonalization: Zip file already extracted ' . $item_id);
        }

        $file_list =  array_diff(scandir($options_dir . $item_id . '/'), array('..', '.')); 
        
        // if (count($file_list) > 1) {
        //   //if there is an svg file, return error
        //   foreach ($file_list as $file) {
        //     if (strtolower(substr($file_list[0], -3)) == 'svg') {
        //       Log::error('Amazon getPersonalization: SVG File found ' . $item_id);
        //       return json_encode(['error' => 'Options in Image File']);
        //     } else {
        //       Log::error('Amazon getPersonalization: Too many options Files found ' . $item_id);
        //       return json_encode(['error' => 'Options in too many Files']);
        //     }
        //   }
        // }
        
        foreach ($file_list as $file) {

          if (strtolower(substr($file, -4)) == 'json') {
            
            $str = file_get_contents($options_dir . $item_id . '/' . $file);
            $json = json_decode($str, true);
            $options = array();
            
            foreach ($json['version3.0']['customizationInfo']['surfaces']  as $surface) {
            
              foreach ($surface['areas'] as $area) {
                if (!isset($area['customizationType'] )) {
                  Log::info('Amazon getPersonalization: NO customizationType ' . $item_id . ' - ' . $area);
                  continue;
                } else if ($area['customizationType'] == 'TextPrinting') {
                  $options[$area['name']] = trim($area['text']);
                } else if ($area['customizationType'] == 'Options') {
                  $options[$area['label']] = trim($area['optionValue']);
                } else if ($area['customizationType'] == 'ImagePrinting') {
                  $options[$area['name']] = trim($area['svgImage']);
                } else {
                  Log::error('Amazon getPersonalization: Unrecognized options ' . $item_id . ' - ' . $area);
                }
              }
            }

            return json_encode($options);
          }
        }

    }
    
    private function getPhotos($store, $AMZItem, $filename) {
      
      $item_dir = storage_path() . $this->dir .  $store . '/' . $AMZItem . '/';
      
      if (!file_exists($item_dir)) {
        return null;
      }
      
      $sure3d_dir = '/media/RDrive/Sure3d/';
      
      if (!file_exists($sure3d_dir)) {
        return null;
      }
      
      $file_list =  array_diff(scandir($item_dir), array('..', '.')); 
      
      $image_file = null;
      
      foreach ($file_list as $file) {
        if (strtolower(substr($file, -4)) == 'json') {
          continue;
        } else {
          $ext = substr($file,  strpos($file, '.'));
          copy($item_dir . $file, $sure3d_dir . $filename . $ext);
          if ($ext != '.svg') {
            $image_file = $file;
          }
        }
      }
      
      return $image_file;
      
    }
    
    private function cancelOrder($order) { 
      
      if ($order->order_status == '6') {
        // mail(['jennifer_it@monogramonline.com'], 'Amazon Cancellation problem', 
        //       'An Amazon customer tried to cancel order ' . $order->order_id . ', but it has already shipped.');
        Log::info('Amazon SC: Customer tried to cancel order through Amazon, but it already shipped. ' . $order->order_5p);
        Order::note('Customer tried to cancel order through Amazon, but it already shipped.', $order->order_5p);
        return false;
      }
      
      $msg = '';
      
      foreach ($order->items as $item) {
        
        if ($item->item_status != 'shipped' && 
             ($item->batch_number == '0' || ($item->batch->summary_date == NULL && $item->batch->export_count == 0))) {
               
              continue;
              
        } else {
          
          if ($item->item_status == 'shipped') {
            
            $msg .= 'Item ' . $item->item_number . ' has already shipped, ';
            
          } else {
            
            if ($item->batch_number != '0') {
              $msg .= 'Item ' . $item->item_number . ' is in batch ' . $item->batch_number .', ';
            }
            
            if ($item->batch && $item->batch->summary_date != NULL) {
              $msg .= ' and batch ' . $item->batch_number . ' has had a summary printed, ';
            }
            
            if ($item->batch && $item->batch->export_count != 0) {
              $msg .= ' and batch ' . $item->batch_number . ' has been exported, ';
            }
          }
        }
      }
      
      if ($msg != '') {
        
        // mail(['jennifer_it@monogramonline.com'], 'Amazon Cancellation problem', 
        //         'An Amazon customer tried to cancel order ' . $order->order_id . ' ' . $msg);
        
        Log::info('Amazon SC: Cancellation problem ' . $msg . ' - ' . $order->order_5p);
        // Order::note('Amazon Cancellation problem. ' . $msg, $order->order_5p);
        return false;
        
      } else {
        
        foreach ($order->items as $item) {
          
        	$item->batch_number = '0';
          $item->item_status = 6;
					$item->save();
					Batch::isFinished($item->batch_number);
				}
        
        $order->order_status = 8;
        $order->save();
      }
    }
    
    
    
    public function shipmentNotification ($store, $shipments) { 
      
      $xml = new \SimpleXMLElement('<AmazonEnvelope></AmazonEnvelope>'); 
      
        $xml->addAttribute('xmlns:xmlns:xsi', "http://www.w3.org/2001/XMLSchema-instance");
        $xml->addAttribute('xsi:xsi:noNamespaceSchemaLocation', "amznenvelope.xsd");
      
        $header = $xml->addChild('Header'); 
          $header->addChild('DocumentVersion', '1.01'); 
          $header->addChild('MerchantIdentifier', $store);
      
        $xml->addChild('MessageType', 'OrderFulfillment'); 
      
        $count = 0;
      
        foreach ($shipments as $shipment) {
                    
          $message = $xml->addChild('Message'); 
          $count++;
          
            $message->addChild('MessageID', $count);
            
            $order_info = $message->addChild('OrderFulfillment');
              
              $order_info->addChild('AmazonOrderID', $shipment->order->order_id);
              $order_info->addChild('FulfillmentDate', substr($shipment->transaction_datetime, 0, 10) . 'T' . substr($shipment->transaction_datetime, 11));
          
              $shipping = $order_info->addChild('FulfillmentData');
              
              if (strpos($shipment->mail_class, 'Mail Innovation'))  {
                $shipping->addChild('CarrierCode', 'UPS');
              } else {
                $carrier = substr($shipment->mail_class, 0, strpos($shipment->mail_class, ' '));
                if ($carrier == 'FEDEX') {
                  $shipping->addChild('CarrierCode', 'FedEx');
                } else if ($carrier == 'UPS') {
                  $shipping->addChild('CarrierCode', 'UPS');
                } else {
                  $shipping->addChild('CarrierName', $carrier);
                }
              }
              
              $shipping->addChild('ShippingMethod', $shipment->mail_class); 
              $shipping->addChild('ShipperTrackingNumber', $shipment->tracking_number);
        
              foreach ($shipment->items as $item) {
                
                $item_info = $order_info->addChild('Item');
                  $item_info->addChild('AmazonOrderItemCode', $item->edi_id);
                  $item_info->addChild('Quantity', $item->item_quantity);
              }
        }
      
      $feedname = $store . '_' . date("YmdHis");
      $fullfile = storage_path() . $this->dir . $store . '/' . $feedname  . '.xml'; 
      echo $xml->asXML($fullfile);
      
      $feed = file_get_contents($fullfile);
      
      $this->sendFeed($store, '_POST_ORDER_FULFILLMENT_DATA_', $feed);
      
      Log::info('Amazon_SC shipmentNotification: Feed Submitted');
      return true;
    } 
    
    public function exportShipments($store, $shipments) {
      
    }
    
    private function processFeeds($store) {
      
      $list= $this->getFeedStatus($store);
      
      if ($list) {
          foreach ($list as $feed) {
              $result = $this->getFeedResult($store, $feed['FeedSubmissionId']);
              
              echo '<br><b>Type:</b> '.$feed['FeedType'];
              echo '<br><b>Date Sent:</b> '.$feed['SubmittedDate'];
              echo '<br><b>Status:</b> '.$feed['FeedProcessingStatus'];
          }
      }
    }
    
    private function getFeedStatus($store) {
        try {
            $amz=new AmazonFeedList($store);
            $amz->setTimeLimits('- 24 hours'); 
            $amz->setFeedStatuses(array("_DONE_"));
            $amz->fetchFeedSubmissions(); 
            return $amz->getFeedList();
        } catch (Exception $ex) {
            Log::error('getFeedStatus: There was a problem with the Amazon library. Error: '.$ex->getMessage());
        }
    }

    private function sendFeed($store, $feed_type, $feed) {
        
        try {
            $amz=new AmazonFeed($store); 
            $amz->setFeedType($feed_type); 
            $amz->setFeedContent($feed); 
            $amz->submitFeed(); 
            return $amz->getResponse();
        } catch (Exception $ex) {
            Log::error('sendFeed: There was a problem with the Amazon library. Error: '.$ex->getMessage());
        }
    }

    private function getFeedResult($store, $feedId) {
        try {
            $amz=new AmazonFeedResult($store, $feedId); 
            $amz->fetchFeedResult();
            return $amz->getRawFeed();
        } catch (Exception $ex) {
            Log::error('getFeedResult: There was a problem with the Amazon library. Error: '.$ex->getMessage());
        }
    }
    
    public function orderConfirmation($store, $order) {
      
    }
     
    public function backorderNotification($store, $item) {
      
    }
    
    public function shipLabel($store, $unique_order_id, $order_id) {
      
    }
    
}
