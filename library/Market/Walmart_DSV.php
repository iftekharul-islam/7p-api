<?php 
 
namespace Market; 
 
use App\Walmart_EDI; 
use App\Batch; 
use App\Ship; 
use App\Product; 
use App\Order;
use App\Item;
use App\Customer;
use App\Option;
use Illuminate\Support\Facades\Log; 
use Monogram\Helper;
 use Monogram\SFTPConnection;
 
class Walmart_DSV extends StoreInterface 
{ 
     
    protected $upload_dir = '/EDI/Walmart_DSV/upload/'; 
    protected $upload_archive_dir = '/EDI/Walmart_DSV/out/'; 
    protected $download_dir = '/home/walmart/in/'; 
    protected $download_archive_dir = '/EDI/Walmart_DSV/in/'; 
    protected $url = 'bedrock.walmart.com';
    protected $port = '22';
    protected $username = 'dtowin';
    protected $password = 'D3al20W1n';
    protected $remote_upload = '/inbound/';
    
    public function importCsv($store, $file) {
      //
    }
    
    public function getInput($store) { 
        
        Log::info('Looking for Walmart DSV files');
        
        $file_list =  array_diff(scandir($this->download_dir), array('..', '.')); 
        $confirm_files = array(); 

        foreach ($file_list as $file) { 
             
            try { 
              
             $input = file_get_contents($this->download_dir . $file); 
             $array = []; 
            
             if (in_array(substr($file, -3), ['wff','txt'])) { 
                  
                 //$fileID = $this->processAP($input); 
                 //$confirm_files[] = [$fileID, 'FAP']; 
                
             } elseif ($this->isXML($input)) { 
                  
                 $xml_message = $this->isXML($input); 
                  
                 if ($xml_message == true) { 
                   
                     $doc = simplexml_load_string($input); 
                     $json = json_encode($doc); 
                     $array = json_decode($json,TRUE); 
                      
                     if ($array['WMIFILEHEADER']['@attributes']['FILETYPE'] == 'FOR') { 
                       $this->processOrder($array);
                       $confirm_files[] = [$array['WMIFILEHEADER']['@attributes']['FILEID'], 'FOR'];
                     } elseif ($array['WMIFILEHEADER']['@attributes']['FILETYPE'] == 'FFC') { 
                       $this->processConfirm($array); 
                     } elseif ($array['WMIFILEHEADER']['@attributes']['FILETYPE'] == 'FOC') { 
                       $this->processCancel($array);
                       $confirm_files[] = [$array['WMIFILEHEADER']['@attributes']['FILEID'], 'FOC']; 
                     } elseif ($array['WMIFILEHEADER']['@attributes']['FILETYPE'] == 'FFE') {  
                       $this->processError($array); 
                       $confirm_files[] = [$array['WMIFILEHEADER']['@attributes']['FILEID'], 'FFE'];
                     } else { 
                       Log::error('Walmart getFiles: Unrecognized File Type ' . $array['WMIFILEHEADER']['@attributes']['FILETYPE']); 
                       $this->createErrorFile(substr($file, -33, -4), substr($file, 0, -34), 'XML ERROR', $xml_message); 
                     } 
                     
                     rename($this->download_dir . $file, storage_path() . $this->download_archive_dir . $file);
                     
                 } else { 
                     Log::error('Walmart getFiles: XML error ' . $xml_message . ' in file ' . $file ); 
                     $this->createErrorFile(substr($file, -33, -4), substr($file, 0, -34), 'XML ERROR', $xml_message); 
                 } 
                  
             } else { 
                 Log::error('Walmart getFiles: XML error in file ' . $file ); 
                 $this->createErrorFile(substr($file, -33, -4), substr($file, 0, -34), 'XML ERROR', 'XML Error'); 
             } 
            
           } catch (\Exception $e) { 
               Log::error('Walmart getFiles: error processing file ' . $file . ' - ' . $e->getMessage()); 
               $this->createErrorFile(substr($file, -33, -4), substr($file, 0, -34), 'ERROR', $e->getMessage());
           } 
         }           
        
        if (count($confirm_files) > 0) {
          $this->createConfirmFile($confirm_files); 
        }
        
        $this->upload();
        
        return true;
   } 
    
   private function isXML($xml){ 
      
       libxml_use_internal_errors(true); 

       $doc = new \DOMDocument('1.0', 'utf-8'); 
       $doc->loadXML($xml); 

       $errors = libxml_get_errors(); 

       if(empty($errors)){ 
           return true; 
       } 

       $error = $errors[0]; 
       if($error->level < 3){ 
           return true; 
       } 

       $explodedxml = explode("r", $xml); 
       $badxml = $explodedxml[($error->line)-1]; 

       $message = $error->message . ' at line ' . $error->line . '. Bad XML: ' . htmlentities($badxml); 
       return $message; 
   } 
    
    
   private function processAP($contents) { 
     $lines = explode("\n", $contents); 
      
     foreach ($lines as $line) { 
       $array = explode('|', $line); 
        
       if ($array[0] == 'FH') { 
         $file_id = $array[1]; 
       } elseif ($array[0] == 'AP') { 
         // 
       } 
     } 
      
     return $file_id; 
   } 


