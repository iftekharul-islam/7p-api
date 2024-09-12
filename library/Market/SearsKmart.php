<?php 
 
namespace Market; 

use App\Order;
use App\Customer;
use App\Item;
use App\Ship;
use App\Store;
use App\Product;
use Illuminate\Support\Facades\Log; 
use Monogram\Helper;
use Monogram\CSV;

class SearsKmart extends StoreInterface 
{ 
		protected $download_dir = '/EDI/download/';
		protected $store_lookup = ['125177' => 'Sears Online', '125178' => 'Kmart Online'];
    
		public function importCsv($store, $file) {
			
			$filename = 'import_' . date("ymd_His", strtotime('now')) . '.csv';
			
			$saved = move_uploaded_file($file, storage_path() . '/EDI/SearsKmart/' . $filename); 
			
			if (!$saved) {
				return false;
			}
			
			$csv = new CSV;
			$data = $csv->intoArray(storage_path() . '/EDI/SearsKmart/' . $filename, ",");
			
			$error = array();
			$order_ids = array();
			
			set_time_limit(0);
			
			$po = '';
			$total = 0;
			$count = 0;
			$order_5p = null;
			
			foreach ($data as $line)  {
				
				if ($line[0] != 'Id') {
					
					if (count($line) != 44) {
						 $error[] = 'Incorrect number of fields in file: ' . count($line);
						 break;
					}
					
					$store = Store::where('store_name', $this->store_lookup[$line[3]])->first();
					
					if (!$store) {
						 $error[] = 'Store name from file not found in 5p: ' . $line[5];
						 break;
					}
					
					if ($line[5] != $po) {
						
						$po = $line[5];
						$count = 0;
						$total = 0;
						
						Log::info('SearsKmart import: Processing order ' . $line[5]);
						
						
						$previous_order = Order::where('orders.is_deleted', '0')
															->where('orders.order_id', $line[5])
															->first();
						
						if ( !$previous_order ) {
							$order_5p = $this->insertOrder($line, $store);
							$order_ids[] = $order_5p;
							Log::info('SearsKmart import: order ' . $line[5] . ' processed');
						} else {
              $order_5p = $previous_order->id;
							Log::info('SearsKmart : Order number already in Database ' . $line[5]); 
							$error[] = 'Order number already in Database ' . $line[5];
						}
					}
					
					$previous_item = Item::where('order_5p', $order_5p)
																->where('edi_id', $line[0] . ':' . $line[28] . ':' . $line[30] . ':' . $line[31] . ':' . $line[11])
																->where('is_deleted', '0')
																->first();
					
					if (!$previous_item) {
						$this->insertItem($line, $store, $order_5p);
						$count++;
						$total += $line[34] * $line[33];
						$this->setOrderTotals($order_5p);
					}
				}
			}
				
			return ['errors' => $error, 'order_ids' => $order_ids];
		}
		
		private function insertOrder($data, $store) { 
			
			// -------------- Customers table data insertion started ----------------------//
			$customer = new Customer();
			
			$customer->order_id = $data[5];
			$customer->ship_company_name = $data[19];
			$customer->ship_full_name = $data[20] . ' ' . $data[21];
			$customer->ship_last_name = $data[21];
			$customer->ship_first_name = $data[20];
			$customer->ship_address_1 = $data[22];
			$customer->ship_address_2 = isset($data[23]) ? $data[23] : null;
			$customer->ship_city = $data[24];
			$customer->ship_state = Helper::stateAbbreviation($data[26]);
			$customer->ship_zip = $data[25];
			$customer->ship_country = 'US';
			$customer->ship_phone = $data[27];
			
			$customer->bill_email = null;
			$customer->bill_company_name = $data[12];
			$customer->bill_address_1 = $data[13];
			$customer->bill_address_2 = isset($data[14]) ? $data[14] : null;
			$customer->bill_city = $data[15];
			$customer->bill_state = Helper::stateAbbreviation($data[17]);
			$customer->bill_zip = $data[16];
			$customer->bill_country = 'US';
			$customer->bill_phone = $data[18];
			$customer->ignore_validation = TRUE;
			
			// -------------- Customers table data insertion ended ----------------------//
			// -------------- Orders table data insertion started ----------------------//
			$order = new Order();
			$order->order_id = $data[5];
			$order->short_order = $data[5];
			$order->shipping_charge = '0';
			$order->tax_charge = '0'; 
			$order->order_date = date("Y-m-d H:i:s", strtotime($data[1]));
			$order->store_id = $store->store_id; 
			$order->store_name = $this->store_lookup[$data[3]] . ':' . $data[3];
			$order->order_status = 4;
			$order->order_comments = '';
			$order->ship_state = Helper::stateAbbreviation($data[26]);
			$shipinfo = $this->lookup[$data[11]];
			$order->carrier = 'UP';
			$order->method = $shipinfo[1]; 
			
			// -------------- Orders table data insertion ended ----------------------//
				
				$customer->save();
				
				try {
					$order->customer_id = $customer->id;
				} catch ( \Exception $exception ) {
					Log::error('Failed to insert customer id in SearsKmart');
				}
				
				$order->save();
				
				try {
					$order_5p = $order->id;
				} catch ( \Exception $exception ) {
					$order_5p = '0';
					Log::error('Failed to get 5p order id in SearsKmart');
				}

				Order::note('Order imported from CSV', $order->id, $order->order_id);
				
				return $order->id;
				
		}
		
