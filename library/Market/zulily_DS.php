<?php 
 
namespace Market; 

use App\Order;
use App\Customer;
use App\Item;
use App\Product;
use App\StoreItem;
use Illuminate\Support\Facades\Log; 
use Monogram\Helper;
use Ship\Shipper;
use Monogram\X12_EDI;
use Excel;
use ZipArchive;

class zulily_DS extends StoreInterface 
{ 
		protected $dir = '/EDI/zulily/'; 
		
    protected $download_dir = '/EDI/GXS/download/'; 
    protected $upload_dir = '/EDI/GXS/upload/'; 

    protected $setup = ['zul-01'    => [  'name' => 'MonogramonlineInc.',
                                          'ISA' => '856320321',
                                          'ISA_TYPE' => '12',
                                          'id' => '70448',
                                        ]
                        ];
                        
    public function getInput($store) {
      
      Log::info('Looking for zulily_DS files');
      
      $file_list =  array_diff(scandir(storage_path() . $this->download_dir), array('..', '.')); 
      
      foreach ($file_list as $file) { 
          
        $info = explode('%', $file);
          
        if ($info[1] == 'ZULILYDS:ZZ') {
          
          if ($this->setup[$store]['ISA'] != substr($info[2], 0, strpos($info[2], ':'))) {
            Log::error('Zulily DS: ISA not found - ' . $file);
            continue;
          }
          
          Log::info('Zulily DS: ' . $file . ' being processed');
          
          try {
            rename(storage_path() . $this->download_dir . $file, storage_path() . $this->dir . $store . '/in/' . $file);
          } catch (\Exception $e) {
            Log::error('Zulily DS: Error moving file ' . $file . ' - ' . $e->getMessage());
            continue;
          }
          
          try { 
            
            $purchase_orders = X12_EDI::parse(storage_path() . $this->dir . $store . '/in/' . $file, 'newline', '*', '~');
            
            $order_id = null;
            
            foreach($purchase_orders as $purchase_order) {
              
              if (isset($purchase_order['ISA01'])) {
                
                continue;
                
              } else if (isset($purchase_order["BEG03"])) {
                
                $previous_order = Order::where('order_id', $purchase_order ["RQ-REF02"] . '-' . $purchase_order["BEG03"])
                                  ->where('is_deleted', '0')
                                  ->first();
                
                if ( $previous_order ) {
                 Log::error('Zulily DS : Order number already in Database ' . $purchase_order ["RQ-REF02"] . '-' . $purchase_order["BEG03"]); 
                 continue;
                } 
                
                $order_id = $this->insert_order($store, $purchase_order);
              }
            }
           
           } catch (\Exception $e) { 
               Log::error('Zulily DS getFiles: error processing file ' . $file . ' - ' . $e->getMessage()); 
           } 
         }
       }           

      return true;
		} 
    
