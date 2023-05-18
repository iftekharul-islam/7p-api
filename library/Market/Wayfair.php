<?php 
 
namespace Market; 

use Illuminate\Support\Facades\Log;
use Monogram\FTPConnection;
use Monogram\X12_EDI;
use Monogram\Helper;
use App\Customer;
use App\Order;
use App\Item;
use App\Product;
use Ship\Shipper;

class Wayfair extends StoreInterface 
{ 
    protected $dir = '/EDI/Wayfair/'; 
    
    protected $url = 'edi.wayfair.com';
    protected $remote_upload = 'incoming/';
    protected $remote_download = 'outgoing/';
    protected $inventory_upload = 'inventory/';
    protected $username = 'EDI_MonogramonlineInc';
// -------------- New Password Provided by Wayfair - JSC ----------------------//
    protected $password = 'Wayfair!2019';
    
    protected $setup = ['wayfair-01' => [ 'name' => 'MonogramonlineInc.',
                                          'ISA' => '856320321',
                                          'ISA_TYPE' => '12',
                                          'VENDOR' => '29004',
                                          'QB' => 0],
                        'wayfair-02' => [ 'name' => 'Natico Originals',
                                          'ISA' => '068229863',
                                          'ISA_TYPE' => '01',
                                          'VENDOR' => '7880',
                                          'QB' => 1]];
                                           
                                    
    public function importCsv($store, $file) {
      //
    }
    