   private function processOrder($array) { 
    
     Log::info('processing ' . $array['WMIFILEHEADER']['@attributes']['FILEID']); 
     $orders = $array['WMIORDERREQUEST']['OR_ORDER']; 
     
     $status1 = array(); 
     $status2 = array();
     
     $break_order_loop = FALSE; 
     
     foreach ($orders as $order) {
         
         if ($break_order_loop == TRUE) { 
           break; 
         } 
         
         $order_info = array();
         
         if (array_key_exists('REQUESTNUMBER', $order)) {
           $order = $array['WMIORDERREQUEST']['OR_ORDER'];
           $break_order_loop = TRUE; 
         }
         
         $previous_order = Order::with('items')
                           ->where('order_id', $order['@attributes']['REQUESTNUMBER'])
                           ->where('is_deleted', '0')
                           ->orderBy('created_at', 'DESC')
                           ->first();
         
         if ( $previous_order ) {
          Log::error('Walmart_DSV : Order number already in Database ' . $order['@attributes']['REQUESTNUMBER']); 
          continue;
         } 
         
         $order_info['phone'] = $order['OR_SHIPPING']['OR_PHONE']['@attributes']['PRIMARY']; 
         $order_info['ship_address'] = $order['OR_SHIPPING']['OR_POSTAL']['@attributes'];
         $order_info['bill_address'] = $order['OR_BILLING']['OR_POSTAL']['@attributes'];
         if (isset($order['OR_BILLING']['OR_EMAIL'])) {
           $order_info['email'] = $order['OR_BILLING']['OR_EMAIL']; 
         } else {
           $order_info['email'] = '';
         }
         $order_info['order_id'] = $order['@attributes']['REQUESTNUMBER']; 
         $order_info['order_date'] = $order['OR_DATEPLACED']['@attributes']['YEAR'] . '-' .  
                                     $order['OR_DATEPLACED']['@attributes']['MONTH'] . '-' .  
                                     $order['OR_DATEPLACED']['@attributes']['DAY'] . date(" H:i:s"); 
         if (isset($order['OR_SHIPPING']['OR_EXPECTEDSHIPDATE'])) {
             $order_info['ship_date'] = $order['OR_SHIPPING']['OR_EXPECTEDSHIPDATE']['@attributes']['YEAR'] . '-' .  
                                         $order['OR_SHIPPING']['OR_EXPECTEDSHIPDATE']['@attributes']['MONTH'] . '-' .  
                                         $order['OR_SHIPPING']['OR_EXPECTEDSHIPDATE']['@attributes']['DAY']; 
         }
         $order_info['ship_code'] = $order['OR_SHIPPING']['@attributes']['CARRIERMETHODCODE']; 
         $order_info['total'] = 0;
         
         $break_loop = FALSE; 
        
         foreach ($order['OR_ORDERLINE'] as $line) {  
            
           if ($break_loop == TRUE) { 
             break; 
           } 
          
           try { 
              
               if (array_key_exists('LINENUMBER', $line)) { 
                 $line = $order['OR_ORDERLINE']; 
                 $break_loop = TRUE; 
               } 
                            
               $row = new Walmart_EDI; 
               $row->STORENUMBER = isset($order['OR_SHIPPING']['@attributes']['STORENUMBER']) ? $order['OR_SHIPPING']['@attributes']['STORENUMBER'] : null; 
               $row->FILEID = $array['WMIFILEHEADER']['@attributes']['FILEID']; 
               $row->REQUESTNUMBER = $order['@attributes']['REQUESTNUMBER']; 
               $row->ORDERNUMBER = $order['@attributes']['ORDERNUMBER']; 
               $row->DATEPLACED = $order['OR_DATEPLACED']['@attributes']['YEAR'] . '-' .  
                                 $order['OR_DATEPLACED']['@attributes']['MONTH'] . '-' .  
                                 $order['OR_DATEPLACED']['@attributes']['DAY']; 
               $row->METHODCODE = $order['OR_SHIPPING']['@attributes']['METHODCODE']; 
               $row->CARRIERMETHODCODE = $order['OR_SHIPPING']['@attributes']['CARRIERMETHODCODE']; 
               $row->TOGETHERCODE = $order['OR_SHIPPING']['@attributes']['TOGETHERCODE']; 
               $row->EXPECTEDSHIPDATE = $order['OR_SHIPPING']['OR_EXPECTEDSHIPDATE']['@attributes']['YEAR'] . '-' .  
                                         $order['OR_SHIPPING']['OR_EXPECTEDSHIPDATE']['@attributes']['MONTH'] . '-' .  
                                         $order['OR_SHIPPING']['OR_EXPECTEDSHIPDATE']['@attributes']['DAY']; 
               $row->LINENUMBER = $line['@attributes']['LINENUMBER']; 
               $row->LINEPRICE = $line['@attributes']['LINEPRICE']; 
               $row->ITEMNUMBER = $line['OR_ITEM']['@attributes']['ITEMNUMBER']; 
               $row->UPC = $line['OR_ITEM']['@attributes']['UPC']; 
               $row->SKU = $line['OR_ITEM']['@attributes']['SKU']; 
               $row->DESCRIPTION = $line['OR_ITEM']['@attributes']['DESCRIPTION']; 
               $row->QUANTITY = $line['OR_ITEM']['@attributes']['QUANTITY']; 
               $row->RETAIL = $line['OR_PRICE']['@attributes']['RETAIL']; 
               $row->TAX = $line['OR_PRICE']['@attributes']['TAX']; 
               $row->SHIPPING = $line['OR_PRICE']['@attributes']['SHIPPING']; 
               $row->AMOUNT = $line['OR_COST']['@attributes']['AMOUNT']; 
               
               if (isset($line['OR_DYNAMICDATA']['@attributes']['VALUE'])) {
                 
                 $row->CONFIGID = $line['OR_DYNAMICDATA']['@attributes']['VALUE'];
                 
                 $status1[] = [$order['@attributes']['REQUESTNUMBER'], $line['@attributes']['LINENUMBER'], 'LI', $line['OR_ITEM']['@attributes']['QUANTITY']]; 
                 $status2[] = [$order['@attributes']['REQUESTNUMBER'], $line['@attributes']['LINENUMBER'], 'LH', $line['OR_ITEM']['@attributes']['QUANTITY']];
                 
               } else {
                 
                 $row->CONFIGID = 'CANCEL';
                 
                 $status1[] = [$order['@attributes']['REQUESTNUMBER'], $line['@attributes']['LINENUMBER'], 'LU', $line['OR_ITEM']['@attributes']['QUANTITY']]; 
                
               } 
               
               $row->save(); 
              
               $order_info['total'] += $line['OR_COST']['@attributes']['AMOUNT'];
               $order_info['items'][] = $row->toArray();
               
          } catch (\Exception $e) { 
             
              Log::error('Walmart getFiles: Process file error ' . $array['WMIFILEHEADER']['@attributes']['FILEID'] . ' - ' .  $e->getMessage()); 
              $status1[] = [$order['@attributes']['REQUESTNUMBER'], $line['@attributes']['LINENUMBER'], 'LU', $line['OR_ITEM']['@attributes']['QUANTITY']]; 
               
          } 
          
          $this->insertOrder($order_info);
          
        } 
    }

    $this->createStatusFile($status1); 
    sleep(1);
    $this->createStatusFile($status2); 
    return true;
  } 
  