    private function insert_order($store_id, $data) {
      
      // -------------- Customers table data insertion started ----------------------//
      $customer = new Customer();
      
      $customer->order_id = $data["RQ-REF02"] . '-' . $data["BEG03"];
      $customer->ship_full_name = $data["ST-N102"];
      $customer->ship_last_name = (strpos($data["ST-N102"], ' ') === false) ? '' : preg_replace('#.*\s([\w-]*)$#', '$1', $data["ST-N102"]);
      $customer->ship_first_name = ($customer->ship_last_name !== $data["ST-N102"]) ?  
                                      trim( preg_replace('#'.$customer->ship_last_name.'#', '', $data["ST-N102"] ) ) : '';
      $customer->ship_company_name = $data["ST-N201"] ?? null;
      $customer->ship_address_1 = $data["ST-N301"];
      $customer->ship_address_2 = $data["ST-N302"] ?? null;
      $customer->ship_city = $data["ST-N401"];
      $customer->ship_state = Helper::stateAbbreviation($data["ST-N402"]);
      $customer->ship_zip = $data["ST-N403"];
      $customer->ship_country = $data["ST-N404"] ?? 'US';
      if (isset($data["ZZ-PER04"])) {
        $customer->ship_phone = $data["ZZ-PER04"];
      }
      
      // $customer->bill_email = '';
      $customer->bill_company_name = $data["BT-N102"];
      // $customer->bill_address_1 = $data[53];
      // $customer->bill_address_2 = isset($data[54]) ? $data[54] : null;
      // $customer->bill_city = $data[55];
      // $customer->bill_state = Helper::stateAbbreviation($data[56]);
      // $customer->bill_zip = $data[57];
      // $customer->bill_country = $data[58];
      // $customer->bill_phone = $data[49];
      
      // -------------- Customers table data insertion ended ----------------------//
      // -------------- Orders table data insertion started ----------------------//
      $order = new Order();
      $order->order_id = $data ["RQ-REF02"] . '-' . $data["BEG03"];
      $order->short_order = $data["BEG03"];
      $order->purchase_order = $data["RQ-REF02"];
      $order->item_count = $data["CTT01"];
      $order->shipping_charge = '0';
      $order->tax_charge = '0';
      // $order->total = $data["AMT02"]; 
      $order->order_date = substr($data["BEG05"], 0, 4) . '-' . substr($data["BEG05"], 4, 2) . '-' . substr($data["BEG05"], 6, 2) . date(" H:i:s");
      $order->store_id = $store_id; 
      $order->store_name = 'Zulily';
      $order->order_status = 4;      
      $order->ship_state = $data["ST-N402"];
      // $order->ship_date = substr($data["*VR-DTM02"], 0, 4) . '-' . substr($data["*VR-DTM02"], 4, 2) . '-' . substr($data["*VR-DTM02"], 6, 2);
      $order->carrier = 'UP';
      $order->method = 'S_GROUND'; 
      
      $customer->save();
      
      try {
        $order->customer_id = $customer->id;
      } catch ( \Exception $exception ) {
        Log::error('Failed to insert customer id in Zulily DS');
      }
      
      $order->save();
      
      try {
        $order_5p = $order->id;
      } catch ( \Exception $exception ) {
        $order_5p = '0';
        Log::error('Failed to get 5p order id in Zullily DS');
      }
      
      // -------------- Orders table data insertion ended ----------------------//
      // -------------- Items table data insertion started ------------------------//
      
      $ctr = '01';
      $total = 0;
      
      while (isset($data['PO1' . $ctr . '-01'])) {
              
        $item = new Item();
        $item->order_id = $order->order_id;
        $item->store_id = $store_id; 
        $item->data_parse_type = 'EDI';
        $item->item_quantity = $data['PO1' . $ctr . '-02'];
        $item->item_unit_price = $data['PO1' . $ctr . '-04']; 
        $item->edi_id = $data['PO1' . $ctr . '-01'] . '*' . $data['PO1' . $ctr . '-07'] . '*' . 
                        $data['PO1' . $ctr . '-09'];
        
        $item->item_option = '{}';
        
        $prefix = '';
        $result = array();
        
        while (isset($data[$prefix . 'MSG' . $ctr . '-01'])) {
          $ex = explode('=', $data[$prefix . 'MSG' . $ctr . '-01']);
          $result['*' . $ex[0] . '*'] = $ex[1] ?? '';
          if (isset($ex[1])) {
            $result[strval(strlen($prefix) + 1)] = $ex[1];
          }
          $prefix = '*' . $prefix;
        }
        
        if (count($result) > 0) {
          $item->item_option = json_encode($result);
        }
        
        $product = Helper::findProduct($data['PO1' . $ctr . '-09']);
        
        if ( $product ) {
          
          $item->item_code = $product->product_model;
          $item->item_description = $product->product_name;
          $item->item_id = $product->id_catalog;
          $item->item_thumb = $product->product_thumb;
          $item->item_url = $product->product_url;
          $item->child_sku = Helper::getchildsku($item, $data['PO1' . $ctr . '-09']);
          
        } else {
          
          $item->item_code = $data['PO1' . $ctr . '-09'];
          $item->item_description = 'Product not found';
          $item->child_sku = $data['PO1' . $ctr . '-09'];
          
        }
        
        $item->save();
        
        $ctr = sprintf('%02d', intval($ctr) + 1);
        
        $item->order_5p = $order->id;
        $item->save();
        
        $total = $total + ($item->item_unit_price * $item->item_quantity);
      }
      
      $order->total = sprintf("%01.2f", $total);
      $order->save();
      
      return $order->id;
        
    }
    
