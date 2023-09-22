<?php 
 
namespace Market; 

use App\Order;
use App\Customer;
use App\Item;
use App\Ship;
use App\Product;
use App\StoreItem;
use Illuminate\Support\Facades\Log; 
use Monogram\Helper;
use Monogram\CSV;

class Figis extends StoreInterface 
{ 
		protected $dir = '/EDI/Figis/'; 
		protected $download_dir = '/EDI/download/';
    
		public function importCsv($store, $file) {
			
			$filename = 'import_' . date("Ymd_His", strtotime('now')) . '.csv';
			
			$saved = move_uploaded_file($file, storage_path() . $this->dir . $filename); 
			
			if (!$saved) {
				return false;
			}
			
			$csv = new CSV;
			$data = $csv->intoArray(storage_path() . $this->dir . $filename, "\t");
			
			if (substr($data[0][0], 0, 6) == 'sep=,,') {
				$data = $csv->intoArray(storage_path() . $this->dir . $filename, ",");
			}
			
			$error = array();
			$order_ids = array();
			
			set_time_limit(0);
			
			foreach ($data as $line)  {
				
				if ($line[0] != 'sep=' && $line[0] != 'Trans Control No') {
					
					if (count($line) != 90) {
						 $error[] = 'Incorrect number of fields in file: ' . count($line);
						 break;
					}
					
					Log::info('Figis import: Processing order ' . $line[3]);
					
					$previous_order = Order::join('customers', 'orders.customer_id', '=', 'customers.id')
														->where('orders.is_deleted', '0')
														->where('orders.order_id', $line[3])
														->orWhere('customers.ship_state', $line[3])
														->first();
					
					if ( $previous_order ) {
						 Log::info('Figis : Order number already in Database ' . $line[3]); 
						 $error[] = 'Order number already in Database ' . $line[3];
						 continue;
					}
					
					$order_ids[] = $this->insertOrder($line);
					Log::info('Figis import: order ' . $line[3] . ' processed');
					 
				}
			}
			
			return ['errors' => $error, 'order_ids' => $order_ids];
		}
		
		private function insertOrder($data) { 
			
			// -------------- Customers table data insertion started ----------------------//
			$customer = new Customer();
			
			$customer->order_id = $data[3];
			$customer->ship_full_name = $data[40];
			$customer->ship_last_name = (strpos($data[40], ' ') === false) ? '' : preg_replace('#.*\s([\w-]*)$#', '$1', $data[40]);
			$customer->ship_first_name = trim( preg_replace('#'.$customer->ship_last_name.'#', '', $data[40] ) );
			$customer->ship_address_1 = $data[42];
			$customer->ship_address_2 = isset($data[43]) ? $data[43] : null;
			$customer->ship_city = $data[44];
			$customer->ship_state = Helper::stateAbbreviation($data[45]);
			$customer->ship_zip = $data[46];
			$customer->ship_country = ($data[47] != null) ? $data[47] : 'US';
			$customer->ship_phone = $data[49];
			
			$customer->bill_email = $data[50];
			$customer->bill_company_name = 'FIGIS COMPANIES, INC.';
			$customer->bill_address_1 = $data[53];
			$customer->bill_address_2 = isset($data[54]) ? $data[54] : null;
			$customer->bill_city = $data[55];
			$customer->bill_state = Helper::stateAbbreviation($data[56]);
			$customer->bill_zip = $data[57];
			$customer->bill_country = $data[58];
			$customer->bill_phone = $data[49];
			
			// -------------- Customers table data insertion ended ----------------------//
			// -------------- Orders table data insertion started ----------------------//
			$order = new Order();
			$order->order_id = $data[3];
			$order->short_order = $data[3];
			$order->item_count = 1;
			$order->shipping_charge = '0';
			$order->tax_charge = '0';
			$order->total = 0; 
			$order->order_date = date("Y-m-d", strtotime($data[5])) . date(" H:i:s");
			$order->store_id = 'figis-001'; 
			$order->store_name = 'Figis';
			$order->order_status = 4;
			$order->order_comments = $data[39];
			$order->ship_state = $data[45];
			$order->carrier = 'FX';
			$order->method = '_GROUND_HOME_DELIVERY'; // our only shipping method - also in ship notify
			$order->ship_date = date("Y-m-d", strtotime($data[35]));
      
			// -------------- Orders table data insertion ended ----------------------//
			// -------------- Items table data insertion started ------------------------//
				
				$gift_num = substr($data[69], 3, 4);
				
				$product = $this->getProduct($gift_num, $data[71]);
				
				$item_info = $this->parseOptions($data[85]);
				
				//$crawled_info =$this->crawlPrice($product['figis_url'], $gift_num, $data[78]);
				
				$item = new Item();
				$item->order_id = $order->order_id;
				$item->store_id = 'figis-001'; 
				$item->item_description = $data[78];
				$item->item_quantity = $data[64];
				$item->item_unit_price = $product['cost']; 
				$item->data_parse_type = 'CSV';
				$item->item_code = $product['parent_sku'];
				$item->item_id = $product['item_id'];
				$item->child_sku = $product['child_sku'];
				$item->item_thumb = $product['thumb'];;
				$item->item_url = $product['product_url'];
				$item->item_option = json_encode($item_info['options']);
				$item->edi_id = $data[64] . '**' . $data[69];
				
				$customer->save();
				
				try {
					$order->customer_id = $customer->id;
				} catch ( \Exception $exception ) {
					Log::error('Failed to insert customer id in Figis');
				}
				
				$order->total = sprintf("%01.2f", $item->item_unit_price * $item->item_quantity);
				$order->save();
				
				try {
					$order_5p = $order->id;
				} catch ( \Exception $exception ) {
					$order_5p = '0';
					Log::error('Failed to get 5p order id in Figis');
				}
				
				$item->order_5p = $order->id;
				$item->save();
				
				//Inventory::addInventoryByStockNumber(null, $item->child_sku);
				// -------------- Items table data insertion ended ---------------------- //
				Order::note('Order imported from CSV', $order->id, $order->order_id);
				
				return $order->id;
				
		}
		