    public function getInput($store, $files = null) 
    {   
        if ($files == null) {
          
          Log::info('Wayfair: contacting via FTP');
        
          try { 
              $ftp = new FTPConnection($this->url, $this->username, $this->password); 
          } catch (\Exception $e) { 
              Log::error('Wayfair getInput: FTP connection error ' . $e->getMessage()); 
              return FALSE; 
          } 
          
          try { 
                $files = $ftp->downloadFiles($this->remote_download, $this->dir . 'in/');
            } catch (\Exception $e) { 
                Log::error('Wayfair getInput: FTP download error ' . $e->getMessage()); 
                return FALSE; 
            } 
        } 
        
        if ($files != null && count($files) != 0) {
          
          foreach ($files as $file) {
            
            Log::info('Wayfair: ' . $file . ' being processed');
            
            $purchase_orders = X12_EDI::parse(storage_path() . $this->dir . '/in/' . $file, 'newline', '*', '~');
            
            $order_id = null;
            
            foreach($purchase_orders as $purchase_order) {
              if (isset($purchase_order['ISA01'])) {
                $store_id = null;
                foreach ($this->setup as $id => $config) {
                  if ($config['ISA'] == trim($purchase_order['ISA08'])) {
                    $store_id = $id;
                  }
                }
                if($store_id == null) {
                  Log::error('Wayfair getInput: Store not Found ' . $purchase_order['ISA08']); 
                  return FALSE;
                }
              } else if (isset($purchase_order["BEG03"])) {
                
                $previous_order = Order::where('order_id', $purchase_order["BEG03"])
                                  ->where('is_deleted', '0')
                                  ->first();
                
                if ( $previous_order ) {
                 Log::error('Wayfair : Order number already in Database ' . $purchase_order["BEG03"]); 
                 continue;
                } 
                
                $order_id = $this->insert_order($store_id, $purchase_order);
                $this->orderAcknowledgement($store_id, $purchase_order, $order_id);
              }
            }
            
            $this->upload($store_id);
            
          }
        }
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
      $customer->ship_phone = $data["DC-PER04"];
      
      $customer->bill_email = $data["DC-PER08"];
      $customer->bill_company_name = 'Wayfair';
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
      $order->total = $data["AMT02"]; 
      $order->order_date = substr($data["BEG05"], 0, 4) . '-' . substr($data["BEG05"], 4, 2) . '-' . substr($data["BEG05"], 6, 2) . date(" H:i:s");
      $order->store_id = $store_id; 
      $order->store_name = 'Wayfair';
      $order->order_status = 4;      
      $order->ship_state = $data["ST-N402"];
      $order->ship_date = substr($data["DTM02"], 0, 4) . '-' . substr($data["DTM02"], 4, 2) . '-' . substr($data["DTM02"], 6, 2);
      
      if ($data["TD503"] == 'FDEG' && $data["TD505"] == 'FDHD') {
        $order->carrier = 'FX';
        $order->method = '_GROUND_HOME_DELIVERY'; 
      } else if ($data["TD503"] == 'FDEG' && $data["TD505"] == 'GR') {
        $order->carrier = 'FX';
        $order->method = '_FEDEX_GROUND';
      } else if ($data["TD503"] == 'FDEG' && $data["TD505"] == '2D') {
        $order->carrier = 'FX';
        $order->method = '_FEDEX_2_DAY'; 
      } else if ($data["TD503"] == 'FDEG' && $data["TD505"] == 'ND') {
        $order->carrier = 'FX';
        $order->method = '_PRIORITY_OVERNIGHT'; 
      } else if ($data["TD503"] == 'UPSN' && $data["TD505"] == 'GR') {
        $order->carrier = 'UP';
        $order->method = 'S_GROUND'; 
      } else if ($data["TD503"] == 'UPSN' && $data["TD505"] == '2D') {
        $order->carrier = 'UP';
        $order->method = 'S_AIR_2DAY';
      } else if ($data["TD503"] == 'UPSN' && $data["TD505"] == 'ND') {
        $order->carrier = 'UP';
        $order->method = 'S_AIR_1DAY';
      } else {
        Log::error('Wayfair: Unrecognized shipping - ' . $data["TD503"] . ' , ' . $data["TD505"] . ' - Order ' . $data["BEG03"]);
        $order->carrier = 'FX';
        $order->method = '_GROUND';
      }
      
      $customer->save();
      
      try {
        $order->customer_id = $customer->id;
      } catch ( \Exception $exception ) {
        Log::error('Failed to insert customer id in Wayfair');
      }
      
      $order->save();
      
      try {
        $order_5p = $order->id;
      } catch ( \Exception $exception ) {
        $order_5p = '0';
        Log::error('Failed to get 5p order id in Wayfair');
      }
      
      // -------------- Orders table data insertion ended ----------------------//
      // -------------- Items table data insertion started ------------------------//
      
      $ctr = '01';
      $total = 0;
      
      while (isset($data['PO1' . $ctr . '-01'])) {
        
        $comments = $data['MTX' . $ctr . '-02'] ?? null;
        
        $comments = str_replace('## Customization Details ##', '', $comments);
        $comments = substr($comments, 0, strpos($comments, "-- SPECIAL COMMENTS:"));
        $comments = explode('  ', trim($comments));
        
        $options = array();
        
        foreach ($comments as $comment) {
          $opt = explode(':', $comment);
          
          if (isset($opt[0]) && isset($opt[1]) && strlen($opt[1]) > 0) {
            $options['*' . $opt[0] . '*'] = trim($opt[1]);
          }
        }
        
        $counter = 1;
        
        foreach ($comments as $comment) {
          $opt = explode(':', $comment); 
          if (isset($opt[0]) && isset($opt[1]) && strlen($opt[1]) > 0) {
            $options[$counter] = trim($opt[1]);
            $counter++;
          }
        }
        
        // -------------- Products table data insertion started ---------------------- //
        
        $product = Helper::findProduct($data['PO1' . $ctr . '-07']);
        
        if ( !$product ) {
          $product = new Product();
          $product->id_catalog = str_replace(' ', '-', strtolower($data['PID' . $ctr . '-05'])) . '-' . strtolower($data['PO1' . $ctr . '-07']);
          $product->product_model = $data['PO1' . $ctr . '-07'];
          // $product->product_url = ;
          // $product->product_thumb = ;
          $product->product_name = $data['PID' . $ctr . '-05'];
          $product->save();
        }
        
        $item = new Item();
        $item->order_id = $order->order_id;
        $item->store_id = $store_id; 
        $item->data_parse_type = 'EDI';
        
        $item->item_quantity = $data['PO1' . $ctr . '-02'];
        $item->item_unit_price = $data['PO1' . $ctr . '-04']; 
        $item->edi_id = $data['PO1' . $ctr . '-01'];
        
        $item->item_code = $product->product_model;
        
        $item->item_description = $data['PID' . $ctr . '-05'];
        $item->item_id = $product->id_catalog;
        $item->item_thumb = $product->product_thumb;
        $item->item_url = $product->product_url;
        
        $item->item_option = json_encode($options);
        
        $item->child_sku = Helper::insertOption($data['PO1' . $ctr . '-07'], $item);
        
        $item->save();
        
        $ctr = sprintf('%02d', intval($ctr) + 1);
        
        $item->order_5p = $order->id;
        $item->save();
        
        $total = $total + ($item->item_unit_price * $item->item_quantity);
      }
      
      //Inventory::addInventoryByStockNumber(null, $item->child_sku);
      // -------------- Items table data insertion ended ---------------------- //
      
      $order->total = sprintf("%01.2f", $total);
      $order->save();
      
      return $order->id;
        
    }
    
