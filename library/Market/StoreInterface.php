<?php 
 
namespace Market; 
 
 
abstract class StoreInterface 
{ 
    
    abstract protected function importCsv($store, $file);
    
    abstract protected function getInput($store);  
     
    abstract protected function orderConfirmation($store, $order);  
     
    abstract protected function backorderNotification($store, $item); 
    
    /* Creates any additional label or identifying information for shipment */
    /* Accepts shipment Returns array [Info, Label] */
    abstract protected function shipLabel($store, $unique_order_id, $order_id); 
    
    abstract protected function shipmentNotification($store, $shipments); 
    
    abstract protected function exportShipments($store, $shipments); 
}