  private function insertOrder($input) { 

    if ($input['ship_address']['NAME'] == 'PICKUP AT STORE') {
      $name = 'PICKUP AT STORE - WALMART';
      $last_name = (strpos($input['ship_address']['ADDRESS2'], ' ') === false) ? '' : preg_replace('#.*\s([\w-]*)$#', '$1', $input['ship_address']['ADDRESS2']);
      $first_name = trim( preg_replace('#'.$last_name.'#', '', $input['ship_address']['ADDRESS2'] ) );
    } else {
      $name = $input['ship_address']['NAME'];
      $last_name = (strpos($input['ship_address']['NAME'], ' ') === false) ? '' : preg_replace('#.*\s([\w-]*)$#', '$1', $input['ship_address']['NAME']);
      $first_name = trim( preg_replace('#'.$last_name.'#', '', $input['ship_address']['NAME'] ) );
      
    }
    // -------------- Customers table data insertion started ----------------------//
    $customer = new Customer();
    $customer->order_id = $input['order_id'];
    $customer->ship_full_name = $name;
    $customer->ship_last_name = $last_name;
    $customer->ship_first_name = $first_name;
    $customer->ship_address_1 = $input['ship_address']['ADDRESS1'];
    $customer->ship_address_2 = isset($input['ship_address']['ADDRESS2']) ? $input['ship_address']['ADDRESS2'] : null;
    $customer->ship_city = $input['ship_address']['CITY'];
    $customer->ship_state = Helper::stateAbbreviation($input['ship_address']['STATE']);
    $customer->ship_zip = $input['ship_address']['POSTALCODE'];
    $customer->ship_country = $input['ship_address']['COUNTRY'];
    $customer->ship_phone = $input['phone'];
    $customer->ship_email = $input['email'];
    
    //$customer->shipping = getShippingMethod($input['CARRIERMETHODCODE']);
    
    $customer->bill_full_name = $input['bill_address']['NAME'];
    $customer->bill_last_name = (strpos($input['bill_address']['NAME'], ' ') === false) ? '' : preg_replace('#.*\s([\w-]*)$#', '$1', $input['bill_address']['NAME']);
    $customer->bill_first_name = trim( preg_replace('#'.$customer->bill_last_name.'#', '', $input['bill_address']['NAME'] ) );
    $customer->bill_address_1 = $input['bill_address']['ADDRESS1'];
    $customer->bill_address_2 = isset($input['bill_address']['ADDRESS2']) ? $input['bill_address']['ADDRESS2'] : null;
    $customer->bill_city = $input['bill_address']['CITY'];
    $customer->bill_state = $input['bill_address']['STATE'];
    $customer->bill_zip = $input['bill_address']['POSTALCODE'];
    $customer->bill_country = $input['bill_address']['COUNTRY'];
    $customer->bill_phone = $input['phone'];
    $customer->bill_email = $input['email'];
    
    $customer->save();
    // -------------- Customers table data insertion ended ----------------------//
    // -------------- Orders table data insertion started ----------------------//
    $order = new Order();
    $order->order_id = $input['order_id'];
    try {
      $order->customer_id = $customer->id;
    } catch ( \Exception $exception ) {
      Log::error('Failed to insert customer id in Walmart_DSV');
    }
    $order->short_order = $input['order_id'];
    $order->item_count = count($input['items']);
    $order->shipping_charge = '0';
    $order->tax_charge = '0';
    $order->order_date = $input['order_date'];
    $order->store_id = 'wm-dsv01';
    $order->store_name = 'Walmart.com';
    $order->order_status = 4;
    $order->ship_state = Helper::stateAbbreviation($input['ship_address']['STATE']);
    if (isset($input['ship_date'])) {
      $order->ship_date = $input['ship_date'];
    }
    try {
      $ship_info = $this->lookup[$input['ship_code']];
      $order->carrier = $ship_info[0];
      $order->method = $ship_info[1];
    } catch (\Exception $e) {
      Log::error('Ship Code ' . $input['ship_code'] . ' not found in Walmart_DSV ' . $e->getMessage());
      $order->carrier = 'FX';
      $order->method = '_FEDEX_GROUND';
    }
    
    $order->save();
    
    try {
      $order_5p = $order->id;
    } catch ( \Exception $exception ) {
      $order_5p = '0';
      Log::error('Failed to get 5p order id in Walmart_DSV');
    }
    // -------------- Orders table data insertion ended ----------------------//
    // -------------- Items table data insertion started ------------------------//
    
    $total = 0;
    
    foreach ( $input['items'] as $EDI_item ) {
      
      $SKU = NULL;
      
      $info['id_catalog'] = null;
      $info['url'] = null;
      $info['description'] = null;
      $info['thumb'] = null;
      $info['item_options'] = [];
      $info['ip'] = null;
      
      if ($EDI_item['CONFIGID'] != NULL && $EDI_item['CONFIGID'] != 'CANCEL') {
        $attempts = 0;
        
        do {
          
          try {
            $info = $this->getPersonalization($EDI_item['CONFIGID']);
          } catch (\Exception $e) {
            $attempts++;
            sleep(90);
            Log::info('Walmart DSV insertOrder: Error getting personalization ' . $e->getMessage() . ' - attempt # ' . $attempts);
            continue;
          }
          
          break;
          
        } while($attempts < 10);
        
        if ($attempts == 10) {
          Log::error('Walmart DSV insertOrder: Failed getting personalization, Order ' . $order->order_id . ' - ' . $EDI_item['SKU']);
          $info['id_catalog'] = strtolower($EDI_item['SKU']);
          $info['description'] = $EDI_item['DESCRIPTION'];
          $info['item_options'] = json_encode([
                                                'error' => 'Magento Personalization site not responding',
                                                'configuration_id' => $EDI_item['CONFIGID']
                                              ]);
        }
        
      } else {
        Log::error('Walmart DSV: NO CONFIG ID FOR WALMART ORDER ' . $input['order_id']);
        $info['id_catalog'] = strtolower($EDI_item['SKU']);
        $info['description'] = $EDI_item['DESCRIPTION'];
        $info['item_options'] = json_encode(['error' => 'No configuration ID sent from Walmart']);
      }
      
      // -------------- Products table data insertion started ---------------------- //
      $product = Helper::findProduct($EDI_item['SKU']);
      
      if ($product == false) {
        $product = new Product();
        $product->id_catalog = $info['id_catalog'];
        $product->product_model = $EDI_item['SKU'];
        $product->product_url = $info['url'];
        $product->product_name = $info['description'];
        $product->product_thumb = $info['thumb'];
        $product->product_upc = $EDI_item['UPC'];
        $product->batch_route_id = Helper::getDefaultRouteId();
        $product->save();
      }
			// -------------- Products table data insertion ended ---------------------- //
      
      $item = new Item();
      $item->order_5p = $order->id;
      $item->order_id = $order->order_id;
      $item->store_id = 'wm-dsv01';
      $item->item_code = $EDI_item['SKU'];
      $item->item_description = $EDI_item['DESCRIPTION'];
      $item->item_quantity = $EDI_item['QUANTITY'];
      $item->item_unit_price = $EDI_item['AMOUNT'];
      $item->data_parse_type = 'XML';
      $item->item_option = $info['item_options'];
      $item->item_id =  $info['id_catalog'];
      $item->item_thumb = $info['thumb'];
      $item->item_url = $info['url'];
      $item->edi_id = $EDI_item['id'];
      $item->child_sku = Helper::getChildSku($item);
      
      $item->item_code = $product->product_model;
      
      if ($EDI_item['CONFIGID'] == 'CANCEL') {
        $item->item_status = 'cancelled';
      }
      
      $item->save();
      
      $total += $item->item_quantity * $item->item_unit_price;
      
      $options = Option::where('child_sku', $item->child_sku)
                        ->where('parent_sku', '!=', $product->product_model)
                        ->get();
      
      if (count($options) > 0) {
        foreach ($options as $option) {
          $option->parent_sku = $product->product_model;
          $option->save();
        }
      }
      
      if ($item->item_option == '[]') {
        $order->order_status = 15;
      } 
      
      $order->total = $total;
      $order->order_ip = $info['ip'];
      $order->save();
      
      // -------------- Items table data insertion ended ---------------------- //
    }
    
    $order->isCancelled();
    
    return;    
  }
  