		public function orderConfirmation($store, $order) {
			
		}
	
		public function shipmentNotification($store, $shipments) {
      
      Log::info('Zulily DS shipment notification');
      
      $date = date("ymd");
              
      foreach ($shipments as $shipment) {
        
        if (substr($shipment->order->short_order, 0, 2) == 'WH') {
          continue;
        }
        
        $time = date("Hi");
        $control_num = sprintf('%09d', rand(1000,10000));
        $group_num = sprintf('%09d', rand(1000,10000));
        $customer = $shipment->order->customer;
        
        $datetime_unique =  date("Ymd_His") . rand(10,99);
        
        $ISA = $this->setup[$store]['ISA_TYPE'] . '*' . str_pad($this->setup[$store]['ISA'], 15, ' ', STR_PAD_RIGHT);
        
        if ($shipment->actual_weight > 0) {
          $weight = $shipment->actual_weight;
        } else {
          $weight = 1;
        }
        
        $notice = "ISA*00*          *00*          *$ISA*ZZ*ZULILYDS       *$date*$time*U*00401*$control_num*0*P*>~\n" .
                  'GS*SH*' . $this->setup[$store]['ISA'] . "*ZULILYDS*20$date*$time*$group_num*X*004010~\n" .
                  "ST*856*" . sprintf('%09d', str_replace('-', '', $shipment->unique_order_id)) . "~\n" .
                  'BSN*00*' . str_replace('-', '', $shipment->unique_order_id) . "*20$date*$time*0001~\n" .
                  "HL*1**S~\n" .
                  'TD1*CTN25*' . $shipment->package_count . 
                  '****G*' . $weight . "*LB~\n" .
                  'TD5**2*UPSN' . "~\n" .
                  'REF*CN*' . $shipment->shipping_id . "~\n" .
                  'REF*RQ*' . $shipment->order->purchase_order . "~\n" .
                  "DTM*011*20$date~\n" .
                  'N1*SH*' . $this->setup[$store]['name'] . '*92*' . $this->setup[$store]['id'] . "~\n" .
                  // "N3*575 UNDERHILL BLVD~\n" .
                  // "N4*SYOSSET*NY*11791*US~\n" .
                  'N1*ST*' . $customer->ship_full_name . "~\n" .
                  'N3*' . $customer->ship_address_1;
                  
          if ($customer->ship_address_2 && $customer->ship_address_2 != '') {
            $notice .= '*' . $customer->ship_address_2;
          } 
          
          $notice .= "~\n" . 'N4*' .  $customer->ship_city . '*' . $customer->ship_state . '*' . 
                  $customer->ship_zip . '*' . Shipper::getcountrycode($customer->ship_country) . "~\n" .
                  "HL*2*1*O~\n" .
                  'PRF*' . $shipment->order->short_order . "~\n" .
                  "HL*3*2*P~\n" .
                  'MAN*GM*' . $shipment->shipping_id . "~\n";
                  
          $counter = 3;
          
          foreach ($shipment->items as $item) {
            
            $ex = explode('*', $item->edi_id);
            
            if (!isset($ex[2])) {
              Log::error('Zulily FC: Error retrieving UPC for item ' . $item->id);
              continue;
            }
            
            $notice .=  'HL*' . ++$counter . "*3*I~\n" .
                        'LIN*' . intval(trim($ex[0])) . '*UP*' . trim($ex[1]) . '*SK*' . trim($ex[2]) . "~\n" .
                        'SN1**' . $item->item_quantity . "*EA~\n";
          }
          
          $notice .=  'CTT*' . $counter . '*' . count($shipment->items) . "~\n" .
                      // 'CTT*' . count($shipment->items) . "~\n" .
                      'SE*' . intval(((count($shipment->items)) * 3) + 18) . '*' . 
                              sprintf('%09d', str_replace('-', '', $shipment->unique_order_id)) . "~\n" .
                      "GE*1*$group_num~\n" .
                      "IEA*1*$control_num~\n\n";
        
        $filename = $shipment->order->order_id . '_856_' . $datetime_unique . '.edi';
        $file = fopen(storage_path() . $this->upload_dir . $filename, "w");
        fwrite($file, $notice);
        fclose($file);
        
        copy(storage_path() . $this->upload_dir . $filename, storage_path() . $this->dir . $store . '/out/' . $filename);
        
        $time = date("Hi");
        $control_num = sprintf('%09d', rand(1000,10000));
        $group_num = sprintf('%09d', rand(1000,10000));
        
        $invoice ="ISA*00*          *00*          *$ISA*ZZ*ZULILYDS       *$date*$time*U*00401*$control_num*0*P*>~\n" .
                  'GS*IN*' . $this->setup[$store]['ISA'] . "*ZULILYDS*20$date*$time*$group_num*X*004010~\n" .
                  "ST*810*" . sprintf('%09d', str_replace('-', '', $shipment->unique_order_id)) . "~\n" .
                  'BIG*20' . $date . '*' . $shipment->unique_order_id . '*' .
                        substr(str_replace('-', '', $shipment->order->order_date),0,8) . '*' . 
                        $shipment->order->short_order . "~\n" .
                  "CUR*SE*USD~\n" .
                  'REF*RQ*' . $shipment->order->purchase_order . "~\n" .
                  'N1*BT*' . $customer->bill_company_name . "~\n" .
                  'N1*ST*' . $customer->ship_full_name . "~\n" .
                  'N3*' . $customer->ship_address_1;
                  
        if ($customer->ship_address_2 && $customer->ship_address_2 != '') {
          $invoice .= '*' . $customer->ship_address_2;
        } 
        
        $invoice .= "~\n" . 'N4*' .  $customer->ship_city . '*' . $customer->ship_state . '*' . 
                            $customer->ship_zip . '*' . Shipper::getcountrycode($customer->ship_country) . "~\n";
                  
        $total = 0;
        $qty_sum = 0;
        
        foreach ($shipment->items as $item) {
          $ex = explode('*', $item->edi_id);
          
          if (!isset($ex[2])) {
            Log::error('Zulily FC: Error retrieving UPC for item 810 ' . $item->id);
            continue;
          }
          
          $invoice .=  'IT1*' . trim($ex[0]) . '*' . $item->item_quantity . '*EA*' . $item->item_unit_price . '**UP*' . trim($ex[1]) . "~\n";
          $total += ($item->item_quantity * $item->item_unit_price);
          $qty_sum += $item->item_quantity;
        }
                  
        $invoice .= 'TDS*' . intval($total * 100) . "~\n" .
                    'CTT*' . count($shipment->items) . '*' . $qty_sum . "~\n" .
                    'SE*' . (((count($shipment->items)) * 2) + 10) . '*' . 
                            sprintf('%09d', str_replace('-', '', $shipment->unique_order_id)) . "~\n" .
                    "GE*1*$group_num~\n" .
                    "IEA*1*$control_num~\n\n";
        
        $filename = $shipment->order->order_id . '_810_' . $datetime_unique . '.edi';
        $file = fopen(storage_path() . $this->upload_dir . $filename, "w");
        fwrite($file, $invoice);
        fclose($file);
        
        copy(storage_path() . $this->upload_dir . $filename, storage_path() . $this->dir . $store . '/out/' . $filename);
      }
      
      return;
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
          
          $ex = explode('*', $item->edi_id);
          
          if (count($ex) == 1) {
            $r = $ex[0];
          } else {
            $zul_product = StoreItem::where('store_id', $store)
                                    ->where('upc', $ex[1])
                                    ->first();
            if ($zul_product) {
              $r = $zul_product->vendor_sku;
            } else {
              Log::error('ZULILY DS Export shipments : Could not add order - ' . $shipment->order->order_id);
            }
          }
          
          $line = array();
          
           $line[] = $shipment->order->short_order;
           $line[] = isset($r) ? trim($r) : '';
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
		
		public function importCsv($store, $file) {
			
		} 
		 
		public function backorderNotification($store, $item) {
			
		}
		
		public function shipLabel($store, $unique_order_id, $order_id) {
			
		}
				 
}