		private function getProduct($gift_num, $passed_sku) {
			
			$figis_product = StoreItem::with('product')
																->where('custom', $gift_num)
                                ->where('store_id', 'figis-001')
																->first();

			if (count($figis_product) == 1) {
				
				$parent_sku = $figis_product->parent_sku;
				$child_sku = $figis_product->child_sku;
				$figis_url = $figis_product->url;
        $cost = $figis_product->cost;
				if ($figis_product->product) {
					$thumb = $figis_product->product->product_thumb;
					$item_id = $figis_product->product->id_catalog;
					$product_url = $figis_product->product->product_url;
				} else {
					Log::error('Figis product not in product table: ' . $parent_sku);
					$thumb = 'http://order.monogramonline.com/assets/images/no_image.jpg';
					$item_id = null;
					$product_url = null;
				}
				
			} else {
        
				Log::info('Figis product not in Figis Items Table: ' . $passed_sku);
				
        $cost = 0;
				$SKU = $this->findSKU($passed_sku); 
				
				if ($SKU) {
					
					$product = Product::with('options')
												->where('product_model', $SKU)
												->first();
					
					if ($product) {
						
						$parent_sku = $SKU;
						$figis_url = null;
						$thumb = $product->product_thumb;
						$item_id = $product->id_catalog;
						$product_url = $product->product_url;
						
						if (count($product->option) == 1) {
							$child_sku = $product->option->child_sku;
						} else {
							$child_sku = $passed_sku;
						}
						
					} else {
						
						Log::error('Figis Product not found ' . $passed_sku);
						
						$parent_sku = $passed_sku;
						$child_sku = $passed_sku;
						$figis_url = null;
						$thumb = 'http://order.monogramonline.com/assets/images/no_image.jpg';
						$item_id = null;
						$product_url = null;
						
					}
				}
			}
			
			return [
				'parent_sku' => $parent_sku,
				'child_sku' => $child_sku,
				'figis_url' => $figis_url,
				'thumb' => $thumb,
				'item_id' => $item_id,
				'product_url' => $product_url,
        'cost' => $cost
			];
		}
		