  private function processCancel($array) { 
     
    $status = array(); 
    $break_loop = FALSE; 
     
    foreach ($array['WMIORDERCANCEL']['OC_LINECANCEL'] as $line) {  
       
      if ($break_loop == TRUE) { 
        break; 
      } 
       
      if (array_key_exists('REQUESTNUMBER', $line)) { 
          $line = $array['WMIORDERCANCEL']['OC_LINECANCEL']; 
          $break_loop = TRUE; 
      } 
       
      $requestnumber = $line['@attributes']['REQUESTNUMBER']; 
      $linenumber = $line['@attributes']['LINENUMBER']; 
      
      $EDI_items = Walmart_EDI::where('Walmart_EDI.REQUESTNUMBER', $requestnumber) 
                  ->where('Walmart_EDI.LINENUMBER', $linenumber) 
                  ->where('is_deleted' ,'0')
                  ->get(); 
      
      if (count($EDI_items) < 1) {
        Log::error('Walmart EDI record not found for cancel ' . $requestnumber . ' - ' . $linenumber);
      } elseif (count($EDI_items) > 1) {
        Log::error('Walmart EDI record not unique for cancel ' . $requestnumber . ' - ' . $linenumber);
      } else { 
        
        $EDI_item = $EDI_items[0];
        
        $items = Item::with('batch.station')
                    ->where('items.edi_id', $EDI_item->id)
                    ->where('order_id', $EDI_item->REQUESTNUMBER)
                    ->where('store_id', 'wm-dsv01')
                    ->get(); 
        
        foreach ($items as $item) {

           if ($item->item_status == 'cancelled') {
              
               Log::info('Walmart item already cancelled ' . $requestnumber . ' - ' . $linenumber);
              
               $EDI_item->cancel = '1'; 
               $EDI_item->save(); 
               $status[] = [$requestnumber, $linenumber, 'LC', $item->item_quantity]; 
               
           } else if ($item->item_status != 'shipped' && 
                ($item->batch_number == '0' || $item->batch->summary_date == NULL)) { 
               
               Log::info('Walmart EDI item cancelled ' . $requestnumber . ' - ' . $linenumber);
               
               $item->item_status = 'cancelled'; 
               
               Order::note('Item ' . $item->id . ' cancelled by Walmart', $item->order_5p);
               
               if ($item->batch_number != '0') {
                 Batch::note($item->batch_number, '', '8', 'Item ' . $item->id . ' cancelled and removed from batch');
                 $item->batch_number = '0';
                 $batch_number = $item->batch_number;
               }
               
               $item->save(); 
               
               if ($item->order->shippable_items && count($item->order->shippable_items) == 0) {
                 $item->order->order_status = 8;
                 $item->order->save();
               }
               
               Batch::isFinished($batch_number);
               
               $EDI_item->cancel = '1'; 
               $EDI_item->save(); 
               $status[] = [$requestnumber, $linenumber, 'LC', $item->item_quantity]; 
           }  
        }
        
      }
    } 
     
    if (count($status) > 0) { 
      $this->createStatusFile($status); 
      return true;
    }  
  } 

