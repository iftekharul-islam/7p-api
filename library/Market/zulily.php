<?php 
 
namespace Market; 

use App\Order;
use App\Customer;
use App\Item;
use App\Ship;
use App\Product;
use App\Parameter;
use App\StoreItem;
use Illuminate\Support\Facades\Log; 
use Monogram\Helper;
use Monogram\CSV;
use Excel;
use ZipArchive;

class zulily extends StoreInterface 
{ 
		protected $dir = '/EDI/zulily/'; 
		
		public function importCsv($store, $file) {
			
      if ($file == null) {
        return;
      }
      
			$filename = 'import_' . date("Ymd_His", strtotime('now')) . '.' . $file->getClientOriginalExtension();
			$PO = str_replace(['-orders','.xlsx'], '', $file->getClientOriginalName());
      
			$saved = move_uploaded_file($file, storage_path() . $this->dir . $filename); 
			
			if (!$saved) {
				return false;
			}
			
      //assuming it will be excel
      $reader = Excel::load(storage_path() . $this->dir . $filename);
      $results = $reader->get()->toArray();
      
			$error = array();
			$order_ids = array();
			$id = '';
      
			set_time_limit(0);
			
			foreach ($results as $line)  {
				    
          // if (count($line) > 24 || count($line) < 20) {
          //   Log::error($line);
          //   return ['errors' => 'Incorrect number of fields: ' . count($line), 'order_ids' => $order_ids];
          // }
          
					Log::info('zulily import: Processing order ' . $line["order_id_long"]);
					
          $line["order_id_long"] = intval($line["order_id_long"]);
          $line["product_id"] = intval($line["product_id"]);
          
          if (is_float($line["ship_phone"])) {
            $line["ship_phone"] = intval($line["ship_phone"]);
          }
          
          
          if ($id == '' || $line["order_id_long"] != $id) {
            
  					$previous_order = Order::join('customers', 'orders.customer_id', '=', 'customers.id')
  														->where('orders.is_deleted', '0')
  														->where('orders.order_id', $PO . '-' . $line["order_id_long"])
  														->first();
  					
  					if ( $previous_order ) {
  						 Log::info('zulily : Order number already in Database ' . $line["order_id_long"]); 
  						 $error[] = 'Order number already in Database ' . $line["order_id_long"];
  						 continue;
  					}
            
            $order_5p = $this->insertOrder($line, $PO);
            $order_ids[] = $order_5p;
            $id = $line["order_id_long"];
          }
          
					$item_result = $this->insertItem($order_5p, $line, $PO);
          
          if ($item_result != 'inserted') {
            $error[] = $item_result;
          }
          
					$this->setOrderTotals($order_5p);
          
			}
			
      if (count($order_ids) == 0) {
        $error[] = 'No Orders Imported from File';
      }
      
			return ['errors' => $error, 'order_ids' => $order_ids];
		}
		
		private function insertOrder($data, $PO) { 
			
			// -------------- Customers table data insertion started ----------------------//
			$customer = new Customer();
			
			$customer->order_id = $PO . '-' . $data["order_id_long"];
			$customer->ship_full_name = $data["ship_first_name"] . ' ' . $data["ship_last_name"];
			$customer->ship_last_name = $data["ship_last_name"];
			$customer->ship_first_name = $data["ship_first_name"];
			$customer->ship_company_name = $data["ship_company_name"];
			$customer->ship_address_1 = $data["shipping_address_1"];
			$customer->ship_address_2 = $data["shipping_address_2"];
			$customer->ship_city = $data["ship_city"];
			$customer->ship_state = Helper::stateAbbreviation($data["ship_region"]);
			$customer->ship_zip = $data["ship_postal_code"];
			$customer->ship_country = 'US';
			$customer->ship_phone = $data["ship_phone"];
			
			$customer->bill_email = 'DROPSHIP@MONOGRAMONLINE.COM';
			$customer->bill_company_name = 'zulily';
			
			// -------------- Customers table data insertion ended ----------------------//
			// -------------- Orders table data insertion started ----------------------//
			$order = new Order();
			$order->order_id = $PO . '-' . $data["order_id_long"];
			$order->short_order = $data["order_id_long"];
      $order->purchase_order = $PO;
			$order->item_count = 1;
			$order->shipping_charge = '0';
			$order->tax_charge = '0';
			$order->total = 0; 
			$order->order_date = date("Y-m-d H:i:s");
			$order->store_id = 'zul-01'; 
			$order->store_name = 'zulily';
			$order->ship_state = $data["ship_region"];
      $order->carrier = 'UP';
			$order->method = 'S_GROUND';
			$order->order_status = 4;
      
			$customer->save();
			
			try {
				$order->customer_id = $customer->id;
			} catch ( \Exception $exception ) {
				Log::error('Failed to insert customer id in zulily');
			}
			
			$order->save();
			
			try {
				$order_5p = $order->id;
			} catch ( \Exception $exception ) {
				$order_5p = '0';
				Log::error('Failed to get 5p order id in zulily');
			}

			Order::note('Order imported from CSV', $order->id, $order->order_id);
			
			return $order->id;
		}
		
