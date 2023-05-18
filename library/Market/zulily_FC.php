<?php 
 
namespace Market; 

use App\Order;
use App\Customer;
use App\Item;
use App\Product;
use App\Ship;
use Illuminate\Support\Facades\Log; 
use Monogram\Helper;
use Ship\Shipper;
use Monogram\X12_EDI;

class zulily_FC extends StoreInterface 
{ 
    protected $dir = '/EDI/zulily/'; 
    
    protected $download_dir = '/EDI/GXS/download/'; 
    protected $upload_dir = '/EDI/GXS/upload/'; 
    
    protected $setup = [ 'zul-02' => [  'name' => 'Natico Originals',
                                          'ISA' => '068229863',
                                          'ISA_TYPE' => '01',
                                          'id' => '66144'
                                      ]
                                          
                        ];
                        
    public function getInput($store) {
      
      Log::info('Looking for zulily_FC files');
      
      $file_list =  array_diff(scandir(storage_path() . $this->download_dir), array('..', '.')); 
      
      foreach ($file_list as $file) { 
          
        $info = explode('%', $file);
          
        if ($info[1] == 'ZVDXPROD:ZZ') {
          
          if ($this->setup[$store]['ISA'] != substr($info[2], 0, strpos($info[2], ':'))) {
            Log::error('Zulily FC: ISA not found - ' . $file);
            continue;
          }
          
          Log::info('Zulily FC: ' . $file . ' being processed');
          
          try {
            rename(storage_path() . $this->download_dir . $file, storage_path() . $this->dir . $store . '/in/' . $file);
          } catch (\Exception $e) {
            Log::error('Zulily DS: Error moving file ' . $file . ' - ' . $e->getMessage());
            continue;
          }
          
          try { 
            
            $purchase_orders = X12_EDI::parse(storage_path() . $this->dir . $store . '/in/' . $file, 'newline', '*', '~');
            
            foreach($purchase_orders as $purchase_order) {
              
              if (isset($purchase_order['ISA01'])) {
                
                continue;

              } else if (isset($purchase_order["BEG03"])) {
                
                $previous_order = Order::where('order_id', $purchase_order["BEG03"])
                                  ->where('is_deleted', '0')
                                  ->first();
                
                if ( $previous_order ) {
                 Log::error('Zulily FC : Order number already in Database ' . $purchase_order["BEG03"]); 
                 continue;
                } 
                
                $this->insert_order($store, $purchase_order);
              }
            }
           
           } catch (\Exception $e) { 
               Log::error('Zulily FC getFiles: error processing file ' . $file . ' - ' . $e->getMessage()); 
           } 
         }
       }           

      return true;
    } 
    