    private function orderAcknowledgement ($store_id, $data, $order_id) 
    {
      $date = date("ymd");
      $time = date("Hi");
      $ship_date = date('Ymd', strtotime('+5 days'));
      $control_num = sprintf('%09d', rand(1000,10000));
      $group_num = sprintf('%09d', rand(1000,10000));
      
      $ISA = $this->setup[$store_id]['ISA_TYPE'] . '*' . str_pad($this->setup[$store_id]['ISA'], 15, ' ', STR_PAD_RIGHT);
      
      $ack = "ISA*00*          *00*          *$ISA*01*112084681      *$date*$time*^*00403*$control_num*0*P*~\n" .
              'GS*PR*' . $this->setup[$store_id]['ISA'] . "*112084681*20$date*$time*$group_num*X*004030\n" .
              "ST*855*" . sprintf('%09d', $order_id) . "\n";
      
        
        isset($data["ST-N302"]) ?  $add_2 = '*' . $data["ST-N302"] : $add_2 = '';
        
        $ack .= 'BAK*00*AD*' . $data["BEG03"] . "*20$date\n" .
                'REF*VR*' . $this->setup[$store_id]['VENDOR'] . "\n" .
                'N1*ST*' . $data["ST-N102"] . "\n" .
                'N3*' . $data["ST-N301"] . $add_2 . "\n" .
                'N4*' . $data["ST-N401"] . '*' . $data["ST-N402"] . '*' . $data["ST-N403"] . '*' . $data["ST-N404"] . "\n";
                
        $ctr = '01';
        
        while (isset($data['PO1' . $ctr . '-01'])) {
          $ack .= 'PO1' . '*' . $data['PO1' . $ctr . '-01'] . '*' . $data['PO1' . $ctr . '-02'] . '*EA*' . $data['PO1' . $ctr . '-04'] . 
                    '**VN*' . $data['PO1' . $ctr . '-07'] . "\n";
          $ack .= 'ACK*IA*' . $data['PO1' . $ctr . '-02'] . '*EA*010*' . $ship_date . '****VO*' . $order_id . "\n";
          
          $ctr = sprintf('%02d', intval($ctr) + 1);
        }
              
        $ack .= 'CTT*' . $data["CTT01"] . "\n" .
                'AMT*GV*' . intval($data["AMT02"] * 100) . "\n".
                'SE*' . (((intval($ctr) - 1) * 2) + 9) . '*' . sprintf('%09d', $order_id) . "\n" .
                "GE*1*$group_num\n" .
                "IEA*1*$control_num\n\n";
        
        $filename = $data["BEG03"] . '_855_' . date("Ymd_His") . '.edi';
        $ack_file = fopen(storage_path() . $this->dir . $store_id . '/upload/' . $filename, "w");
        fwrite($ack_file, $ack);
        fclose($ack_file);
        
        $this->upload($store_id);
        
        return;
    }
    
    public function orderConfirmation($store, $order)
    {
      
    }  
     
    public function backorderNotification($store, $item) 
    {
      
    }
  
    public function shipLabel($store, $unique_order_id, $order_id) 
    {
      
    }
    