    private function insertItem($order_5p, $data, $PO) {
        
        $store_item = StoreItem::where('vendor_sku', $data["product_id"])->first();
        
        if (!$store_item) {
          $result = 'Order ' . $data["order_id_long"] . ' - Store SKU ' . $data["product_id"] . ' not found in Zulily Store Items';
          $product = Helper::findProduct($data["vendor_sku"]);
          
          $store_item = new StoreItem;
          
          $store_item->store_id = 'zul-01';
          $store_item->vendor_sku = $data["product_id"];
          // $store_item->upc = $data["upc"];
          
          if ($product) {
            $store_item->parent_sku = $product->product_model;
          } else {
            $store_item->parent_sku = $data["vendor_sku"];
          }
          
          $store_item->description = $data["product_name"];
          $store_item->child_sku = $data["vendor_sku"];
          
          $store_item->save();
          
        } else {
          $result = 'inserted';
          $product = Helper::findProduct($store_item->parent_sku);
          
          if (!$product) {
            Log::error('zulily: Store Item Product not found ' . $data["product_id"]);
            $product = Helper::findProduct($data["vendor_sku"]);
          }
        }
        
        if ($product) {
          $sku = $product->product_model;
          $item_id = $product->id_catalog;
          $thumb = $product->product_thumb;
          $url = $product->product_url;
        } else {
          Log::error('zulily: Product not found ' . $data["vendor_sku"]);
          $sku = trim($data["vendor_sku"]);
          $item_id = null;
          $thumb = null;
          $url = null;
        }
        
        $options = array();
        
        if (trim($data["size"]) != '' && trim($data["size"]) != 'One-Size') {
          $options['Size'] = $data["size"];
        }
        
        if (trim($data["color"]) != '') {
          $options['Color'] = $data["color"];
        }
        
        if (isset ($data["personalization_type_1"]) && trim($data["personalization_type_1"]) != '' && 
                  trim($data["personalization_1"]) != '') {
          $options['*' . $data["personalization_type_1"] . '*'] = $data["personalization_1"];
          $options['1'] = $data["personalization_1"];
          
        }
        
        if (isset ($data["personalization_type_2"]) && trim($data["personalization_type_2"]) != '' && 
              trim($data["personalization_2"]) != '') {
          $options['*' . $data["personalization_type_2"] . '*'] = $data["personalization_2"];
          $options['2'] = $data["personalization_2"];
        }
        
        if (trim($data["gift_message"]) != '') {
          $options['Gift Message'] = $data["gift_message"];
        }
        
        $item = new Item();
        $item->order_id = $PO . '-' . $data["order_id_long"];
        $item->store_id = 'zul-01'; 
        $item->item_description = $data["product_name"];
        $item->item_quantity = intval($data["qty_ordered"]);
        $item->data_parse_type = 'CSV';
        $item->item_code = $sku;
        $item->item_id = $item_id; 
        $item->item_thumb = $thumb;
        $item->item_url = $url;
        $item->item_option = json_encode($options);
        $item->edi_id = $data["product_id"];
        
        if ($store_item && $store_item->cost != null) {
          $item->item_unit_price = $store_item->cost; 
        } else {
          $item->item_unit_price = 0; 
        }
        
        if ($store_item) {
          $item->child_sku = $store_item->child_sku; 
        } else {
          $item->child_sku = trim($data["vendor_sku"]);
        }
        
        // if ($product) {
        //     $item->child_sku = Helper::getChildSku($item);
        // } else {
        //     $item->child_sku = $data["vendor_sku"];
        // }
        
        $item->order_5p = $order_5p;
        $item->save();
        
        return $result;
    }
    
