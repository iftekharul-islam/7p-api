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
use App\Ship;
use App\AmazonFC;

class Amazon_VC extends StoreInterface 
{ 
    protected $dir = '/EDI/Amazon/';
    
    protected $setup = ['NATJX' => ['download' => '3NJNC1RRUAFKZ', 
                                    'upload' => '2COKKPI3XERRZ', 
                                    'ISA' => '068229863', 
                                    'ISA_TYPE' => '01',
                                    'name' => 'Natico Originals',
                                    'AMZISA' => 'AMAZON']]; 
    
    public function importCsv($store, $file) {
      //
    }
    
    public function getInput($store, $files = null) 
    {   
      if ($files == null) {
        Log::info('Amazon VC: contacting via SFTP ' . $store);
        
        try { 
            $sftp = new SFTPConnection('sftp.amazonsedi.com', 2222, 'id_rsa'); 
            $sftp->login($this->setup[$store]['download'], null, storage_path() . $this->dir . '.ssh/id_rsa.pub', 
                          storage_path() . $this->dir . '.ssh/id_rsa');
        } catch (\Exception $e) { 
            Log::error('Amazon_VC getInput: SFTP connection error ' . $e->getMessage()); 
            return FALSE; 
        } 
         
        try { 
            $files = $sftp->downloadFiles('/download/', $this->dir . $store . '/in/');
        } catch (\Exception $e) { 
            Log::error('Amazon_VC getInput: SFTP download error ' . $e->getMessage()); 
            return FALSE; 
        } 
      }
      
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
             Log::error('Amazon_VC : Order number already in Database ' . $purchase_order["BEG03"]); 
             continue;
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
      
      $customer->order_id = $data["BEG03"];
      $customer->ship_first_name = $data["ST-N104"];
      
      if (strlen($data["ST-N104"]) == 4) {
        $fc = AmazonFC::where('code', $data["ST-N104"])->first();
        
        if ($fc) {
          $customer->ship_full_name = $fc->name;
          $customer->ship_last_name = 'Fufillment Center';
          $customer->ship_address_1 = $fc->address_1;
          $customer->ship_address_2 = $fc->address_2;
          $customer->ship_city = $fc->city;
          $customer->ship_state = $fc->state;
          $customer->ship_zip = $fc->zip;
          $customer->ship_country = $fc->country;
          $customer->ship_phone = '0000000000';
        }
      }
      
      $customer->bill_email = '';
      $customer->bill_company_name = 'Amazon VC';
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
      $order->purchase_order = $data["ST-N104"];
      $order->item_count = $data["CTT01"];
      $order->shipping_charge = '0';
      $order->tax_charge = '0';
      $order->total = '0'; 
      $order->order_date = substr($data["BEG05"], 0, 4) . '-' . substr($data["BEG05"], 4, 2) . '-' . substr($data["BEG05"], 6, 2) . date(" H:i:s");
      $order->store_id = $store; 
      $order->store_name = 'Amazon VC';
      $order->order_status = 4;      
      $order->ship_state = $data["ST-N402"] ?? null;
      $order->carrier = 'UP';
      $order->method = 'S_GROUND';
      $customer->save();
      
      try {
        $order->customer_id = $customer->id;
      } catch ( \Exception $exception ) {
        Log::error('Failed to insert customer id in Amazon VC');
      }
      
      $order->save();
      
      try {
        $order_5p = $order->id;
      } catch ( \Exception $exception ) {
        $order_5p = '0';
        Log::error('Failed to get 5p order id in Amazon VC');
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
          $product->product_name = 'Amazon VC PRODUCT NOT FOUND';
          $product->save();
          
          
        }
        
        $item = new Item();
        $item->order_id = $order->order_id;
        $item->store_id = $store; 
        $item->data_parse_type = 'EDI';
        
        $item->item_quantity = $data['PO1' . $ctr . '-02'];
        $item->item_unit_price = $data['PO1' . $ctr . '-04']; 
        
        
        $item->item_code = $product->product_model;
        
        $item->item_description = $product->product_name;
        $item->item_id = $product->id_catalog;
        $item->item_thumb = $product->product_thumb;
        $item->item_url = $product->product_url;
        
        $item->item_option = json_encode([]);
        $item->edi_id = $data['PO1' . $ctr . '-07'];
        
        $item->save();
        
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
      
        $ack .= 'BAK*00*AD*' . $data["BEG03"] . "*20$date\n";
        
        $ctr = '01';
        $total_qty = 0;
        
        while (isset($data['PO1' . $ctr . '-01'])) {
          $ack .= 'PO1' . '*' . $data['PO1' . $ctr . '-01'] . '*' . $data['PO1' . $ctr . '-02'] . '*EA*' . $data['PO1' . $ctr . '-04'] . 
                    '**VN*' . $data['PO1' . $ctr . '-07'] . "\n" .
                    'ACK*IA*' . $data['PO1' . $ctr . '-02'] . '*EA*010*' . $ship_date .  "\n";                    
          $total_qty += intval($data['PO1' . $ctr . '-02']);
          $ctr = sprintf('%02d', intval($ctr) + 1);
        }
              
        $ack .= 'DTM*067*' . date("Ymd", strtotime('+5 days')) .  "\n" .
                'CTT*' . $data["CTT01"] . '*' . $total_qty . "\n" .
                'SE*' . (((intval($ctr) - 1) * 2) + 5) . '*' . sprintf('%09d', $result[0]) . "\n" .
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
      
        $shipment = Ship::with('order.customer')
                        ->where('unique_order_id', $unique_order_id)
                        ->first();
        
        if (!$shipment) {
          Log::error('Amazon VC: Shipment not found for SSCC label ' . $unique_order_id);
          return null;
        }
      
        $customer = $shipment->order->customer;
        
        $name =  $customer->ship_full_name;
        $address1 = $customer->ship_address_1; 
        $address2 = $customer->ship_address_2; 
        $city = $customer->ship_city; 
        $state = $customer->ship_state; 
        $zip = $customer->ship_zip; 
        $country = $customer->ship_country; 
        $PO = $shipment->order->short_order; 
        $date = date("n/d/y"); 
        
        $count = $shipment->package_count;
        $SSCC = '(00) 00633944 ' . str_pad(substr(str_replace('-','',$unique_order_id),-7),7,"0",STR_PAD_LEFT);
        $num_sscc = str_replace(['(',')',' '], '', $SSCC);
        $checkdigit = $this->ssccCheckdigit($num_sscc);
        $SSCC .= ' ' . $checkdigit;
        $shipment->transaction_id = $num_sscc . $checkdigit;
        $carrier = substr($shipment->mail_class, 0, strpos($shipment->mail_class, ' '));
        $tracking = $shipment->shipping_id;
        
        $zpl = '';
        
        if ($count == 1 && count($shipment->items) == 1 && 
            $shipment->items->first()->inventoryunit && count($shipment->items->first()->inventoryunit) == 1 &&
            $shipment->items->first()->inventoryunit->first()->inventory) {
              
          $UPC = $shipment->items->first()->inventoryunit->first()->inventory->upc;
          
        } else {
          $UPC = 'Mixed/UPCs';
        }
        
        if ($count == 1) {
          $QTY = 'QTY: ' . $shipment->items->sum('item_quantity');
        } else {
          $QTY = '';
        }
        
        for ($i = 1; $i <= $count; $i++) {echo $i;
            $zpl .= "^XA";
            $zpl .= "^FO30,100^GB750,950,5^FS";
            $zpl .= "^FO75,130^A0,35,30^FDSHIP FROM:^FS";
            $zpl .= "^FO90,160^A0,35,30^FD" . $this->setup[$store]['name'] . "^FS";
            $zpl .= "^FO90,190^A0,35,30^FD575 Underhill blvd^FS";
            $zpl .= "^FO90,220^A0,35,30^FDSuite 325^FS";
            $zpl .= "^FO90,250^A0,35,30^FDSyosset, NY 11791 ^FS";
            $zpl .= "^FO375,100^GB1,200,5^FS";
            $zpl .= "^FO400,130^A0,35,30^FDSHIP TO:^FS";
            $zpl .= "^FO400,160^A0,35,30^FD$name^FS";
            $zpl .= "^FO400,190^A0,35,30^FD$address1^FS";
            $zpl .= "^FO400,220^A0,35,30^FD$address2^FS";
            $zpl .= "^FO400,250^A0,35,30^FD$city,$state $zip^FS";
            $zpl .= "^FO35,300^GB740,1,5^FS";
            $zpl .= "^FO450,300^GB1,150,5^FS";
            $zpl .= "^FO480,350^A0,40,30^FD$carrier^FS";
            $zpl .= "^FO480,380^A0,35,25^FD$tracking^FS";
            $zpl .= "^FO35,450^GB740,1,5^FS";
            $zpl .= "^FO75,480^A0,40,30^FDPURCHASE ORDER: $PO^FS";
            $zpl .= "^FO75,535^A0,40,30^FDUPC: $UPC^FS";
            $zpl .= "^FO75,575^A0,40,30^FD$QTY^FS";
            $zpl .= "^FO75,615^A0,40,30^FDCARTON $i of $count ^FS";
            $zpl .= "^FO350,540^BY3^BCN,120,Y,N,Y,N^FD$PO^FS";
            $zpl .= "^FO35,700^GB740,1,5^FS";
            $zpl .= "^FO250,745^A0,30,20^FDSerial Shipping Container Code (SSCC)^FS";
            $zpl .= "^FO50,785^BY4^BCN,175,Y,N,Y,N^FD$num_sscc$checkdigit^FS";
            $zpl .= "^XZ";
        }
        
        $zpl = str_replace("'", " ", $zpl);
        $zpl = str_replace('"', " ", $zpl);
        $zpl = str_replace('&quot;', " ", $zpl);
        
        $shipment->save();
        
        return ['', $zpl]; 

    }
    