    private function insert_order($store_id, $data) {
      
      // -------------- Customers table data insertion started ----------------------//
      $customer = new Customer();
      
      $customer->order_id = $data["BEG03"];
      $customer->ship_full_name = $data["ST-N102"];
      $customer->ship_last_name = (strpos($data["ST-N102"], ' ') === false) ? '' : preg_replace('#.*\s([\w-]*)$#', '$1', $data["ST-N102"]);
      $customer->ship_first_name = trim( preg_replace('#'.$customer->ship_last_name.'#', '', $data["ST-N102"] ) );
      $customer->ship_company_name = $data["ST-N201"] ?? null;
      $customer->ship_address_1 = $data["ST-N301"];
      $customer->ship_address_2 = $data["ST-N302"] ?? null;
      $customer->ship_city = $data["ST-N401"];
      $customer->ship_state = Helper::stateAbbreviation($data["ST-N402"]);
      $customer->ship_zip = $data["ST-N403"];
      $customer->ship_country = $data["ST-N404"] ?? 'US';
      $customer->ship_phone = '8563203210';
      
      // $customer->bill_email = '';
      $customer->bill_company_name = 'Zulily*' . $data["ST-N104"];
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
      $order->order_id = $data["BEG03"];
      $order->short_order = $data["BEG03"];
      $order->item_count = $data["CTT01"];
      $order->shipping_charge = '0';
      $order->tax_charge = '0';
      // $order->total = $data["AMT02"]; 
      $order->order_date = substr($data["BEG05"], 0, 4) . '-' . substr($data["BEG05"], 4, 2) . '-' . substr($data["BEG05"], 6, 2) . date(" H:i:s");
      $order->store_id = $store_id; 
      $order->store_name = 'Zulily';
      $order->order_status = 4;      
      $order->ship_state = $data["ST-N402"];
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
        $item->edi_id = $data['PO1' . $ctr . '-01'] . '*' . $data['PO1' . $ctr . '-07'] . 
                        '*' . $data['PO1' . $ctr . '-09']  . '*' . $data['PO1' . $ctr . '-11'];
        
        
        $product = Helper::findProduct($data['PO1' . $ctr . '-09']);
        
        if ( !$product ) {
          $product = new Product;
          
          $product->product_model = $data['PO1' . $ctr . '-09'];  
          $product->id_catalog = $data['PO1' . $ctr . '-09'];  
          $product->product_name = 'Product not found ' . $data['PO1' . $ctr . '-11'];
          $product->product_upc = $data['PO1' . $ctr . '-07'];  
          
          $product->save();
        }
        
        $item->item_code = $product->product_model;  
        $item->item_description = $product->product_name;
        $item->item_id = $product->id_catalog;
        $item->item_thumb = $product->product_thumb;
        $item->item_url = $product->product_url;
        $item->child_sku = Helper::insertOption($data['PO1' . $ctr . '-09'], $item);
        
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
        
        $notice = "ISA*00*          *00*          *$ISA*ZZ*ZVDXPROD       *$date*$time*U*00401*$control_num*0*P*>~\n" .
                  'GS*SH*' . $this->setup[$store]['ISA'] . "*ZVDXPROD*20$date*$time*$group_num*X*004010~\n" .
                  "ST*856*" . sprintf('%09d', str_replace('-', '', $shipment->unique_order_id)) . "~\n" .
                  'BSN*00*' . str_replace('-', '', $shipment->unique_order_id) . "*20$date*$time*0001~\n" .
                  "HL*1**S~\n" .
                  'TD1*CTN25*' . $shipment->package_count . '****G*' . $weight . "*LB~\n" .
                  'TD5**2*UPSN' . "~\n" .
                  'REF*CN*' . $shipment->shipping_id . "~\n" .
                  "DTM*011*20$date~\n" .
                  'N1*SF*' . $this->setup[$store]['name'] . '*92*' . $this->setup[$store]['id'] . "~\n" .
                  "N3*575 UNDERHILL BLVD~\n" .
                  "N4*SYOSSET*NY*11791*US~\n" .
                  'N1*ST*' . substr($customer->bill_company_name, 0, strpos($customer->bill_company_name, '*')) . 
                    '*92*' . substr($customer->bill_company_name, strpos($customer->bill_company_name, '*') + 1) . "~\n" .
                  'N3*' . $customer->ship_address_1;
                  
          if ($customer->ship_address_2 && $customer->ship_address_2 != '') {
            $notice .= '*' . $customer->ship_address_2;
          } 
          
          $notice .= "~\n" . 'N4*' .  $customer->ship_city . '*' . $customer->ship_state . '*' . 
                  $customer->ship_zip . '*' . Shipper::getcountrycode($customer->ship_country) . "~\n" .
                  "HL*2*1*O~\n" .
                  'PRF*' . $shipment->order->short_order . "~\n" .
                  "HL*3*2*P~\n" . 
                  'MAN*GM*' . str_replace('-', '', $shipment->unique_order_id) . "~\n";
          
          $counter = 3;
          $total_quantity = 0;
          
          foreach ($shipment->items as $item) {
            
            $ex = explode('*', $item->edi_id);
            
            if (!isset($ex[2])) {
              Log::error('Zulily FC: Error retrieving UPC for item ' . $item->id);
              continue;
            }
            
            $notice .=  'HL*' . ++$counter . '*' . ($counter - 1) . "*I~\n" .
                        'LIN**UP*' . trim($ex[1]) . "~\n" .
                        'SN1**' . $item->item_quantity . "*EA~\n";
            
            $total_quantity += $item->item_quantity;
          }
          
          $notice .=  'CTT*' . $counter . '*' . $total_quantity . "~\n" . //todo
                      'SE*' . intval(((count($shipment->items)) * 3) + 18) . '*' . 
                              sprintf('%09d', str_replace('-', '', $shipment->unique_order_id)) . "~\n" .
                      "GE*1*$group_num~\n" .
                      "IEA*1*$control_num~\n\n";
        
        $filename = $shipment->order->order_id . '_856_' . $datetime_unique . '.edi';
        $file = fopen(storage_path() . $this->upload_dir . $filename, "w");
        fwrite($file, $notice);
        fclose($file);
        
        copy(storage_path() . $this->upload_dir . $filename, storage_path() . $this->dir . $store . '/out/' . $filename);
      }
      
