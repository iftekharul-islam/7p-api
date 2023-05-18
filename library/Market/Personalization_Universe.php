<?php 
 
namespace Market; 

use App\Order;
use App\Customer;
use App\Item;
use App\Ship;
use App\Product;
use App\Parameter;
use Illuminate\Support\Facades\Log; 
use Monogram\Helper;
use Monogram\CSV;

class Personalization_Universe extends StoreInterface 
{ 
		protected $dir = '/EDI/Personalization_Universe/'; 
		
		public function importCsv($store, $file) {
			
			$filename = 'import_' . date("Ymd_His", strtotime('now')) . '.csv';
			
			$saved = move_uploaded_file($file, storage_path() . $this->dir . $filename); 
			
			if (!$saved) {
				return false;
			}
			
			$raw_data = file_get_contents(storage_path() . $this->dir . $filename);
			
			if (substr($raw_data, 0, 9) == 'begin 644') {
				//decode
        $raw_data = explode(PHP_EOL, $raw_data);
				$data = array();
				$full_line = '';
				
				foreach ($raw_data as $line) { 
            
            $line = str_replace("\r", "", $line);
            if (substr($line, 0, 9) != 'begin 644' && $line != '`' && $line != 'end' && strlen($line) > 0) {
              $decoded = convert_uudecode($line);
            } else {
              $decoded = '';
            }
            
            if (strpos($decoded, "\n")) {
              $data[] = str_getcsv($full_line . substr($decoded, 0, strpos($decoded, "\n")), ',', '"');
              $full_line = substr($decoded, strpos($decoded, "\n") + 2);
            } else {
              $full_line .= $decoded;
            }
				}
        
        $data[] =  str_getcsv($full_line, ',', '"');
        
        // foreach ($data as $row_key => $row) {
        //   foreach ($row as $field_key => $field) {
        //     $data[$row_key][$field_key] = str_replace("\x14r\"", '', $field);
        //   }
        // }
        
			} else {
				$csv = new CSV;
				$data = $csv->intoArray(storage_path() . $this->dir . $filename, ',');
			}
			
			// dd($data);
			
			$error = array();
			$order_ids = array();
			
			set_time_limit(0);
			
			foreach ($data as $line)  {
				
				if ( isset($line[0]) && !strpos($line[0], 'MMDDHH24')) {
					
					Log::info('Personalization Universe import: Processing order ' . $line[1]);
					
					$previous_order = Order::join('customers', 'orders.customer_id', '=', 'customers.id')
														->where('orders.is_deleted', '0')
														->where('orders.order_id', $line[1])
														->first();
					
					if ( $previous_order ) {
						 Log::info('Personalization Universe : Order number already in Database ' . $line[1]); 
						 $error[] = 'Order number already in Database ' . $line[1];
						 continue;
					}
					
					$order_ids[] = $this->insertOrder($line);
					Log::info('Personalization Universe import: order ' . $line[1] . ' processed');
					 
				}
			}
			
      if (count($order_ids) == 0) {
        $error[] = 'No Orders Imported from File';
      }
      
			return ['errors' => $error, 'order_ids' => $order_ids];
		}
		
