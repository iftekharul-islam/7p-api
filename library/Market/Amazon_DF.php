<?php 
 
namespace Market; 

use Illuminate\Support\Facades\Log;
use Monogram\SFTPConnection;
use Monogram\X12_EDI;
use Monogram\Helper;
use App\Customer;
use App\Order;
use App\Item;
use App\Product;
use Ship\Shipper;

class Amazon_DF extends StoreInterface 
{ 
    protected $dir = '/EDI/Amazon/';
    
    protected $setup = ['MP9PY' => ['download' => '3VMP81TTBMVXB', 
                                    'upload' => '3AIJGO1B5R34A', 
                                    'ISA' => '856320321', 
                                    'ISA_TYPE' => '12',
                                    'name' => 'Monogramonline',
                                    'AMZISA' => 'AMAZONDS']]; 
    
    public function importCsv($store, $file) {
      //
    }
    
    public function getInput($store, $files = null) 
    {   
        if ($files == null) {
          Log::info('Amazon DF: contacting via SFTP ' . $store);
          
          try { 
              $sftp = new SFTPConnection('sftp.amazonsedi.com', 2222, 'id_rsa'); 
              $sftp->login($this->setup[$store]['download'], null, storage_path() . $this->dir . '.ssh/id_rsa.pub', storage_path() . $this->dir . '.ssh/id_rsa');   
          } catch (\Exception $e) { 
              Log::error('Amazon_DF getInput: SFTP connection error ' . $e->getMessage()); 
              return FALSE; 
          } 
          
          try { 
              $files = $sftp->downloadFiles('/download/', $this->dir . $store . '/in/');
          } catch (\Exception $e) { 
              Log::error('Amazon_DF getInput: SFTP download error ' . $e->getMessage()); 
              return FALSE; 
          } 
      }
      // $files = array_diff(scandir(storage_path() . $this->dir . $store . '/in/mp9q2201809131316040amz.xof'), array('..', '.')); 
      
      // $files = [0 => 'mp9q2201809260946370amz.xof'];
      
      if ($files == null) {
        return;
      }
      
      foreach ($files as $file) {
        
        // $purchase_orders = X12_EDI::parse(storage_path() . $this->dir . $store . '/in/' . $file, '~', $this->setup[$store]['delim_1'], $this->setup[$store]['delim_2']);
        $purchase_orders = X12_EDI::parse(storage_path() . $this->dir . $store . '/in/' . $file, '~', '*', '>');
        // $purchase_orders = X12_EDI::parse($file, '~', '*', '>');
        
        $order_id = null;
        
        foreach($purchase_orders as $purchase_order) { 
          if (!isset($purchase_order['ISA01']) && isset($purchase_order["BEG03"])) {
            
            $previous_order = Order::where('order_id', $purchase_order["BEG03"])
                              ->where('is_deleted', '0')
                              ->first();
            
            if ( $previous_order ) {
             Log::error('Amazon_DF : Order number already in Database ' . $purchase_order["BEG03"]); 
             // continue;
            } 
            
            $result = $this->insert_order($purchase_order, $store);
            $this->orderAcknowledgement($purchase_order, $result, $store);
          }
        }
      }
      
      $this->upload($store);
    }
    