  private function processError($array) { 
     
    Log::error('Walmart processError: Process error file ' . $array['WMIFILEHEADER']['@attributes']['FILEID']); 
   
  } 

  private function processConfirm($array) { 
    // 
  } 

  private function createErrorFile ($error_file, $type, $message, $data) { 
   
    if (strlen($type) > 3) {
      
      switch ($type) {
        case 'WMI_Order_Req':
            $type = 'FOR';
            break;
        case 'WMI_ORDER_Cancel':
            $type = 'FOC';
            break;
        case 'WMI_Error':
            $type = 'FFE';
            break;
        case 'WMI_Confirm':
            $type = 'FFC';
            break;
        case 'WMI_DSVAP':
            $type = 'FII';
            break;
        }
    }
         
    $file_id = $this->createFileId(); 
     
    $xml = $this->createXmlHeader($file_id, 'FFE');
     
    $file = $xml->addChild('WMIFILEERROR'); 
    $file->addAttribute('FILEID', str_replace('_', '.', $error_file)); 
    $file->addAttribute('FILETYPE', $type); 

    $error = $file->addChild('FE_ERROR'); 
    $error->addAttribute('ERRORCODE', '0000'); 
     
    $error->addChild('FE_MESSAGE', $message); 
    $error->addChild('FE_DATA', $data); 
     
    $filename = $this->createFilename('WMI_Error_', $file_id); 
    $full_file = storage_path() . $this->upload_dir . $filename; 
    echo $xml->asXML($full_file); 
    return $filename; 

  } 
   
  private function createConfirmFile ($confirm_files) { 
     
    $file_id = $this->createFileId(); 
     
    $xml = $this->createXmlHeader($file_id, 'FFC');
     
    foreach ($confirm_files as $confirm_file) { 
      $confirm = $xml->addChild('WMIFILECONFIRM'); 
      $confirm->addAttribute('FILEID', str_replace('_', '.', $confirm_file[0])); 
      $confirm->addAttribute('FILETYPE', $confirm_file[1]); 
    } 
     
    $filename = $this->createFilename('WMI_Confirm_', $file_id); 
    $full_file = storage_path() . $this->upload_dir . $filename; 
    echo $xml->asXML($full_file); 
    return $filename; 

  } 
   