		private function insertOrder($data) { 
			
			// -------------- Customers table data insertion started ----------------------//
			$customer = new Customer();
			
			$customer->order_id = $data[1];
			$customer->ship_full_name = $data[5];
			$customer->ship_last_name = (strpos($data[5], ' ') === false) ? '' : preg_replace('#.*\s([\w-]*)$#', '$1', $data[5]);
			$customer->ship_first_name = trim( preg_replace('#'.$customer->ship_last_name.'#', '', $data[5] ) );
			$customer->ship_company_name = isset($data[6]) ? $data[6] : null;
			$customer->ship_address_1 = $data[7];
			$customer->ship_address_2 = isset($data[8]) ? $data[8] : null;
			$customer->ship_city = $data[9];
			$customer->ship_state = Helper::stateAbbreviation($data[10]);
			$customer->ship_zip = $data[11];
			$customer->ship_country = 'US';
			$customer->ship_phone = $data[12];
			
			$customer->bill_email = 'DROPSHIP@MONOGRAMONLINE.COM';
			$customer->bill_company_name = 'Personalization Universe';
			
			// -------------- Customers table data insertion ended ----------------------//
			// -------------- Orders table data insertion started ----------------------//
			$order = new Order();
			$order->order_id = $data[1];
			$order->short_order = $data[1];
			$order->item_count = 1;
			$order->shipping_charge = '0';
			$order->tax_charge = '0';
			$order->total = 0; 
			$order->order_date = date("Y-m-d H:i:s", strtotime(date('Y') . substr($data[0], 0, 4)));
			$order->store_id = 'pz_univ001'; 
			$order->store_name = 'Personalization Universe';
			$order->ship_state = $data[10];
      $order->ship_date = substr($data[20], 0, 4) . '-' . substr($data[20], 4, 2) . '-' . substr($data[20], 6, 2);
			$order->carrier = 'MN';
			$order->method = 'Ship through Bloomlink Portal'; // our only shipping method - also in ship notify
			
      if ($order->ship_date >= date("Y-m-d")) {
        $order->order_status = 12;
      } else {
        $order->order_status = 4;
      }
      
			// -------------- Orders table data insertion ended ----------------------//
			// -------------- Items table data insertion started ------------------------//
				
				// $product = Product::where('product_model', $data[15])->first();
				
        $product = Helper::findProduct($data[15]);
        
        $parameters = Parameter::where('is_deleted', '0')
                                ->get()
                                ->pluck('parameter_value')
                                ->toArray();
        
				if (!$product) {
					Log::error('Personalization Universe: Product not found ' . $data[15]);
					$desc = 'NOT FOUND';
					$price = 0;
					$item_id = null;
					$thumb = null;
					$url = null;
				} else {
					$desc = $product->product_name;
					$price = $product->product_wholesale_price;
					$item_id = $product->id_catalog;
					$thumb = $product->product_thumb;
					$url = $product->product_url;
				}
				
				$options = array();
				
				if (trim($data[23]) != '') {
					$options['Monogram'] = $data[23];
				}
				
				for ($i = 24; $i < count($data); $i = $i + 2) {
          if (in_array($data[$i], $parameters)) {
					      $options[$data[$i]] = $data[$i+1];
          } else {
              $options['*' . $data[$i] . '*'] = $data[$i+1];
          }
				}
				
				if (trim($data[14]) != '') {
					$options['Instructions'] = $data[14];
				}
				
        $count = 1;
        
				for ($i = 25; $i < count($data); $i = $i + 2) {
          if (!in_array($data[$i-1], $parameters)) {
					      $options[$count] = $data[$i];
                $count += 1;
          }
				}
				
				$item = new Item();
				$item->order_id = $order->order_id;
				$item->store_id = 'pz_univ001'; 
				$item->item_description = $desc;
				$item->item_quantity = intval($data[18]);
				$item->item_unit_price = $price; 
				$item->data_parse_type = 'CSV';
				$item->item_code = $data[15];
				$item->item_id = $item_id; 
				$item->item_thumb = $thumb;
				$item->item_url = $url;
				$item->item_option = json_encode($options);
				$item->edi_id = $data[22];
				
        if ($product) {
				    $item->child_sku = Helper::getChildSku($item);
        } else {
            $item->child_sku = $data[15];
        }
				
				$customer->save();
				
				try {
					$order->customer_id = $customer->id;
				} catch ( \Exception $exception ) {
					Log::error('Failed to insert customer id in Personalization Universe');
				}
				
				$order->total = $item->item_unit_price * $item->item_quantity;
				$order->save();
				
				try {
					$order_5p = $order->id;
				} catch ( \Exception $exception ) {
					$order_5p = '0';
					Log::error('Failed to get 5p order id in Personalization Universe');
				}
				
				$item->order_5p = $order->id;
				$item->save();
				
				//Inventory::addInventoryByStockNumber(null, $item->child_sku);
				// -------------- Items table data insertion ended ---------------------- //
				Order::note('Order imported from CSV', $order->id, $order->order_id);
				
				return $order->id;
			
		}
		
		public function orderConfirmation($store, $order) {
			
		} 
	
		public function shipmentNotification($store, $shipments) {
		 
		}
    
    public function exportShipments($store, $shipments) {
      
    }
    
		public function getInput($store) {
			
		} 
		 
		public function backorderNotification($store, $item) {
			
		}
		
		public function shipLabel($store, $unique_order_id, $order_id) {
			
		}
				 
}