    public function shipmentNotification($store_id, $shipments) 
    {
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
        
        if ($shipment->order->carrier == 'FX' && $shipment->order->method == '_GROUND_HOME_DELIVERY') {
          $scac = 'FDEG';
          $speed = 'FDHD';
        } else if ($shipment->order->carrier == 'FX' && $shipment->order->method == '_GROUND') {
          $scac = 'FDEG';
          $speed = 'GR';
        } else if ($shipment->order->carrier == 'FX' && $shipment->order->method == '_FEDEX_2_DAY') {
          $scac = 'FDEG';
          $speed = '2D';
        } else if ($shipment->order->carrier == 'FX' && $shipment->order->method == '_PRIORITY_OVERNIGHT') {
          $scac = 'FDEG';
          $speed = 'ND';
        } else if ($shipment->order->carrier == 'UP' && $shipment->order->method == 'S_GROUND') {
          $scac = 'UPSN';
          $speed = 'GR';
        } else if ($shipment->order->carrier == 'UP' && $shipment->order->method == 'S_AIR_2DAY') {
          $scac = 'UPSN';
          $speed = '2D';
        } else if ($shipment->order->carrier == 'UP' && $shipment->order->method == 'S_AIR_1DAY') {
          $scac = 'UPSN';
          $speed = 'ND';
        } else {
          Log::error('Wayfair: Unrecognized shipping - Order ' . $shipment->order->order_id);
          $scac = 'USPS';
          $speed = 'GR';
        }
        
        $ISA = $this->setup[$store_id]['ISA_TYPE'] . '*' . str_pad($this->setup[$store_id]['ISA'], 15, ' ', STR_PAD_RIGHT);
        
        $notice = "ISA*00*          *00*          *$ISA*01*112084681      *$date*$time*^*00403*$control_num*0*P*~\n" .
                  'GS*SH*' . $this->setup[$store_id]['ISA'] . "*112084681*20$date*$time*$group_num*X*004030\n" .
                  "ST*856*" . sprintf('%09d', str_replace('-', '', $shipment->unique_order_id)) . "\n" .
                  'BSN*00*' . str_replace('-', '', $shipment->unique_order_id) . "*20$date*$time*0001\n" .
                  "HL*1**S\n" .
                  "TD1*CTN*1*****1*LB*1*CF\n" .
                  'TD5**2*' . $scac . '*ZZ*' . $speed . "\n" .
                  'REF*2I*' . $shipment->shipping_id . "\n" .
                  "DTM*011*20$date\n" .
                  'N1*SF*' . $this->setup[$store_id]['name'] . "\n" .
                  "N3*575 UNDERHILL BLVD\n" .
                  "N4*SYOSSET*NY*11791*US\n" .
                  'N1*ST*' . $customer->ship_full_name . "\n" .
                  'N3*' . $customer->ship_address_1;
                  
          if ($customer->ship_address_2 && $customer->ship_address_2 != '') {
            $notice .= '*' . $customer->ship_address_2;
          } 
          
          $notice .= "\n" . 'N4*' .  $customer->ship_city . '*' . $customer->ship_state . '*' . 
                  $customer->ship_zip . '*' . Shipper::getcountrycode($customer->ship_country) . "\n" .
                  "HL*2*1*O\n" .
                  'PRF*' . $shipment->order->short_order . "\n" .
                  'REF*VR*' . $this->setup[$store_id]['VENDOR'] . "\n";
                  
          foreach ($shipment->items as $item) {
            $notice .=  "HL*4*3*I\n" .
                        'LIN**VN*' . $item->child_sku . "\n" .
                        'SN1**' . $item->item_quantity . "*EA\n";
          }
          
          $notice .=  'CTT*' . intval(count($shipment->items) * 3) . "\n" .
                      'SE*' . intval(((count($shipment->items)) * 3) + 18) . '*' . 
                              sprintf('%09d', str_replace('-', '', $shipment->unique_order_id)) . "\n" .
                      "GE*1*$group_num\n" .
                      "IEA*1*$control_num\n\n";
        
        $filename = $shipment->order->order_id . '_856_' . $datetime_unique . '.edi';
        $file = fopen(storage_path() . $this->dir . $store_id . '/upload/' . $filename, "w");
        fwrite($file, $notice);
        fclose($file);
        
        $this->upload($store_id);
        
        $time = date("Hi");
        $control_num = sprintf('%09d', rand(1000,10000));
        $group_num = sprintf('%09d', rand(1000,10000));
        
        $invoice ="ISA*00*          *00*          *$ISA*01*112084681      *$date*$time*^*00403*$control_num*0*P*~\n" .
                  'GS*SH*' . $this->setup[$store_id]['ISA'] . "*112084681*20$date*$time*$group_num*X*004030\n" .
                  "ST*810*" . sprintf('%09d', str_replace('-', '', $shipment->unique_order_id)) . "\n" .
                  'BIG*20' . $date . '*' . $shipment->unique_order_id . '*' .
                        substr(str_replace('-', '', $shipment->order->order_date),0,8) . '*' . 
                        $shipment->order->short_order . "***DI\n" .
                  'REF*VR*' . $this->setup[$store_id]['VENDOR'] . "\n" .
                  'PER*IC*' . $this->setup[$store_id]['name'] . "*TE*8663203210*FX*5169084925\n" .
                  'N1*ST*' . $customer->ship_full_name . "\n" .
                  'N3*' . $customer->ship_address_1;
                  
        if ($customer->ship_address_2 && $customer->ship_address_2 != '') {
          $invoice .= '*' . $customer->ship_address_2;
        } 
        
        $invoice .= "\n" . 'N4*' .  $customer->ship_city . '*' . $customer->ship_state . '*' . 
                            $customer->ship_zip . '*' . Shipper::getcountrycode($customer->ship_country) . "\n" .
                            "DTM*011*20$date\n";
                  
        $total = 0;
        
        foreach ($shipment->items as $item) {
          $invoice .=  'IT1**' . $item->item_quantity . '*EA*' . $item->item_unit_price . '**VN*' . $item->child_sku . "\n" .
                       'PID*F****' . $item->item_description . "\n";
          $total += ($item->item_quantity * $item->item_unit_price);
        }
                  
        $invoice .= 'TDS*' . intval($total * 100) . "\n" .
                    'SE*' . (((count($shipment->items)) * 2) + 10) . '*' . 
                            sprintf('%09d', str_replace('-', '', $shipment->unique_order_id)) . "\n" .
                    "GE*1*$group_num\n" .
                    "IEA*1*$control_num\n\n";
        
        $filename = $shipment->order->order_id . '_810_' . $datetime_unique . '.edi';
        $file = fopen(storage_path() . $this->dir . $store_id . '/upload/' . $filename, "w");
        fwrite($file, $invoice);
        fclose($file);
        
        $this->upload($store_id);
      }
      