  private function createStatusFile ($statuses) { 
      if (count($statuses) > 0) {
        $file_id = $this->createFileId();
        
        $xml = $this->createXmlHeader($file_id, 'FOS');
        
        $body = $xml->addChild('WMIORDERSTATUS'); 
         
        foreach ($statuses as $num => $status) { 
          $line = $body->addChild('OS_LINESTATUS'); 
          $line->addAttribute('REQUESTNUMBER', $status[0]); 
          $line->addAttribute('LINENUMBER', $status[1]); 
          $line->addAttribute('STATUSCODE', $status[2]); 
          $line->addAttribute('QUANTITY', $status[3]); 
        } 
         
        $filename = $this->createFilename('WMI_Order_Status_', $file_id); 
        $full_file = storage_path() . $this->upload_dir . $filename; 
        echo $xml->asXML($full_file); 

        return $filename; 
      } else {
        return false;
      }
  } 
  
  
  public function shipmentNotification ($store, $shipments) { 
               
    $file_id = $this->createFileId(); 
     
    $xml = $this->createXmlHeader($file_id, 'FOS');
    
    $body = $xml->addChild('WMIORDERSTATUS'); 
     
    foreach ($shipments as $shipment) {
      
      foreach ($shipment->items as $item) {
        
        $edi_item = Walmart_EDI::find($item->edi_id);
        
        if (!$edi_item) {
          continue;
        }
        
        $line = $body->addChild('OS_PACKAGEINVOICE'); 
        $line->addAttribute('REQUESTNUMBER', $edi_item->REQUESTNUMBER); 
        $line->addAttribute('STATUSCODE', 'PS'); 
         
        $package = $line->addChild('OS_PACKAGE'); 
        
        if ($shipment->transaction_id != '') {
          $package->addAttribute('ASNNUMBER', $shipment->transaction_id); 
        }
        
        $package->addAttribute('CARRIERMETHODCODE', $edi_item->CARRIERMETHODCODE); 
        $package->addAttribute('PACKAGEID', $shipment->unique_order_id); 
        $package->addAttribute('TRACKINGNUMBER', $item->tracking_number); 
        $package->addAttribute('WEIGHT', 1); 
         
        $ship = $line->addChild('OS_SHIP_DATE'); 
        $ship->addAttribute('DAY', date("d")); 
        $ship->addAttribute('MONTH', date("m")); 
        $ship->addAttribute('YEAR', date("Y")); 
         
        $invoice = $line->addChild('OS_INVOICE'); 
        
        $ship_invoice = $invoice->addChild('OS_SHIPPING'); 
        
        if (strpos($shipment->mail_class, 'MAIL INNOVATIONS')) {
          $ship_invoice->addAttribute('THIRDPARTYSHIPPING', '0.0'); 
          $ship_invoice->addAttribute('SUPPLIERSHIPPING', '3.95'); 
        } else {
          $ship_invoice->addAttribute('THIRDPARTYSHIPPING', '1.0'); 
          $ship_invoice->addAttribute('SUPPLIERSHIPPING', '0.0'); 
        }
         
        $linecost = $invoice->addChild('OS_LINECOST'); 
        $linecost->addAttribute('LINENUMBER', $edi_item->LINENUMBER); 
        $linecost->addAttribute('QUANTITY', $item->item_quantity); 
      }
    }
     
    $filename = $this->createFilename('WMI_Order_Status_', $file_id); 
    $full_file = storage_path() . $this->upload_dir . $filename; 
    echo $xml->asXML($full_file); 
    
    $this->upload();
    
  } 
  
  public function exportShipments($store, $shipments) {
    
  }
  