    private function ssccCheckdigit($code)
    {
        $odd_total  = 0;
        $even_total = 0;
     
        for($i=0; $i<11; $i++) {
            if((($i+1)%2) == 0) {
                $even_total += $code[$i];
            } else {
                $odd_total += $code[$i];
            }
        }
     
        $sum = (3 * $odd_total) + $even_total;
        $check_digit = $sum % 10;
     
        return ($check_digit > 0) ? 10 - $check_digit : $check_digit;
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
        
        if ($shipment->order->carrier == 'UP') {
          $scac = 'UPSN';
        } else if ($shipment->order->carrier == 'FX') {
          $scac = 'FDEG';
        } else if ($shipment->order->carrier == 'US') {
          $scac = 'USPS';
        } else {
          $scac = 'UPSN';
        }
        
        $ISA_1 = $this->setup[$store]['ISA_TYPE'] . '*' . str_pad($this->setup[$store]['ISA'], 15, ' ', STR_PAD_RIGHT);
        
        $ISA_2 = str_pad($this->setup[$store]['AMZISA'], 15, ' ', STR_PAD_RIGHT);
        
        $notice = "ISA*00*          *00*          *$ISA_1*ZZ*$ISA_2*$date*$time*^*00501*$control_num*0*P*~\n" .
                  'GS*SH*' . $this->setup[$store]['ISA'] . '*' . $this->setup[$store]['AMZISA'] . "*20$date*$time*$group_num*X*005010\n" .
                  "ST*856*" . sprintf('%09d', str_replace('-', '', $shipment->unique_order_id)) . "\n" .
                  'BSN*00*' . str_replace('-', '', $shipment->unique_order_id) . "*20$date*$time*0001\n" .
                  "HL*1**S\n" .
                  'TD1*CTN*' . $shipment->package_count . '****G*' . $shipment->actual_weight . "*LB\n" .
                  'TD5**2*' . $scac . "\n" .
                  "DTM*011*20$date\n" .
                  // "DTM*017*20$date\n" .
                  'N1*SF*' . $this->setup[$store]['name'] . '.*92*' . $store . "\n" .
                  "N4*SYOSSET*NY*11791*US\n" .
                  'N1*ST*' . $this->setup[$store]['AMZISA'] . '*15*' . $shipment->order->purchase_order . "\n" .
                  'N4*' .  $customer->ship_city . '*' . $customer->ship_state . '*' . 
                            $customer->ship_zip . '*' . Shipper::getcountrycode($customer->ship_country) . "\n" .
                  "HL*2*1*O\n" .
                  'PRF*' . $shipment->order->short_order . "\n" .
                  "HL*3*2*P\n" .
                  'REF*CN*' . $shipment->tracking_number . "\n" .
                  'MAN*GM*' . $shipment->transaction_id . "\n";
                  
                  foreach ($shipment->items as $item) {
                    
                    if ($item->edi_id != null) {
                      $sku = $item->edi_id;
                    } else {
                      $sku = $item->child_sku;
                    }
                    
                    $notice .=  "HL*4*3*I\n" .
                                'LIN**VN*' . $sku . "\n" .
                                'SN1**' . $item->item_quantity . "*EA\n";
                  }
                  
                  $notice .=  'CTT*' . (((count($shipment->items)) * 2) + 2) . "\n" .
                              'SE*' . (((count($shipment->items)) * 3) + 20) . '*' . 
                                      sprintf('%09d', str_replace('-', '', $shipment->unique_order_id)) . "\n" .
                              "GE*1*$group_num\n" .
                              "IEA*1*$control_num\n\n";
                              
        $filename = $shipment->order->short_order . '_856_' . $datetime_unique . '.edi';
        
        try {
          $file = fopen(storage_path() . $this->dir . $store . '/upload/' . $filename, "w");
          fwrite($file, $notice);
          fclose($file);
        } catch (\Exception $e) {
          Log::error('Amazon VC Shipnotification: ' . $e->getMessage());
        }
        
        $this->upload($store);
        
        $time = date("Hi");
        $control_num = sprintf('%09d', rand(1000,10000));
        $group_num = sprintf('%09d', rand(1000,10000));
        
        $invoice ="ISA*00*          *00*          *$ISA_1*ZZ*$ISA_2*$date*$time*U*00401*$control_num*1*P*>\n" .
                  'GS*IN*' . $this->setup[$store]['ISA'] . '*' . $this->setup[$store]['AMZISA'] . "*20$date*$time*$group_num*X*004010\n" .
                  "ST*810*" . sprintf('%09d', str_replace('-', '', $shipment->unique_order_id)) . "\n" .
                  "BIG*20$date*" . $shipment->unique_order_id  . '*' . 
                          date("Ymd", strtotime($shipment->order->order_date)) . '*' . 
                          $shipment->order->short_order . "\n" .
                  'CUR*BT*USD' . "\n" .
                  'N1*RI*' . $this->setup[$store]['name'] . "\n" .
                  'N3*575 Underhill Blvd*Suite 325' . "\n" .
                  'N4*Syosset*NY*11791*US' . "\n" .
                  'N1*ST*' . $customer->ship_first_name . "\n";
                  
                  if ($customer->ship_address_1 && $customer->ship_address_1 != '') {
                    $invoice .= 'N3*' . $customer->ship_address_1 . "\n";
                  }
                  
                  $invoice .= 'N4*' .  $customer->ship_city . '*' . $customer->ship_state . '*' . 
                                      $customer->ship_zip . '*' . Shipper::getcountrycode($customer->ship_country) . "\n".
                              'ITD*01*3' . "\n";
                  
                  $total = 0;
                  $sum_qty = 0;
                  $count = 1;
                  
                  foreach ($shipment->items as $item) {
                    
                    if ($item->edi_id != null) {
                      $sku = $item->edi_id;
                    } else {
                      $sku = $item->child_sku;
                    }
                    
                    $invoice .=  'IT1*' . $count . '*' . $item->item_quantity . '*EA*' . $item->item_unit_price . '*NT*VN*' . $sku . '***PO*' . $item->order_id . "\n";
                    $total += ($item->item_quantity * $item->item_unit_price);
                    $sum_qty += $item->item_quantity;
                    $count++;
                  }
                  
                  $invoice .= 'TDS*' . intval($total * 100) . "\n" .
                              'CTT*' . $count . '*' . $sum_qty . "\n" .
                              'SE*' . (((count($shipment->items)) * 2) + 10) . '*' . 
                                      sprintf('%09d', str_replace('-', '', $shipment->unique_order_id)) . "\n" .
                              "GE*1*$group_num\n" .
                              "IEA*1*$control_num\n\n";
        
        $filename = $shipment->order->short_order . '_810_' . $datetime_unique . '.edi';
        
        try {
          $file = fopen(storage_path() . $this->dir . $store . '/upload/' . $filename, "w");
          fwrite($file, $invoice);
          fclose($file);
        } catch (\Exception $e) {
          Log::error('Amazon VC Shipnotification invoice: ' . $e->getMessage());
        }
        
        $this->upload($store);
      }

      return;
    }
    
    public function upload ($store) 
    {
        try { 
            $sftp = new SFTPConnection('sftp.amazonsedi.com', 2222, 'id_rsa'); 
            $sftp->login($this->setup[$store]['upload'], null, storage_path() . $this->dir . '.ssh/id_rsa.pub', storage_path() . $this->dir . '.ssh/id_rsa');   
        } catch (\Exception $e) { 
            Log::error('Amazon_VC upload: SFTP connection error ' . $e->getMessage()); 
            return FALSE; 
        } 
        
        try { 
            $file_list = $sftp->uploadDirectory('/upload/', $this->dir . $store . '/upload/');
        } catch (\Exception $e) { 
            Log::error('Amazon_VC upload: SFTP upload error ' . $e->getMessage()); 
            return FALSE; 
        } 
        
        //move files to out directory
        foreach ($file_list as $file) {
          try {
            
            rename(storage_path() . $this->dir . $store . '/upload/' . $file, storage_path() . $this->dir . $store . '/out/' . $file);
            
          } catch (\Exception $e) {
            Log::error('Amazon VC upload: File rename Error ' . $e->getMessage()); 
          }
        }
        
        return;
    }
    
    public function exportShipments($store, $shipments) {
      
    }
}