    private function setOrderTotals ($order_5p) {
      if ($order_5p != null) {
        $order = Order::with('items')
                        ->where('id', $order_5p)
                        ->first();
        if (!$order) {
          Log::error('zulily setOrderTotals: order not found!');
          return;
        }
    		$order->item_count = $order->items->count();
        $order->total = $order->items->sum( function ($i) { return $i->item_quantity * $i->item_unit_price; });
        $order->save();
        return;
      }
    }
    
		public function orderConfirmation($store, $order) {
			
		} 
	
		public function shipmentNotification($store, $shipments) {
    
    }
    
    public function exportShipments($store, $shipments) {
      
      Log::info('zulily shipment csv started');
      
      $POs = array();
      
      foreach ($shipments as $shipment) {
        $POs[] = $shipment->order->purchase_order;
      }

      $files = array_flip(array_unique($POs));
      
       $header = array();
       
       $header[] = 'Order Id';
       $header[] = 'Product Id';
       $header[] = 'Vendor SKU';
       $header[] = 'Ship to Name';
       $header[] = 'Ship to Address';
       $header[] = 'Ship to City';
       $header[] = 'Ship to State';
       $header[] = 'Ship to Zip';
       $header[] = 'Personalization Name/Names';
       $header[] = 'Personalization Value/Values';
       $header[] = 'QTY';
       $header[] = 'Carrier';
       $header[] = 'Tracking Number';
       $header[] = 'Status';

      foreach ($shipments as $shipment) {
         
        foreach ($shipment->items as $item) {
          
          $line = array();
          
           $line[] = $shipment->order->short_order;
           $line[] = $item->edi_id;
           $line[] = $item->item_code;
           $line[] = $shipment->order->customer->ship_full_name;
           $line[] = $shipment->order->customer->ship_address_1;
           $line[] = $shipment->order->customer->ship_city;
           $line[] = $shipment->order->customer->ship_state;
           $line[] = $shipment->order->customer->ship_zip;
           $line[] = '';
           $line[] = '';
           $line[] = $item->item_quantity;
           $line[] = substr($shipment->mail_class, 0, strpos($shipment->mail_class, ' '));
           $line[] = $shipment->tracking_number;
           $line[] = 'Created';
           
           if (!is_array($files[$shipment->order->purchase_order])) {
             $files[$shipment->order->purchase_order] = array();
             $files[$shipment->order->purchase_order][] = $header;
           }
           
          $files[$shipment->order->purchase_order][] = $line;
        }
      }
      
      $zip = new ZipArchive;
      $zipfile = $store . '_SHIP_' . date('ymd_His') . '.zip';
      
      if ($zip->open(storage_path() . $this->dir . $zipfile, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
        
        foreach ($files as $PO => $lines) {
          if (count($lines) > 0) {
            $filename = $store . '_' . $PO . '_SHIP_' . date('ymd_His'); 
            $path = storage_path() . $this->dir; 
            
            // $lines = array_unshift($lines, $header);
            
            try {
              Excel::create($filename, function($excel) use ($lines) {
                  $excel->sheet('Sheet1', function($sheet) use ($lines) {
                      $sheet->fromArray($lines, null, 'A1', false, false);
                  });

              })->store('xlsx', $path);
              
              // copy($path . $filename . '.xlsx', storage_path() . '/EDI/download/' . $filename . '.xlsx');
            } catch (\Exception $e) {
              Log::error('Error Creating zulily XLS - ' . $e->getMessage());
            }
    			}
          
          if (!$zip->addFile($path . $filename . '.xlsx', basename($path . $filename . '.xlsx'))) {
              throw new Exception("file `{$filename}` could not be added to the zip file: " . $zip->getStatusString());
          } 
        }
      }
      
      Log::info('zulily shipment csv upload created');
      
      if ($zip->close()) { 
          return storage_path() . $this->dir . $zipfile;
      } else {
          throw new Exception("could not close zip file: " . $zip->getStatusString());
      }

		}
		
		public function getInput($store) {
			
		} 
		 
		public function backorderNotification($store, $item) {
			
		}
		
		public function shipLabel($store, $unique_order_id, $order_id) {
			
		}
				 
}