    private function insert_order($data, $store) {
      
      // -------------- Customers table data insertion started ----------------------//
      $customer = new Customer();
      try {
      $customer->order_id = $data["BEG03"];
      $customer->ship_full_name = $data["ST-N102"];
      $customer->ship_last_name = (strpos($data["ST-N102"], ' ') === false) ? '' : preg_replace('#.*\s([\w-]*)$#', '$1', $data["ST-N102"]);
      $customer->ship_first_name = trim( preg_replace('#'.$customer->ship_last_name.'#', '', $data["ST-N102"] ) );
      $customer->ship_company_name = $data["ST-N201"] ?? null;
      $customer->ship_address_1 = $data["ST-N301"] ?? null;
      $customer->ship_address_2 = $data["ST-N302"] ?? null;
      $customer->ship_city = $data["ST-N401"] ?? null;
      $customer->ship_state = isset($data["ST-N401"]) ? Helper::stateAbbreviation($data["ST-N402"]) : null;
      $customer->ship_zip = $data["ST-N403"] ?? null;
      $customer->ship_country = $data["ST-N404"] ?? 'US';
      $customer->ship_phone = $data["ZZ-PER06"] ?? '0000000000';
      
      $customer->bill_email = '';
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
      $order->order_id = $data["BEG03"];
      $order->short_order = $data["BEG03"];
      $order->item_count = $data["CTT01"];
      $order->shipping_charge = '0';
      $order->tax_charge = '0';
      $order->total = '0'; 
      $order->order_date = substr($data["BEG05"], 0, 4) . '-' . substr($data["BEG05"], 4, 2) . '-' . substr($data["BEG05"], 6, 2) . date(" H:i:s");
      $order->store_id = $store; 
      $order->store_name = $data["SF-N102"] . '*' . $data["ST-TD503"];
      $order->order_status = 15;      
      $order->ship_state = $data["ST-N402"] ?? null;

        $ship_info = $this->lookup[$data["ST-TD503"]];
        $order->carrier = $ship_info[0];
        $order->method = $ship_info[1];
      } catch (\Exception $e) {
        Log::error('Ship Code ' . $data["ST-TD503"] . ' not found in Amazon_DF ' . $e->getMessage());
        $order->carrier = 'UP';
        $order->method = 'S_GROUND';
      }
      
      // $order->carrier = 'MN';
      // $order->method = 'Get ' . $data["ST-TD503"] . ' label from Vendor Central';
      $customer->save();
      
      try {
        $order->customer_id = $customer->id;
      } catch ( \Exception $exception ) {
        Log::error('Failed to insert customer id in Amazon DF');
      }
      
      $order->save();
      
      try {
        $order_5p = $order->id;
      } catch ( \Exception $exception ) {
        $order_5p = '0';
        Log::error('Failed to get 5p order id in Amazon DF');
      }
      
      // -------------- Orders table data insertion ended ----------------------//
      // -------------- Items table data insertion started ------------------------//
      
      $ctr = '01';
      $total = 0;
      
      while (isset($data['PO1' . $ctr . '-01'])) {
        
        // -------------- Products table data insertion started ---------------------- //
        
        $product = Helper::findProduct($data['PO1' . $ctr . '-07']);
        
        if ( !$product ) {
          $product = new Product();
          $product->id_catalog = $data['PO1' . $ctr . '-07'];
          $product->product_model = $data['PO1' . $ctr . '-07'];
          // $product->product_url = ;
          // $product->product_thumb = ;
          $product->product_name = 'Amazon DF PRODUCT NOT FOUND';
          $product->save();
        }
        
        $item = new Item();
        $item->order_id = $order->order_id;
        $item->store_id = $store; 
        $item->data_parse_type = 'EDI';
        
        $item->item_quantity = $data['PO1' . $ctr . '-02'];
        $item->item_unit_price = $data['PO1' . $ctr . '-04']; 
        $item->edi_id = $data['PO1' . $ctr . '-01'] . '*' . $data['PO1' . $ctr . '-07'];
        
        $item->item_code = $product->product_model;
        
        $item->item_description = $product->product_name;
        $item->item_id = $product->id_catalog;
        $item->item_thumb = $product->product_thumb;
        $item->item_url = $product->product_url;
        
        $item->item_option = json_encode(["instructions" => "Get Personalization from Amazon Portal"]);
        
        $item->child_sku = Helper::insertOption($data['PO1' . $ctr . '-07'], $item);
        
        $item->save();
        
        $ctr = sprintf('%02d', intval($ctr) + 1);
        
        $item->order_5p = $order->id;
        $item->save();
        
        $total = $total + $item->item_unit_price * $item->item_quantity;
      
      }
      
      //Inventory::addInventoryByStockNumber(null, $item->child_sku);
      // -------------- Items table data insertion ended ---------------------- //
      
      $order->total = sprintf("%01.2f", $total);
      $order->save();
      
      return [$order->id, $order->total];
        
    }
    