      $this->inventory_upload($store_id);
      
      return;
    }
    
    public function exportShipments($store, $shipments) {
      
    }
    
    public function inventory_upload($store_id) 
    {   
      if (file_exists(storage_path() . $this->dir . $store_id . '/inventory/')) {

        try { 
            $ftp = new FTPConnection($this->url, $this->username, $this->password); 
        } catch (\Exception $e) { 
            Log::error('Wayfair inventory_upload: FTP connection error ' . $e->getMessage()); 
            return; 
        } 
        
        try {
          $file_list = $ftp->uploadDirectory($this->inventory_upload, $this->dir . $store_id . '/inventory/');
        } catch (\Exception $e) { 
            Log::error('Wayfair inventory upload: FTP upload error ' . $e->getMessage()); 
            return; 
        } 
        
      }
        
      return;
    }
    
    public function upload ($store_id) 
    {   
        
        try { 
            $ftp = new FTPConnection($this->url, $this->username, $this->password); 
        } catch (\Exception $e) { 
            Log::error('Wayfair upload: FTP connection error ' . $e->getMessage()); 
            return; 
        } 
        
        try {
          $file_list = $ftp->uploadDirectory($this->remote_upload, $this->dir . $store_id . '/upload/');
        } catch (\Exception $e) { 
            Log::error('Wayfair upload: FTP upload error ' . $e->getMessage()); 
            return; 
        } 
        
        //move files to out directory
        foreach ($file_list as $file) {
          try {
            rename(storage_path() . $this->dir . $store_id . '/upload/' . $file, storage_path() . $this->dir . $store_id . '/out/' . $file);
          } catch (\Exception $e) {
            Log::error('Wayfair upload: File rename Error ' . $e->getMessage()); 
          }
        }
        
        return;
    }
}