  public function createInventoryFile () { 
              
    $file_id = $this->createFileId(); 
     
    $xml = $this->createXmlHeader($file_id, 'FII');
    
    $csv = '/home/jennifer/WM_prices.csv';
    
    $items = array();
    
    if (($handle = fopen($csv, "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $num = count($data);
            //for ($c=0; $c < $num; $c++) {
                //echo $data[$c] . "<br />\n";
                $items[] = [$data[3], $data[0], $data[4], $data[1], $data[2]];
            //}
        }
        fclose($handle);
    } 

    $confirm = $xml->addChild('WMIITEMINVENTORY'); 
     
    foreach ($items as $item) { 
       
      $lineitem = $confirm->addchild('II_ITEM'); 
      $lineitem->addAttribute('ITEMNUMBER', $item[0]); 
      $lineitem->addAttribute('UPC', substr($item[2], 0, -1)); 
      $lineitem->addAttribute('SKU', $item[1]); 
       
      $avail = $lineitem->addchild('II_AVAILABILITY'); 
      $avail->addAttribute('CODE', 'AA'); 
       
      $avail->addChild('II_ONHANDQTY', '10000'); 
       
      $days = $avail->addchild('II_DAYS');
      $days->addAttribute('MIN', '10'); 
      $days->addAttribute('MAX', '18'); 

      $start = $avail->addchild('II_START'); 
      $start->addAttribute('DAY', date("d")); 
      $start->addAttribute('MONTH', date("m")); 
      $start->addAttribute('YEAR', date("y")); 
       
      $end = $avail->addchild('II_END'); 
      $end->addAttribute('DAY', '01'); 
      $end->addAttribute('MONTH', '01'); 
      $end->addAttribute('YEAR', '2025'); 
               
      $price = $lineitem->addchild('II_PRICE'); 
      $price->addAttribute('MSRP', $item[4]); 
      $price->addAttribute('RETAIL', $item[4]); 
      $price->addAttribute('COST', $item[3]); 
    } 
     
    $filename = $this->createFilename('WMI_Inventory_', $file_id); 
    $full_file = storage_path() . $this->upload_dir . $filename; 
    echo $xml->asXML($full_file); 
    return $filename; 

  } 
   
  private function createASN($unique_order_id) { 
     
    $ASN = sprintf("0000744792%09d", str_replace('-', '', $unique_order_id)); 
   
    $sum = intval((($ASN[0] + $ASN[2] + $ASN[4] + $ASN[6] + $ASN[8] + $ASN[10] + $ASN[12] + $ASN[14] + $ASN[16] + $ASN[18]) * 3)  
              + ($ASN[1] + $ASN[3] + $ASN[5] + $ASN[7] + $ASN[9] + $ASN[11] + $ASN[13] + $ASN[15] + $ASN[17])); 
    
    if ($sum % 10 === 0) {
      return $ASN . '0';
    } else {
      $rounded = intval(ceil($sum / 10) * 10); 
      $checkdigit = intval($rounded - $sum);
      return $ASN . $checkdigit; 
    }
  } 
   
  public function shipLabel($store, $unique_order_id, $order_id) { 
        
    $EDI_info = Walmart_EDI::where('REQUESTNUMBER', $order_id)->first();
    
    if (!$EDI_info) {
      Log::error('Walmart DSV: EDI record not found for DTS label ' . $order_id);
      return null;
    }
    
    if ($EDI_info->METHODCODE == 'MI' || $EDI_info->METHODCODE == 'MA') {
      
      $shipment = Ship::with('order.customer')
                      ->where('unique_order_id', $unique_order_id)
                      ->first();
      
      if (!$shipment) {
        Log::error('Walmart DSV: Shipment not found for DTS label ' . $unique_order_id);
        return null;
      }
      
      $ASN = $this->createASN($unique_order_id); 
      
      $customer = $shipment->order->customer;
      
      $name =  $customer->ship_full_name;
      $address1 = $customer->ship_address_1; 
      $address2 = $customer->ship_address_2; 
      $city = $customer->ship_city; 
      $state = $customer->ship_state; 
      $zip = $customer->ship_zip; 
      $order_id = $order_id; 
      $PO = $EDI_info->REQUESTNUMBER; 
      $date = date("n/d/y"); 
       
      $zpl = "^XA^FO30,100^GB750,950,20^FS^FO75,130^A0,35,30^FDSHIP FROM:^FS^FO90,160^A0,35,30^FDWALMART.COM^FS^FO90,190^A0,35,30^FD1901 SE 10th St^FS"; 
      $zpl .= "^FO90,220^A0,35,30^FDBentonville, AR ^FS^FO150,250^A0,35,30^FD72712-5698^FS^FO375,100^GB1,200,20^FS^FO400,130^A0,35,30^FDSHIP TO:^FS"; 
       
       
      $zpl .= "^FO410,160^A0,35,30^FD$address1^FS^FO410,190^A0,35,30^FD$address2^FS^FO410,220^A0,35,30^FD$city, $state ^FS^FO475,250^A0,35,30^FD$zip^FS"; 
      $zpl .= "^FO50,300^GB720,1,20^FS^FO50,450^GB720,1,20^FS^FO75,480^A0,50,40^FDCUSTOMER: $name^FS^FO75,530^A0,50,40^FDCUSTOMER ORDER #:^FS"; 
      $zpl .= "^FO150,575^A0,50,40^FD $order_id^FS^FO75,630^A0,40,35^FDWM.com PO #: $PO^FS^FO75,670^A0,35,30^FDProcessing Date: $date^FS"; 
      $zpl .= "^FO200,720^A0,35,30^FDWal-Mart Associate Scan ASN Below^FS^FO50,750^GB720,1,20^FS"; 
       
      $zpl .= "^FO60,790^BCN,200,Y,N,N^FD$ASN^FS^XZ";  
      
      $zpl = str_replace("'", " ", $zpl);
  		$zpl = str_replace('"', " ", $zpl);
  		$zpl = str_replace('&quot;', " ", $zpl);
      
      $shipment->transaction_id = $ASN;
      $shipment->save();
      
      return ['Use Orange Walmart Direct to Store Tape on Package', $zpl]; 
      
    } else {
      
      return ''; 
      
    }
    
  } 
  
  private function createXmlHeader ($file_id, $type) {
         
    $xml = new \SimpleXMLElement('<WMI></WMI>'); 
     
    $header = $xml->addChild('WMIFILEHEADER'); 
    $header->addAttribute('FILEID', $file_id); 
    $header->addAttribute('FILETYPE', $type); 
    $header->addAttribute('VERSION', '4.0.0'); 
     
    $to = $header->addChild('FH_TO'); 
    $to->addAttribute('ID', '2677'); 
    $to->addAttribute('NAME', 'Walmart.com Operations Support'); 
   
     
    $from = $header->addChild('FH_FROM'); 
    $from->addAttribute('ID', '744792'); 
    $from->addAttribute('NAME', 'Deal To Win, Inc. - SYOSSET, NY - DSV'); 
    $contact = $from->addChild('FH_CONTACT'); 
    $contact->addAttribute('NAME', 'Shlomi Matalon'); 
    $contact->addAttribute('EMAIL', 'Shlomi@Monogramonline.com'); 
    $contact->addAttribute('PHONE', '8563203210'); 
    
    return $xml;
  }
  
  private function createFilename($prefix, $fileID) { 
    return  $prefix . str_replace('.', '_', $fileID) . '.xml'; 
  } 
   
  private function createFileId() { 
    return '744792' . date(".Ymd.His.") . rand(100000, 999999); 
  } 
  
  private function getPersonalization ($config_id) {
    
    if (!$config_id) {
      return ['item_options' => [], 'ip' => '', 'sku' => ''];
    }
    
    // $json = file_get_contents('https://personalizeddecor.walmart.com/walmart/api/get/configid/' . $config_id);
    
    $url='https://personalizeddecor.walmart.com/walmart/api/get/configid/' . $config_id;
    
    $ch = curl_init();
    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);     
    $json=curl_exec($ch);
    
    curl_close($ch);
    
    $decoded = json_decode(substr(str_replace("\u00a0", "", $json), 5, -6), true);
    $options = json_decode($decoded['customAttributes'], true);
    
    $sku = json_decode($decoded['sku'], true);
    
    $elements = array(); 
  
    foreach ($sku as $key => $value) {
      if (substr($key, 5, 2) == 'Id') {
        $elements['id_catalog'] = $value;
      } elseif (substr($key, 5, 3) == 'Url') {
        $elements['url'] = $value;
      } elseif (substr($key, 5, 5) == 'Thumb') {
        $elements['thumb'] = substr($value, strpos($value, 'src=') + 4, -1);
      } elseif (substr($key, 5, 11) == 'Description') {
        $elements['description'] = $value;
      }
    }
    
    $a = array_map(function ($element) {
			return str_replace(" ", "_", trim($element));
		}, array_keys($options));
    
    $b = array_map(function ($element) {
      return html_entity_decode(trim($element));
    }, $options);
    
    // $b = array_map('trim', $options);
    
    $options = array_combine($a, $b);
    
    $options = json_encode($options);
    
    if (isset($elements['id_catalog'])) {
      $id_catalog = $elements['id_catalog'];
    } else if (isset($elements['description']) && isset($sku['sku'])) {
      $id_catalog = strtolower(str_replace(' ', '-', $elements['description'] . '-' . $sku['sku']));
    } else if (isset($elements['description'])) {
      $id_catalog = strtolower(str_replace(' ', '-', $elements['description']));
    } else if (isset($sku['sku'])) {
      $id_catalog = strtolower(str_replace(' ', '-', $sku['sku']));
    } else {
      $id_catalog = rand(10000, 99999);
    }
    
    return ['item_options'    => $options, 
            'ip'              => $decoded['cus_ip'], 
            'sku'             => $sku['sku'],
            'id_catalog'      => $id_catalog,
            'url'             => isset($elements['url']) ? $elements['url'] : null,
            'thumb'           => isset($elements['thumb']) ? $elements['thumb'] : null,
            'description'     => isset($elements['description']) ? $elements['description'] : null,
            ];
  }
  
  
  public function upload() {
    //connect to Walmart
    try { 
       
          $sftp = new SFTPConnection($this->url, $this->port); 
          $sftp->login($this->username, $this->password);   
        
      } catch (\Exception $e) { 
          echo 'Walmart getFiles: SFTP connection error ' . $e->getMessage(); 
          return FALSE; 
      } 

    //upload files to walmart
     try { 
       
         $file_list = $sftp->uploadDirectory($this->remote_upload, $this->upload_dir); 
         
     } catch (\Exception $e) { 
         Log::error('Walmart getFiles: SFTP upload error ' . $e->getMessage()); 
         return FALSE; 
     } 

    //move files to out directory
    foreach ($file_list as $file) {
      try {
        
        rename(storage_path() . $this->upload_dir . $file, storage_path() . $this->upload_archive_dir . $file);
        
      } catch (\Exception $e) {
        Log::error('Walmart getFiles: File rename Error ' . $e->getMessage()); 
      }
    }
    
  }
  