    public function setOrderTotals ($order_5p) {
      if ($order_5p != null) {
        $order = Order::find($order_5p);
    		$order->item_count = count($order->items);
        $total = 0;
        foreach ($order->items as $item) {
          $total += $item->item_quantity * $item->item_unit_price;
        }
        $order->total = sprintf("%01.2f", $total);
        $order->save();
      }
    }
		
		private function insertItem ($data, $store, $order_5p) {
			
			$product = Helper::findProduct($data[29]);
			
			$item = new Item();
			
			if ($product != false) {
				$item->item_code = $product->product_model;
				$item->item_id = $product->id_catalog;
				$item->item_thumb = $product->product_thumb;
				$item->item_url = $product->product_url;
			} else {
				$item->item_code = $data[29];
				Log::error('SearsKmart Product not found ' . $data[29] . ' in order ' . $data[5]);
			}
			
			$item->order_id = $data[5];
			$item->store_id = $store->store_id; 
			$item->item_description = $data[32];
			$item->item_quantity = $data[33];
			$item->item_unit_price = $data[34]; 
			$item->data_parse_type = 'CSV';
			$item->child_sku = $data[29];
			$item->item_option = '{}';
			$item->edi_id = $data[0] . ':' . $data[28] . ':' . $data[30] . ':' . $data[31] . ':' . $data[11];
			$item->order_5p = $order_5p;
			$item->save();
		}
		
		public function orderConfirmation($store, $order) {
			
		} 
	
		public function shipmentNotification($store, $shipments) {
      
		}
    
