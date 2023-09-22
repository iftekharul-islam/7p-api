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
use Monogram\SFTPConnection;
use ZipArchive;

class DevTest extends StoreInterface
{ 
		protected $dir = ['kohls-01' => '/EDI/Kohls/']; 
		protected $download_dir = '/EDI/download/';
    
    public function getInput($store, $files = null) {
      
      if ($files == null) {
        
        Log::info('DSCO: contacting via SFTP ' . $store);
      
        try { 
            $sftp = new SFTPConnection('sftp.dsco.io');
            // $sftp = new SFTPConnection('52.2.163.74'); 
            $sftp->login('account1000007357', '520155');
        } catch (\Exception $e) { 
            Log::error('DSCO getInput: SFTP connection error ' . $e->getMessage()); 
            return FALSE; 
        } 
        
        try { 
              $files = $sftp->downloadFiles('out/', $this->dir[$store] . 'in/', null, 'archive');
        } catch (\Exception $e) { 
            Log::error('DSCO getInput: SFTP download error ' . $e->getMessage()); 
            return FALSE; 
        } 
      }
      
      if ($files == null || count($files) == 0) {
        return;
      }
      
      foreach ($files as $file) {
        if (substr($file, -3) == 'csv') {
          $errors = $this->processFile($store, $file);
        } else if (substr($file, -3) == 'pdf') {
          copy(storage_path() . $this->dir[$store] . '/in/' . $file, storage_path() . $this->download_dir . $file);
        }
      }
    } 
    
		public function importCsv($store, $file) {
			
			$filename = 'import_' . date("ymd_His", strtotime('now')) . '.csv';
			
			$saved = move_uploaded_file($file, storage_path() . $this->dir[$store->store_id] . 'in/' .$filename); 
			
			if (!$saved) {
				return false;
			}
			
      return $this->processFile($store, $filename);
			
		}
		
    public function processFile ($store, $filename) {
      
      if (!$store instanceof Store) {
        $store = Store::where('store_id', $store)->first();
      }
      
      $csv = new CSV;
      // $data = $csv->intoArray(storage_path() . $this->dir[$store->store_id] . $filename, ",");
      $data = $csv->intoArray(storage_path() . $this->dir[$store->store_id] . 'in/' . $filename, ",");
      
      $error = array();
      $order_ids = array();
      
      set_time_limit(0);
      
      $po = '';
      $total = 0;
      $count = 0;
      $order_5p = null;


//      foreach ($data as $_ => $inside) {
//          if(isset($inside[16])) {
//              unset($inside[16]);
//          }
//      }

//        foreach ($data as $_ => $inside) {
//            $data[$_] = array_values($inside);
//        }
//        dd($data);

      foreach ($data as $line)  {
        
        if ($line[0] != 'po_number') {

            if (count($line) != 223) {
//          if (count($line) != 143) {
             $error[] = 'Incorrect number of fields in file: ' . count($line);
             break;
          }
          
          if ($line[0] != $po) {
            
            if ($po != '') {
              $this->setOrderTotals($order_5p, $total);
            }
            
            $po = $line[0];
            $total = 0;
            
            Log::info('DSCO import: Processing order ' . $line[4]);
            
            
            $previous_order = Order::where('orders.is_deleted', '0')
                              ->where('orders.order_id', $line[0])
                              ->first();
            
            if ( !$previous_order ) {
              $order_5p = $this->insertOrder($line, $store);
              $order_ids[] = $order_5p;
              Log::info('DSCO import: order ' . $line[0] . ' processed');
            } else {
              $order_5p = $previous_order->id;
              Log::info('DSCO : Order number already in Database ' . $line[0]); 
              $error[] = 'Order number already in Database ' . $line[0];
            }
          }
          
          $id = $line[1] . ':' . $line[2] . ':' . $line[4];
          
          $previous_item = Item::where('order_5p', $order_5p)
                                ->where('edi_id', $id)
                                ->where('is_deleted', '0')
                                ->first();
          
          if (!$previous_item) {
            $this->insertItem($line, $store, $order_5p);
            $total += $line[11] * $line[15];
            $this->setOrderTotals($order_5p, $total);
          }
        }
      }
      return ['errors' => $error, 'order_ids' => $order_ids];
    }
    