		private function findSKU ($text) {
			
			if ($text == NULL) {
				return NULL;
			}
			
			$text = strtolower(str_replace('-', '', $text));
			
			$products = Product::where('is_deleted', '0')
												->where('product_model', 'LIKE', substr($text,0,2) . '%')
												->get()
												->pluck('product_model');
			
			$product_map = array();
			
			foreach($products as $product) {
				$product_map[strtolower(str_replace('-', '', $product))] = $product;
			}
			
			$SKU = NULL;
			
			do {
				if (isset($product_map[$text])) {
					$SKU = $product_map[$text];
				} else {
					$text = substr($text, 0, strlen($text) - 1);
					
				}
			} while ($SKU == null && strlen($text) > 0);
			
			return $SKU;
		}

		private function parseOptions ($text) {
			
			if (substr($text, 0, 2) != 'G#') {
				return ['gift_num' => null, 'options' => []];
			}
			
			$text = trim($text);
			
			$gift_num = substr($text, 2, strpos($text, ' ') - 2);
			
			$option_str = substr($text, strpos($text, ' ') + 1);
			$options = array();
			$options_count = substr_count($option_str, '/');
			
			for ($i = 1; $i <= $options_count; $i++) {
				$start = strpos($option_str, $i . '/') + 2;
				if (strpos($option_str, '/', $start)) {
					$next = strpos($option_str, '/', $start) - 2;
				} else {
					$next = strlen($option_str);
				}
				$length = $next - $start;
				
				$options[$i] = substr($option_str, $start,  $length);
			}
			
			$options['gift_num'] = $gift_num;
			
			return ['gift_num' => $gift_num, 'options' => $options];
		}
		
		private function crawlPrice ($url, $gift_num, $description) {
			
			$urls= array();
			$urls[] = $url;
			$urls[] = 'https://www.figis.com/gallery?Ntt=' . str_replace(' ', '+', $description);
			$urls[] = 'https://www.figis.com/gallery?Ntt=' . $gift_num;
			
			foreach ($urls as $url) {
				
				$page = null;
				$attempts = 0;
				
				do {
					
					try {
						$page = file_get_contents($url);
					} catch (\Exception $e) {
						$attempts++;
						sleep(2);
						Log::info('Figis crawlPrice: Error getting figis page ' . $e->getMessage() . ' - attempt # ' . $attempts);
						continue;
					}
					
					break;
					
				} while($attempts < 4);
				
				if ($attempts == 4) {
					Log::info('Figis crawlPrice: Failed getting figis page, ' . $url);
				}
				
				if (strpos($page, 'pricePicker')) {
			
					$price_start = strpos($page, '$', strpos($page, 'pricePicker')) + 1;
					$price_end = strpos($page, '.', $price_start) + 3;
					$price = substr($page, $price_start, $price_end - $price_start);
					
					if ($price && $price > 0) {
						return ['url' => $url, 'price' => $price];
					} else {
						return ['url' => $url, 'price' => 0];
					}
				}
			}
			
			return ['url' => null, 'price' => 0];
		}
		
		public function orderConfirmation($store, $order) {
			
		} 
	
		public function shipmentNotification($store, $shipments) {
		
    }
    