    private function orderAcknowledgement ($data, $result, $store) 
    {
      $ISA_1 = $this->setup[$store]['ISA_TYPE'] . '*' . str_pad($this->setup[$store]['ISA'], 15, ' ', STR_PAD_RIGHT);
      $ISA_2 = str_pad($this->setup[$store]['AMZISA'], 15, ' ', STR_PAD_RIGHT);
      
      $date = date("ymd");
      $time = date("Hi");
      $ship_date = date('Ymd', strtotime('+5 days'));
      $control_num = sprintf('%09d', rand(1000,10000));
      $group_num = sprintf('%09d', rand(1000,10000));
      
      $ack = "ISA*00*          *00*          *$ISA_1*ZZ*$ISA_2*$date*$time*^*00401*$control_num*0*P*~\n" .
              'GS*PR*' . $this->setup[$store]['ISA'] . '*' . $this->setup[$store]['AMZISA'] . "*20$date*$time*$group_num*X*004010\n" .
              "ST*855*" . sprintf('%09d', $result[0]) . "\n";
      
        
        isset($data["ST-N302"]) ?  $add_2 = '*' . $data["ST-N302"] : $add_2 = '';
        
        $ack .= 'BAK*00*AT*' . $data["BEG03"] . "*20$date***" . $result[0] . "\n" .
                'N1*SF*' . $data["SF-N102"] . '*92*' . $data["SF-N104"] . "\n";
        
        $ctr = '01';
        
        while (isset($data['PO1' . $ctr . '-01'])) {
          $ack .= 'PO1' . '*' . $data['PO1' . $ctr . '-01'] . '*' . $data['PO1' . $ctr . '-02'] . '*EA***SK*' . $data['PO1' . $ctr . '-07'] .  "\n";
          $ack .= 'ACK*IA*' . $data['PO1' . $ctr . '-02'] . '*EA**************************00' . "\n";
        
          $ctr = sprintf('%02d', intval($ctr) + 1);
        }
              
        $ack .= 'CTT*' . $data["CTT01"] . "\n" .
                'SE*' . ((intval($data["CTT01"]) * 2) + 3) . '*' . sprintf('%09d', $result[0]) . "\n" .
                "GE*1*$group_num\n" .
                "IEA*1*$control_num\n\n";
        
        $filename = $data["BEG03"] . '_855_' . date("Ymd_His") . '.edi';
        $ack_file = fopen(storage_path() . $this->dir . $store . '/upload/' . $filename, "w");
        fwrite($ack_file, $ack);
        fclose($ack_file);
        
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
    
    public function shipmentNotification($store, $shipments) 
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
        
        $ship_method = null;
        
        $ex = explode('*', $shipment->order->store_name);
        
        $ISA_1 = $this->setup[$store]['ISA_TYPE'] . '*' . str_pad($this->setup[$store]['ISA'], 15, ' ', STR_PAD_RIGHT);
        $ISA_2 = str_pad($this->setup[$store]['AMZISA'], 15, ' ', STR_PAD_RIGHT);
        
        $level = 0;
        
        $notice = "ISA*00*          *00*          *$ISA_1*ZZ*$ISA_2*$date*$time*^*00401*$control_num*0*P*~\n" .
                  'GS*SH*' . $this->setup[$store]['ISA'] . '*' . $this->setup[$store]['AMZISA'] . "*20$date*$time*$group_num*X*004010\n" .
                  "ST*856*" . sprintf('%09d', str_replace('-', '', $shipment->unique_order_id)) . "\n" .
                  'BSN*00*' . str_replace('-', '', $shipment->unique_order_id) . "*20$date*$time*ZZZZ*AS*NOR\n" .
                  'HL*' . ++$level . "**O\n" .
                  'PRF*' . $shipment->order->short_order . '***' . date("Ymd", strtotime($shipment->order->order_date)) . '**' . $shipment->order_number . "\n" .
                  'N1*SF*' . $ex[0] . '*92*' . $ex[0] . "\n";
                  
        foreach ($shipment->items as $item) {
            $info = explode('*', $item->edi_id);
            if (!isset($info[1])) {
              Log::error('Amazon DF: edi id not well formed ' . $shipment->unique_order_id);
              return false;
            }
            $notice .=  'HL*' . ++$level . "**I\n" .
                        'LIN*' . $info[0] . '*SK*' . $info[1] . "\n" .
                        'SN1*' . $info[0] . '*' . $item->item_quantity . '*EA**'  . $item->item_quantity . '*EA**IA' ."\n" .
                        'MAN*R*' . sprintf('%09d', str_replace('-', '', $shipment->unique_order_id)) ."\n";
        }
        
        $notice .=  'HL*' . ++$level . "**P\n" .
                    'TD1******Z*' . $shipment->actual_weight . "*LB\n" .
                    'TD5**92*' . $ex[1] . "\n" .
                    'MAN*R*' . sprintf('%09d', str_replace('-', '', $shipment->unique_order_id)) . '**SM*' . $shipment->tracking_number . "\n" .
                    'DTM*ZZZ*' . gmdate("Ymd", strtotime($shipment->transaction_datetime)) .  "\n" . //'*' . gmdate("Hi", strtotime($shipment->transaction_datetime)) 
                    "DTM*011*20$date\n" .
                    'CTT*' . count($shipment->items) . '*' . $shipment->items->sum('item_quantity') . "\n" .
                    'SE*' . ((count($shipment->items) * 4) + 15) . '*' . 
                            sprintf('%09d', str_replace('-', '', $shipment->unique_order_id)) . "\n" .
                    "GE*1*$group_num\n" .
                    "IEA*1*$control_num\n\n";
        
        $filename = $shipment->order->order_id . '_856_' . $datetime_unique . '.edi';
        $file = fopen(storage_path() . $this->dir . $store . '/upload/' . $filename, "w");
        fwrite($file, $notice);
        fclose($file);
        
        $this->upload($store);
        
      }
      