    public function exportShipments($store, $shipments) {
      
		 Log::info('SearsKmart shipment csv started');

		 $lines = array();
		 
		 $line = array();
		 
		 $line[] = 'Id';
		 $line[] = 'DocumentDate';
     $line[] = 'StatusCode';
     $line[] = 'CoId';
     $line[] = 'Source';
     $line[] = 'PartnerPO';
     $line[] = 'SubTotal';
     $line[] = 'TaxTotal';
     $line[] = 'DiscountTotal';
     $line[] = 'HandlingAmount';
     $line[] = 'TotalAmount';
     $line[] = 'ShipMethod';
     $line[] = 'BillToAddress.CompanyName';
		 $line[] = 'BillToAddress.FirstName';
		 $line[] = 'BillToAddress.LastName';
		 $line[] = 'BillToAddress.Address1';
		 $line[] = 'BillToAddress.Address2';
		 $line[] = 'BillToAddress.City';
		 $line[] = 'BillToAddress.Zip';
		 $line[] = 'BillToAddress.State';
		 $line[] = 'BillToAddress.Phone';
     $line[] = 'ShipToAddress.CompanyName';
		 $line[] = 'ShipToAddress.FirstName';
		 $line[] = 'ShipToAddress.LastName';
		 $line[] = 'ShipToAddress.Address1';
		 $line[] = 'ShipToAddress.Address2';
		 $line[] = 'ShipToAddress.City';
		 $line[] = 'ShipToAddress.Zip';
		 $line[] = 'ShipToAddress.State';
		 $line[] = 'ShipToAddress.Phone';
     $line[] = 'LineNumber';
		 $line[] = 'ItemIdentifier.SupplierSKU';
		 $line[] = 'ItemIdentifier.PartnerSKU';
		 $line[] = 'ItemIdentifier.UPC';
     $line[] = 'Description';
		 $line[] = 'Quantity';
		 $line[] = 'Price';
     $line[] = 'TrackingNumber';
     $line[] = 'CarrierCode';
     $line[] = 'ShipFromAddress.CompanyName';
     $line[] = 'ShipFromAddress.Address1';
     $line[] = 'ShipFromAddress.Address2';
     $line[] = 'ShipFromAddress.City';
     $line[] = 'ShipFromAddress.State';
     $line[] = 'ShipFromAddress.Zip';
     $line[] = 'ShipFromAddress.Country';
     $line[] = 'QuantityShipped';
		 
		 $lines[] = $line;
		 
			foreach ($shipments as $shipment) {
				
				$total_qty = $shipment->items->sum('item_quantity');
				
        $company_id = explode(':', $shipment->order->store_name);
        
				foreach ($shipment->items as $item) {
					
					$exploded = explode(':',$item->edi_id);
					
					$line = array();
					
          $line[] = isset($exploded[0]) ? $exploded[0] : '';
					$line[] = date('Y-m-d\TH:i:s\Z');
          $line[] = '500';
          $line[] = $company_id[1] ?? '';
          $line[] = 'Workflow';
          $line[] = $item->order_id;
          $line[] = $shipment->post_value;
          $line[] = 0;
          $line[] = 0;
          $line[] = 0;
          $line[] = $shipment->post_value;
          $line[] = isset($exploded[4]) ? $exploded[4] : '';
          $line[] = $shipment->order->customer->bill_company_name;
					$line[] = $shipment->order->customer->bill_first_name;
					$line[] = $shipment->order->customer->bill_last_name;
					$line[] = $shipment->order->customer->bill_address_1;
					$line[] = $shipment->order->customer->bill_address_2;
					$line[] = $shipment->order->customer->bill_city;
					$line[] = $shipment->order->customer->bill_zip;
					$line[] = $shipment->order->customer->bill_state;
					$line[] = $shipment->order->customer->bill_phone;
          $line[] = $shipment->order->customer->ship_company_name;
					$line[] = $shipment->order->customer->ship_first_name;
					$line[] = $shipment->order->customer->ship_last_name;
					$line[] = $shipment->order->customer->ship_address_1;
					$line[] = $shipment->order->customer->ship_address_2;
					$line[] = $shipment->order->customer->ship_city;
					$line[] = $shipment->order->customer->ship_zip;
					$line[] = $shipment->order->customer->ship_state;
					$line[] = $shipment->order->customer->ship_phone;
          $line[] = isset($exploded[1]) ? $exploded[1] : '';
          $line[] = $item->child_sku;
          $line[] = isset($exploded[2]) ? $exploded[2] : '';
          $line[] = isset($exploded[3]) ? $exploded[3] : '';
          $line[] = $item->item_description;
          $line[] = $item->item_quantity;
          $line[] = $item->item_unit_price;
          $line[] = $shipment->tracking_number;
          $line[] = isset($exploded[4]) ? substr($exploded[4],0,4) : '';
          $line[] = 'NATICO ORIGINALS INC';
          $line[] = '575 Underhill Blvd';
          $line[] = 'STE 325';
          $line[] = 'Syosset';
          $line[] = 'NY';
          $line[] = '11791';
          $line[] = 'US';
          $line[] = $item->item_quantity;
					
					$lines[] = $line;
				}
			}
			
			if (count($lines) > 0) {
				$filename = $store . '_SHIP_' . date('ymd_His') . '.csv'; 
				$path = storage_path() . '/EDI/SearsKmart/'; 
				try {
					$csv = new CSV;
					$pathToFile = $csv->createFile($lines, $path, null, $filename, ',');
					// copy($path . $filename, storage_path() . '/EDI/SearsKmart/' . $filename);
				} catch (\Exception $e) {
					Log::error('Error Creating SearsKmart CSV - ' . $e->getMessage());
					return;
				}
			}
			
			Log::info('SearsKmart shipment csv upload created');
			
			return $path . $filename;
		}
		
		public function getInput($store) {
			
		} 
		 
		public function backorderNotification($store, $item) {
			
		}
		
		public function shipLabel($store, $unique_order_id, $order_id) {
			
		}
		
		private $lookup = array(        'UPSN-CG'  => ['UP',   'S_GROUND'],
																		'UPSN-3D'  => ['UP',   'S_3DAYSELECT'],
																		'UPSN-SC'  => ['UP',   'S_AIR_2DAY'],
																		'UPSN-ND'  => ['UP',   'S_AIR_1DAY'],
																		'UPSN-PM'  => ['UP',   'S_AIR_1DAYSAVER'],
																		// 'FDE-3D'   => ['FX', '_FEDEX_EXPRESS_SAVER'],
																		// 'FDEG-CG'  => ['FX', '_FEDEX_GROUND'],
																		// 'FDE-SE'   => ['FX', '_FEDEX_2_DAY'],
																		// 'FDE-NM'   => ['FX', '_PRIORITY_OVERNIGHT'],
																); 
}