		private function insertOrder($data, $store) { 


			// -------------- Customers table data insertion started ----------------------//
			$customer = new Customer();
			
			$customer->order_id = $data[0];
			$customer->ship_full_name = $data[85] . ' ' . $data[86];
      $customer->ship_last_name = $data[86];
      $customer->ship_first_name = $data[85];
			$customer->ship_company_name = isset($data[87]) ? $data[87] : null;
      $customer->ship_address_1 = isset($data[88]) ? $data[88] : null;
      $customer->ship_address_2 = isset($data[89]) ? $data[89] : null;
      $customer->ship_city = $data[92];
			$customer->ship_state = Helper::stateAbbreviation($data[93]);
			$customer->ship_zip = $data[94];
			$customer->ship_country = isset($data[95]) ? $data[95] : null;
			$customer->ship_phone = isset($data[96]) ? $data[96] : null;
			
      $customer->bill_email = isset($data[97]) ? $data[97] : null;
			$customer->bill_company_name = $store->store_name;
      $customer->ignore_validation = TRUE;
      
			// -------------- Customers table data insertion ended ----------------------//
			// -------------- Orders table data insertion started ----------------------//
			$order = new Order();
			$order->order_id = $data[0];
			$order->short_order = $data[0];
			$order->shipping_charge = '0';
			$order->tax_charge = '0'; 
			$order->order_date = date("Y-m-d H:i:s", strtotime($data[107]));
			$order->store_id = $store->store_id; 
			$order->store_name = '';
			$order->order_status = 4;
//			$order->order_comments = $data[88];
			$order->order_comments = "";
			$order->ship_state = Helper::stateAbbreviation($data[93]);
      
      $shipinfo = $this->lookup[$data[101]];
      if (isset($shipinfo[0])) {
        $order->carrier = $shipinfo[0];
    		$order->method = $shipinfo[1];
      } else {
        $order->carrier ='UP';
    		$order->method = 'S_GROUND';
        Log::error('DSCO : ship method not found ' . $data[101]);
      }
			
			// -------------- Orders table data insertion ended ----------------------//
				
				$customer->save();
				
				try {
					$order->customer_id = $customer->id;
				} catch ( \Exception $exception ) {
					Log::error('Failed to insert customer id in DSCO');
				}
				
				$order->save();
				
				try {
					$order_5p = $order->id;
				} catch ( \Exception $exception ) {
					$order_5p = '0';
					Log::error('Failed to get 5p order id in DSCO');
				}

				Order::note('Order imported from CSV', $order->id, $order->order_id);
				
				return $order->id;
				
		}
    
    private function setOrderTotals ($order_5p, $total) {
      if ($order_5p != null) {
        $order = Order::with('items')
                        ->where('id', $order_5p)
                        ->first();
        if (!$order) {
          Log::error('Kohls order not found!');
          return;
        }
    		$order->item_count = $order->items->count();
        $order->total = sprintf("%01.2f", $total);
        $order->save();
        return;
      }
    }
    
    private function insertItem ($data, $store, $order_5p) {
      
      $product = Helper::findProduct($data[3]);
      
      $item = new Item();
      
      if ($product != false) {
        $item->item_code = $product->product_model;
        $item->item_id = $product->id_catalog;
        $item->item_thumb = $product->product_thumb;
        $item->item_url = $product->product_url;
      } else {
        $item->item_code = $data[3];
        Log::error('DSCO Product not found ' . $data[3] . ' in order ' . $data[0]);
      }
      
      $item->order_id = $data[0];
      $item->store_id = $store->store_id; 
      $item->item_description = $data[12];
      $item->item_quantity = $data[11];
      $item->item_unit_price = $data[15];
      $item->data_parse_type = 'CSV';
      $item->child_sku = $data[3];
      $item->item_option = json_encode(array('Color' => $data[13], 'Size' => $data[14]));
      $item->edi_id = $data[1] . ':' . $data[2] . ':' . $data[4];
      $item->order_5p = $order_5p;
      $item->save();
    }
    
		public function orderConfirmation($store, $order) {
			
		} 
	