      return;
    }
    
    public function exportShipments($store, $shipments) {
      
    }
    
    public function upload ($store) 
    {
        try { 
            $sftp = new SFTPConnection('sftp.amazonsedi.com', 2222, 'id_rsa'); 
            $sftp->login($this->setup[$store]['upload'], null, storage_path() . $this->dir . '.ssh/id_rsa.pub', storage_path() . $this->dir . '.ssh/id_rsa');   
        } catch (\Exception $e) { 
            Log::error('Amazon_DF upload: SFTP connection error ' . $e->getMessage()); 
            return FALSE; 
        } 
        
        try { 
            $file_list = $sftp->uploadDirectory('/upload/', $this->dir . $store . '/upload/');
        } catch (\Exception $e) { 
            Log::error('Amazon_DF upload: SFTP upload error ' . $e->getMessage()); 
            return FALSE; 
        } 
        
        //move files to out directory
        foreach ($file_list as $file) {
          try {
            
            rename(storage_path() . $this->dir . $store . '/upload/' . $file, storage_path() . $this->dir . $store . '/out/' . $file);
            
          } catch (\Exception $e) {
            Log::error('Amazon DF upload: File rename Error ' . $e->getMessage()); 
          }
        }
        
        return;
    }
    
    private $lookup = array(        'UPS_GR_RES'        => ['UP',   'S_GROUND'],
                                    'UPS_GR_COM'        => ['UP',   'S_GROUND'],
                                    'UPS_NEXT'          => ['UP',   'S_AIR_1DAY'],
                                    'UPS_NEXT_COM'      => ['UP',   'S_AIR_1DAY'],
                                    'UPS_NXT_SVR'       => ['UP',   'S_AIR_1DAYSAVER'],
                                    'UPS_NXT_SVR_COM'   => ['UP',   'S_AIR_1DAYSAVER'],
                                    'UPS_2ND'           => ['UP',   'S_AIR_2DAY'],
                                    'UPS_2ND_COM'       => ['UP',   'S_AIR_2DAY'],
                                    'UPS_DOM_3DAY_COM'  => ['UP',   'S_3DAYSELECT'],
                                    'UPS_DOM_3DAY_RES'  => ['UP',   'S_3DAYSELECT'],
                                    'FEDEX_NEXT_PRI'    => ['FX', '_PRIORITY_OVERNIGHT'],
                                    'FEDEX_SECOND'      => ['FX', '_FEDEX_2_DAY'],
                                    'FEDEX_3DAY'        => ['FX', '_FEDEX_3_DAY'],
                                    'FEDEX_NEXT_STD'    => ['FX', '_STANDARD_OVERNIGHT'],
                                ); 
    
}