  public function retryPersonalization ($item_id) {
    
    $item = Item::find($item_id);
    
    if (!$item) {
      return false;
    }
    
    $config_json = json_decode($item->item_option, true);
    
    $attempts = 0;
    
    do {
      
      try {
        $info = $this->getPersonalization($config_json['configuration_id']);
      } catch (\Exception $e) {
        $attempts++;
        sleep(90);
        Log::info('Walmart DSV retryPersonalization]: Error getting personalization ' . $e->getMessage() . ' - attempt # ' . $attempts);
        continue;
      }
      
      break;
      
    } while($attempts < 10);
    
    if ($attempts == 10) {
      return false;
    }
    
    $item->item_option = $info['item_options'];
    $item->item_id =  $info['id_catalog'];
    $item->item_thumb = $info['thumb'];
    $item->item_url = $info['url'];
    $item->child_sku = Helper::getChildSku($item);
    $item->save();
    
    return true;
  }
  
  public function orderConfirmation($store, $order_id) { 
    // 
  } 
   
  public function backorderNotification($store, $item_id) { 
    // 
  } 
        
  private $lookup = array(        '02'  => ['UP',   'S_GROUND'],
                                  '09'  => ['UP',   'S_AIR_2DAY'],
                                  '13'  => ['UP',   'S_AIR_2DAY'],
                                  '15'  => ['FX', '_GROUND_HOME_DELIVERY'],
                                  '16'  => ['UP',   'S_AIR_1DAY'],
                                  '19'  => ['FX', '_FEDEX_EXPRESS_SAVER'],
                                  '20'  => ['FX', '_FEDEX_GROUND'],
                                  '21'  => ['FX', '_PRIORITY_OVERNIGHT'],
                                  '22'  => ['FX', '_FEDEX_2_DAY'],
                                  '23'  => ['FX', '_PRIORITY_OVERNIGHT'],
                                  '24'  => ['FX', '_STANDARD_OVERNIGHT'],
                                  '26'  => ['UP',   'S_AIR_1DAYSAVER'],
                                  '27'  => ['UP',   'S_AIR_2DAYAM'],
                                  '29'  => ['UP',   'S_3DAYSELECT'],
                                  '38'  => ['UP',   'S_GROUND'],
                                  '44'  => ['FX', '_PRIORITY_OVERNIGHT'],
                                  '45'  => ['UP',   'S_AIR_1DAYSAVER'],
                                  '61'  => ['UP',   'S_GROUND'],
                                  '67'  => ['FX', '_GROUND_HOME_DELIVERY'],
                                  '77'  => ['FX', '_PRIORITY_OVERNIGHT'],
                                  '78'  => ['FX', '_FEDEX_2_DAY'],
                                  '79'  => ['FX', '_FEDEX_GROUND'],
                                  '80'  => ['UP',   'S_GROUND'],
                                  '88'  => ['UP',   'S_AIR_1DAY'],
                                  '97'  => ['UP',   'S_AIR_2DAY'],
                                  '501' => ['UP',   'S_GROUND'],
                                  '31'  => [null,    null],
                                  '30'  => [null,    null],
                                  '5'   => [null,    null],
                                  '10'  => [null,    null],
                              ); 
}
