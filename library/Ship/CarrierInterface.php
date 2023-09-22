<?php 
 
namespace Ship; 
 
 
abstract class CarrierInterface 
{ 
     
    abstract public function getLabel($store, $order, $unique_order_id, $method, $weight);
    
    abstract public function void($store, $tracking_number);
         
}