		public function shipmentNotification($store, $shipments) {
      $this->createShipmentCsv($store, $shipments, '/upload/');
      $this->upload($store);
			return;
		}
		
    public function exportShipments($store, $shipments) {
      $files = $this->createShipmentCsv($store, $shipments, '/out/');
      Log::info($files);
      $zip = new ZipArchive;
      $zipfile = $store . '_SHIP_' . date('ymd_His') . '.zip';
      Log::info($zipfile);
      if ($zip->open(storage_path() . $this->dir[$store] . 'zipfiles/' . $zipfile, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
        foreach($files as $file) {
          Log::info($file);
          if (!$zip->addFile($file, basename($file))) {
              throw new Exception("file `{$file}` could not be added to the zip file: " . $zip->getStatusString());
          } 
        }
      }
      Log::info('out');
      if ($zip->close()) { 
          return storage_path() . $this->dir[$store] . 'zipfiles/' . $zipfile;
      } else {
          throw new Exception("could not close zip file: " . $zip->getStatusString());
      }
    
    }
    
    private function createShipmentCsv($store, $shipments, $path) {
      Log::info('DevTest shipment csv started');
      
      $files = array();
  
      $lines = array();
      
      $line = array();
      
      $line[] = 'po_number';
      $line[] = 'package_tracking_number';
      $line[] = 'package_ship_carrier';
      $line[] = 'package_ship_method';
      $line[] = 'line_item_line_number';
      $line[] = 'line_item_sku';
      $line[] = 'line_item_upc';
      $line[] = 'line_item_quantity';
      
      $lines[] = $line;

 			foreach ($shipments as $shipment) {

 				foreach ($shipment->items as $item) {
           
           $data = explode(':', $item->edi_id);
           
           if ($shipment->order->method == null) {
               $carrier = 'UPS';
               $method = 'MAIL INNOVATIONS';
           } else if ($shipment->order->carrier == 'FX') {
               $carrier = 'FedEx';
               $method = substr($shipment->order->method, 1); 
           } else {
               $carrier = 'UPS';
               $method = substr($shipment->order->method, 2); 
           }
           
           $line = array();
      
           $line[] = $shipment->order->short_order;
           $line[] = $shipment->tracking_number;
           $line[] = $carrier;
           $line[] = $method;
           $line[] = $data[1];
           $line[] = $item->child_sku;
           $line[] = $data[2];
           $line[] = $item->item_quantity;
      
           $lines[] = $line;

 				}
 			}
      
 			if (count($lines) > 0) {
 				$filename = 'Order_Shipment_' . $store . '_' . date('ymd_His') . '.csv'; 
         try {
   				$csv = new CSV;
   				$files[] = $csv->createFile($lines, storage_path() . $this->dir[$store] . $path, null, $filename, ',');
         } catch (\Exception $e) {
           Log::error('Error Creating DevTest CSV - ' . $e->getMessage());
           return;
         }
 			}
     
       Log::info('DSCO invoice csv started');
       
       $lines = array();
       
       $line = array();
       
       $line[] = 'invoice_id';
       $line[] = 'po_number';
       $line[] = 'invoice_total_amount';
       $line[] = 'line_item_line_number';
       $line[] = 'line_item_sku';
       $line[] = 'line_item_upc'; 
       $line[] = 'line_item_title';
       $line[] = 'line_item_unit_price';
       $line[] = 'line_item_quantity';
       $line[] = 'line_item_ship_date';
       $line[] = 'line_item_tracking_number';
       $line[] = 'line_item_ship_carrier';
       $line[] = 'line_item_ship_method';
       $line[] = 'line_item_shipping_service_level_code';
       
       $lines[] = $line;
       
       foreach ($shipments as $shipment) {
         
         $total = 0;
         
         foreach ($shipment->items as $item) {
           $total +=  ($item->item_quantity * $item->item_unit_price);
         }
         
         foreach ($shipment->items as $item) {
       
           $data = explode(':', $item->edi_id);
           
           if ($shipment->order->method != null) {
             $method = substr($shipment->order->method, 2);
             $service_code = array_search([$shipment->order->carrier, $shipment->order->method], $this->lookup); 
           } else {
             $method = 'MAIL INNOVATIONS';
             $service_code = 'GND';
           }
           
           $line = array();
       
           $line[] = $shipment->unique_order_id;
           $line[] = $shipment->order->short_order;
           $line[] = sprintf("%01.2f", $total);
           $line[] = $data[1];
           $line[] = $item->child_sku;
           $line[] = $data[2];
           $line[] = $item->item_description;
           $line[] = $item->item_unit_price;
           $line[] = $item->item_quantity;
           $line[] = date("Y-m-dTH:i:s+5:00", strtotime($shipment->transaction_datetime));
           $line[] = $shipment->shipping_id;
           $line[] = 'UPS';
           $line[] = $method; 
           $line[] = $service_code;
           
           $lines[] = $line;
         }
       }
       
       if (count($lines) > 0) {
         $filename = 'Invoice_' . $store . '_' . date('ymd_His') . '.csv'; 
         try {
           $csv = new CSV;
           $files[] = $csv->createFile($lines, storage_path() . $this->dir[$store] . $path, null, $filename, ',');
         } catch (\Exception $e) {
           Log::error('Error Creating DSCO Invoice CSV - ' . $e->getMessage());
           return;
         }
       }
       
       Log::info('DSCO invoice csv upload created');
       
       return $files;
    }
    
    public function upload ($store_id) 
    {   
        
        try { 
            $sftp = new SFTPConnection('ftp.dsco.io');
            // $sftp = new SFTPConnection('ftp.dsco.io', 22 , array('hostkey'=>'ssh-rsa', 'kex' => 'diffie-hellman-group14-sha1'));
            $sftp->login('account1000007357', '520155');
        } catch (\Exception $e) { 
            Log::error('DSCO upload: SFTP connection error ' . $e->getMessage()); 
            return; 
        } 
        
        try {
            $file_list = $sftp->uploadDirectory('in/', $this->dir[$store_id] . '/upload/');
        } catch (\Exception $e) { 
            Log::error('DSCO upload: SFTP upload error ' . $e->getMessage()); 
            return; 
        } 
        
        //move files to out directory
        foreach ($file_list as $file) {
          try {
            
            rename(storage_path() . $this->dir[$store_id] . '/upload/' . $file, storage_path() . $this->dir[$store_id] . '/out/' . $file);
            
          } catch (\Exception $e) {
            Log::error('DSCO upload: File rename Error ' . $e->getMessage()); 
          }
        }
        
        return;
    }
    
		public function backorderNotification($store, $item) {
			
		}
		
		public function shipLabel($store, $unique_order_id, $order_id) {
			
		}
    
    private $lookup = array(        'upcg'  => ['UP', 'S_GROUND'],
                                    'u3ds'  => ['UP', 'S_3DAYSELECT'],
                                    'u2da'  => ['UP', 'S_AIR_2DAY'],
                                    'u2aa'  => ['UP', 'S_AIR_2DAYAM'],
                                    'UPSS'  => ['UP', 'S_GROUND'],
                                    'UPSP'  => ['UP', 'S_SUREPOST'],
                                    'USPL'  => ['UP', 'S_SUREPOST'],
                                    'GCG'   => ['UP', 'S_GROUND'],
                                    'GND'   => ['UP', 'S_AIR_1DAY'],
                                    'GSE'   => ['UP', 'S_AIR_2DAY'],
                                    'UNAS'  => ['UP', 'S_AIR_1DAYSAVER'],
                                    'FESP'  => ['FX', '_SMART_POST'],
                                    'FEHD'  => ['FX', '_GROUND_HOME_DELIVERY'],
                                    'FECG'  => ['FX', '_FEDEX_GROUND'],
                                    'FEES'  => ['FX', '_FEDEX_EXPRESS_SAVER'],
                                    'FE2D'  => ['FX', '_FEDEX_2_DAY'],
                                    'F2DA'  => ['FX', '_FEDEX_2_DAY_AM'],
                                    'FESO'  => ['FX', '_STANDARD_OVERNIGHT'],
                                    'FEPO'  => ['FX', '_PRIORITY_OVERNIGHT'],
                                    'FEFO'  => ['FX', '_FIRST_OVERNIGHT'],
                                ); 
}
