<?php 
 
namespace Market; 

use App\Order;
use App\Customer;
use App\Item;
use App\Ship;
use App\Product;
use Illuminate\Support\Facades\Log; 
use Monogram\Helper;
use Monogram\CSV;

class Houzz extends StoreInterface 
{ 
		protected $dir = ['houzz-01' => '/EDI/Houzz/']; 
		protected $download_dir = '/EDI/download/';
    
    private function simpleRequest($url, $input_xml = null) {
      
      try {
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,​ 'https://api.houzz.com/​api?format=xml&' . $url);
        if ($input_xml != null) {
          $headers = array(
              "X-HOUZZ-API-SSL-TOKEN: fQABAAAAu7qSW4IAOWCMuunVaTZbEXBXBG3bWyDE2clWES8LHY2Y2aBvRRy0R25hdGljbzE=",
              "X-HOUZZ-API-USER-NAME: natico1",
              "X-HOUZZ-API-APP-NAME: natico1app",
              "Content-type: text/xml",
              "Content-length: " . strlen($input_xml),
              "Connection: close",
          );
          curl_setopt($ch, CURLOPT_POSTFIELDS, $input_xml);
          curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        } else {
          $headers = array(
            "X-HOUZZ-API-SSL-TOKEN: fQABAAAAu7qSW4IAOWCMuunVaTZbEXBXBG3bWyDE2clWES8LHY2Y2aBvRRy0R25hdGljbzE=",
            "X-HOUZZ-API-USER-NAME: natico1",
            "X-HOUZZ-API-APP-NAME: natico1app",
          );
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);    
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);     
        $xml=curl_exec($ch);
        curl_close($ch);
      } catch (\Exception $e) {
        Log::info('Houzz Curl Error: ' . $e->getMessage());
        return false;
      }
      
      if($xml != 404) { 
        try {
          $doc = simplexml_load_string($xml); 
          $json = json_encode($doc); 
          return json_decode($json,TRUE);
        } catch (\Exception $e) {
          Log::info('Houzz XML Decode Error: ' . $e->getMessage());
          return false;
        }
      } else {
        Log::info('Houzz 404 Error');
        return false;
      }
    }
    
		public function importCsv($store, $file) {
			
		}
		
    public function getInput($store) {
      
      $result = $this->simpleRequest("method=getOrders&Status=Charged");
      
      if ($result['GetOrdersResponse']['Ack'] == 'Success') {
        foreach ($result['Orders'] as $order) {
          $db_order = Order::with('items.batch')
                        ->where('order_id', $order['OrderId'])
                        ->where('is_deleted', '0')
                        ->first();
          
          if (!$db_order) {
            $this->insertOrder($store, $order);
          }           
        }
      }
    } 
    
		private function insertOrder($data, $store) { 
			
			// -------------- Customers table data insertion started ----------------------//
			$customer = new Customer();
			
			$customer->order_id = $data['OrderId'];
			$customer->ship_full_name = $data['CustomerName'];
      $customer->ship_last_name = (strpos($data['CustomerName'], ' ') === false) ? '' : preg_replace('#.*\s([\w-]*)$#', '$1', $data['CustomerName']);
      $customer->ship_first_name = trim( preg_replace('#'.$customer->ship_last_name.'#', '', $data['CustomerName'] ) );
			
      $customer->ship_address_1 = $data['Address']['Address'];
      $customer->ship_city = $data['Address']['City'];
			$customer->ship_state = Helper::stateAbbreviation($data['Address']['State']);
			$customer->ship_zip = $data['Address']['Zip'];
			$customer->ship_country = $data['Address']['Country'];
			$customer->ship_phone = $data['Address']['Phone'];;
			
			$customer->bill_email = null;
			$customer->bill_company_name = $store->store_name;
			
			// -------------- Customers table data insertion ended ----------------------//
			// -------------- Orders table data insertion started ----------------------//
			$order = new Order();
			$order->order_id = $data['OrderId'];
			$order->short_order = $data['OrderId'];
			$order->order_date = date("Y-m-d H:i:s", strtotime($data['Created']));
			$order->store_id = $store->store_id; 
			$order->store_name = '';
			$order->order_status = 4;
			$order->order_comments = '';
			$order->ship_state = Helper::stateAbbreviation($data['Address']['State']);
      $order->carrier = 'AP'; 
			$order->method = 'Label through API'; 
			
      $customer->save();
      
      try {
        $order->customer_id = $customer->id;
      } catch ( \Exception $exception ) {
        Log::error('Failed to insert customer id in Houzz');
      }
      
      $order->save();
      
      try {
        $order_5p = $order->id;
      } catch ( \Exception $exception ) {
        $order_5p = '0';
        Log::error('Failed to get 5p order id in Houzz');
      }
      
			// -------------- Orders table data insertion ended ----------------------//
			
      foreach ($data['OrderItems'] as $APIitem) {
        
        $product = Helper::findProduct($APIitem['SKU']);
        
        $item = new Item();
        
        if ($product != false) {
          $item->item_code = $product->product_model;
          $item->item_id = $product->id_catalog;
          $item->item_thumb = $product->product_thumb;
          $item->item_url = $product->product_url;
          
        } else {
          $item->item_code = $APIitem['SKU'];
          Log::error('Houzz Product not found ' . $APIitem['SKU'] . ' in order ' . $data['OrderId']);
        }
        
        $item->order_id = $data['OrderId'];
        $item->store_id = $store_id; 
        $item->item_description = $data['Title'];
        $item->item_quantity = $APIitem['Quantity'];
        $item->item_unit_price = $APIitem['Cost']; 
        $item->data_parse_type = 'API';
        $item->child_sku = $APIitem['SKU'];
        $item->item_option = '{}';
        $item->edi_id = $data['ProductId'];
        $item->order_5p = $order_5p;
        $item->save();
      }
        
				Order::note('Order received through API', $order->id, $order->order_id);
				
				return $order->id;
				
		}
    
    public function shipmentNotification($store, $shipments) {
     
    }
    
    public function exportShipments($store, $shipments) {
      
    }
    
		public function orderConfirmation($store, $order) {
			
		} 
		 
		public function backorderNotification($store, $item) {
			
		}
		
		public function shipLabel($store, $unique_order_id, $order_id) {
			
		}
}