      return;
      
    }
    
    public function exportShipments($store, $shipments) {
      
		}
		
		public function importCsv($store, $file) {
			
		} 
		 
		public function backorderNotification($store, $item) {
			
		}
		
		public function shipLabel($store, $unique_order_id, $order_id) {
      
        $shipment = Ship::with('order.customer')
                        ->where('unique_order_id', $unique_order_id)
                        ->first();
        
        if (!$shipment) {
          return 'Zulily FC: Shipment not found for Ship label ' . $unique_order_id;
          Log::error('Zulily FC: Shipment not found for Ship label ' . $unique_order_id);
          return null;
        }
        
        $ASN = str_replace('-', '', $shipment->unique_order_id); 
        
        $customer = $shipment->order->customer;
        
        $name =  $customer->ship_full_name;
        $address1 = $customer->ship_address_1; 
        $city = $customer->ship_city; 
        $state = $customer->ship_state; 
        $zip = $customer->ship_zip; 
        $order_id = $order_id; 
        $PO = $shipment->order->short_order; 
        $date = date("n/d/y"); 
        
        $company_name = $this->setup[$store]['name'];
        
        $carton_count = $shipment->package_count;
        
        $zpl = '';
        
        for ($carton = 1; $carton <= $carton_count; $carton++) {
          $zpl .="^XA";
          $zpl .="^FO50,40^A0,110,110^FDzulily^FS";
          $zpl .="^FO400,40^A0,40,40^FDCarton^FS";
          $zpl .="^FO420,80^A0,40,40^FD$carton/$carton_count^FS";
          $zpl .="^FO600,40^A0,40,40^FDPallet^FS";
          $zpl .="^FO620,80^A0,40,40^FD- / -^FS";
          $zpl .="^FO10,140^GB780,1,2^FS";

          $zpl .="^FO50,200^A0,65,65^FDDetails^FS";
          $zpl .="^FO60,280^A0,35,35^FDVENDOR^FS";
          $zpl .="^FO70,320^A0,40,40^FD$company_name^FS";
          $zpl .="^FO450,280^A0,35,35^FDPO #^FS";
          $zpl .="^FO460,320^A0,40,40^FD$PO^FS";
          $zpl .="^FO60,400^A0,35,35^FDZULILY VENDOR SPECIALIST^FS";
          $zpl .="^FO70,440^A0,40,40^FDGalager Martinez^FS";
          $zpl .="^FO60,520^A0,35,35^FDASN^FS";
          $zpl .="^FO70,560^A0,40,40^FD$ASN^FS";
          $zpl .="^FO10,620^GB780,1,2^FS";

          $zpl .="^FO50,685^A0,65,65^FDLocations^FS";
          $zpl .="^FO60,765^A0,35,35^FDSHIP TO^FS";
          $zpl .="^FO70,810^A0,40,40^FD$name^FS";
          $zpl .="^FO70,850^A0,40,40^FD$address1^FS";
          $zpl .="^FO70,890^A0,40,40^FD$city, $state^FS";
          $zpl .="^FO70,930^A0,40,40^FD$zip^FS";

          $zpl .="^FO450,765^A0,35,35^FDSHIP FROM^FS";
          $zpl .="^FO460,810^A0,40,40^FD$company_name^FS";
          $zpl .="^FO460,850^A0,40,40^FD575 Underhilll Blvd^FS";
          $zpl .="^FO460,890^A0,40,40^FDSuite 325^FS";
          $zpl .="^FO460,930^A0,40,40^FDSyosset, NY 11791^FS";
          $zpl .="^FO300,1000^BY2^BCN,150,Y,N,N^FD$ASN^FS";
          $zpl .="^XZ";
        }
        
        $zpl = str_replace("'", " ", $zpl);
        $zpl = str_replace('"', " ", $zpl);
        $zpl = str_replace('&quot;', " ", $zpl);
        
        return ['', $zpl]; 
      
		}
				 
}
