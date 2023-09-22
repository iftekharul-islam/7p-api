<?php 
 
namespace Market; 

use App\Order;
use Illuminate\Support\Facades\Log; 
 
class Magento extends StoreInterface 
{ 
    
    protected $url = [
                        '524339241' => 'http://www.personalizewithstyle.com/ioss/mlm/',
                        '52053152' => 'http://www.monogramonline.com/ioss/mlm/',
                      ];
    
    public function importCsv($store, $file) {
      //
    }
                      
    public function orderConfirmation($store, $order) {
      
      $url = $this->url[$store] . 'createInvoice/order_id/' . $order->short_order;
      
      $attempts = 0;
      
      do {
        
        try {
          $json = file_get_contents($url);
        } catch (\Exception $e) {
          $attempts++;
          sleep(2);
          Log::info('Magento orderConfirmation: Error ' . $e->getMessage() . ' - attempt # ' . $attempts);
          continue;
        }
        
        break;
        
      } while($attempts < 5);
      
      if ($attempts == 5) {
        Log::info('Magento orderConfirmation: Failure, Order ' . $order->order_id);
        Order::note('Failed to confirm order with Magento', $order->id, $order->order_id);
        return false;
      }
      
      $decode = json_decode($json,true);
      
      if ($decode['message'] == 'Invoice Create Success') {
        Log::info('Order confirmed with Magento' . $order->order_id);
        Order::note('Order confirmed with Magento', $order->id, $order->order_id);
      } else {
        Log::info('Magento orderConfirmation: ' . $decode['message'] . ' - ' . $order->order_id);
        Order::note('Magento: ' . $decode['message'], $order->id, $order->order_id);
      }
      
    } 
  
    public function shipmentNotification($store, $shipments) {
      
      Log::info('Inside Magento ShipNotificaton');
      foreach ($shipments as $shipment) {
        
        $url = $this->url[$store] . 'createShipping/order_id/' . $shipment->order->short_order . '/trackingNumber/' . $shipment->tracking_number;
        
        $attempts = 0;
        
        do {
          
          try {
            $json = file_get_contents($url);
          } catch (\Exception $e) {
            $attempts++;
            sleep(2);
            Log::info('Magento shipmentNotification: Error ' . $e->getMessage() . ' - attempt # ' . $attempts);
            continue;
          }
          
          break;
          
        } while($attempts < 5);
        
        if ($attempts == 5) {
          Log::info('Magento shipmentNotification: Failure, Order ' . $shipment->order->order_id);
          Order::note('Failed to notify Magento of shipment', $shipment->order_number, $shipment->order->order_id);
          return false;
        }
        
        $decode = json_decode($json,true);
        
        if ($decode['message'] == 'Shipping create Success') {
          Log::info('Magento notified of shipment ' . $shipment->order->order_id);
          Order::note('Magento notified of shipment', $shipment->order->id, $shipment->order->order_id);
        } else {
          Log::info('Magento shipmentNotification: ' . $decode['message'] . ' - ' . $shipment->order->order_id);
          Order::note('Magento: ' . $decode['message'], $shipment->order_number, $shipment->order->order_id);
        }
      }
    }
    
    public function exportShipments($store, $shipments) {
      
    }
    
    public function getInput($store) {
      
    } 
     
    public function backorderNotification($store, $item) {
      
    }
    
    public function shipLabel($store, $unique_order_id, $order_id) {
      
    }
         
}