    public function exportShipments($store, $shipments) {
      
     Log::info('Figis shipment csv started');

		 $lines = array();
		 
			foreach ($shipments as $shipment) {

				foreach ($shipment->items as $item) {
					
					$line = array();
					
					$line[0] = 'FIGI50669';
					$line[1] = $shipment->unique_order_id;
					$line[2] = date('m/d/Y' , strtotime($shipment->transaction_datetime));
					$line[3] = date('Hi' , strtotime($shipment->transaction_datetime));
					$line[4] = 'CTN';
					$line[5] = 1;
					$line[6] = null;
					$line[7] = null;
					$line[8] = 'FDEG'; // the only shipping method
					$line[9] = null;
					$line[10] = 'FEDEX';
					$line[11] = null;
					$line[12] = null;
					$line[13] = null;
					$line[14] = $shipment->tracking_number;
					$line[15] = null;
					$line[16] = null;
					$line[17] = date('m/d/Y' , strtotime($shipment->transaction_datetime));
					$line[18] = null;
					$line[19] = null;
					$line[20] = date('m/d/Y' , strtotime("+1 week"));
					$line[21] = 'DE';
					$line[22] = 'ZZ';
					$line[23] = null;
					$line[24] = null;
					$line[25] = null;
					$line[26] = null;
					$line[27] = null;
					$line[28] = null;
					$line[29] = null;
					$line[30] = null;
					$line[31] = '506669';
					$line[32] = 'MONOGRAM ONLINE INC';
					$line[33] = null;
					$line[34] = null;
					$line[35] = null;
					$line[36] = null;
					$line[37] = null;
					$line[38] = 'DROPSHIP';
					$line[39] = $shipment->order->customer->ship_full_name;
					$line[40] = $shipment->order->customer->ship_address_1;
					$line[41] = $shipment->order->customer->ship_address_2;
					$line[42] = $shipment->order->customer->ship_city;
					$line[43] = $shipment->order->customer->ship_state;
					$line[44] = $shipment->order->customer->ship_zip;
					$line[45] = null;
					$line[46] = null;
					$line[47] = 'DROPSHIP';
					$line[48] = 'XYZ';
					$line[49] = '575 UNDERHILL BLVD STE 325';
					$line[50] = 'SYOSSET';
					$line[51] = 'NY';
					$line[52] = '11791';
					$line[53] = null;
					$line[54] = null;
					$line[55] = null;
					$line[56] = '00';
					$line[57] = null;
					$line[58] = null;
					$line[59] = null;
					$line[60] = null;
					$line[61] = null;
					$line[62] = null;
					$line[63] = null;
					$line[64] = null;
					$line[65] = null;
					$line[66] = null;
					$line[67] = null;
					$line[68] = null;
					$line[69] = $shipment->order->short_order;
					$line[70] = date('m/d/Y' , strtotime($shipment->order->order_date));
					$line[71] = null;
					$line[72] = null;
					$line[73] = null;
					$line[74] = null;
					$line[75] = null;
					$line[76] = null;
					$line[77] = null;
					$line[78] = null;
					$line[79] = null;
					$line[80] = 'DROPSHIP';
					$line[81] = 'GWENDOLYN JOHNSON';
					$line[82] = null;
					$line[83] = null;
					$line[84] = null;
					$line[85] = null;
					$line[86] = null;
					$line[87] = null;
					$line[88] = null;
					$line[89] = null;
					$line[90] = null;
					$line[91] = null;
					$line[92] = null;
					$line[93] = null;
					$line[94] = null;
					$line[95] = null;
					$line[96] = 'GM';
					$line[97] = sprintf('%021d', '081810202' . '0' . substr($item->order_5p,-6));
					$line[98] = null;
					$line[99] = null;
					$line[100] = null;
					$line[101] = null;
					$line[102] = null;
					$line[103] = null;
					$line[104] = sprintf('%03d', substr($item->edi_id,0,strpos($item->edi_id,'**')));
					$line[105] = 'SK';
					$line[106] = substr($item->edi_id,strpos($item->edi_id,'**') + 2); //figis product code
					$line[107] = 'VA';
					$line[108] = $item->item_code;
					$line[109] = null;
					$line[110] = null;
					$line[111] = null;
					$line[112] = null;
					$line[113] = null;
					$line[114] = null;
					$line[115] = null;
					$line[116] = null;
					$line[117] = date('m/d/Y' , strtotime($shipment->transaction_datetime));;
					$line[118] = $item->item_quantity;
					$line[119] = 'EA';
					$line[120] = null;
					$line[121] = null;
					$line[122] = null;
					$line[123] = null;
					$line[124] = null;
					$line[125] = null;
					$line[126] = null;
					$line[127] = null;
					$line[128] = null;
					$line[129] = null;
					$line[130] = null;
					$line[131] = null;
					
					$lines[] = $line;
          
				}
			}
			
			if (count($lines) > 0) {
				$filename = 'FIGIS_SHIP_' . date('Ymd_His') . '.csv'; 
				$path = storage_path() . $this->dir; 
        try {
  				$csv = new CSV;
  				$pathToFile = $csv->createFile($lines, $path, null, $filename, "\t");
          // copy($path . $filename, storage_path() . $this->dir . $filename);
        } catch (\Exception $e) {
          Log::error('Error Creating Figis CSV - ' . $e->getMessage());
          return;
        }
			}
			
			Log::info('Figis shipment csv upload created');

			return $path . $filename;
		}
		
		public function getInput($store) {
			
		} 
		 
		public function backorderNotification($store, $item) {
			
		}
		
		public function shipLabel($store, $unique_order_id, $order_id) {
			
		}
				 
}
