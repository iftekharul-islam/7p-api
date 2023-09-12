<?php 

namespace Market; 

use App\Order;
use App\Customer;
use App\Item;
use App\Product;
use Ship\Shipper;
use Monogram\Helper;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use WalmartMkt;

class Walmart_MKT extends StoreInterface 
{ 
		protected $dir = '/EDI/Walmart_MKT/'; 
		
		public function importCsv($store, $file) {
      //
    }
		
		public function getInput($store) {
			
			Log::info('Making Request to Walmart Marketplace');
			
			do {
				
				try {
					if (isset($list['meta']['nextCursor'])) {
						Log::info('Walmart MKT: getting next cursor: ' . $list['meta']['nextCursor']);
						$list = WalmartMkt::orderGet()->allReleased(new Carbon('2018-01-01'), Carbon::tomorrow(), $list['meta']['nextCursor']);
					} else {
						$list = WalmartMkt::orderGet()->allReleased(new Carbon('2018-01-01'), Carbon::tomorrow());
					}
				}  catch (\Exception $exception ) {
					if (isset($list)) {
						Log::error($list);
					}
					Log::error($exception->getMessage());
					return;
				}
				if (count($list) > 0) {
					
						foreach ($list['elements'] as $orders) {
							
							$break = false;
							
							foreach ($orders as $order) {
								
								if ($break) {
									break;
								}
								
								if (isset($orders['purchaseOrderId'])) {
									$order = $orders;
									$break = true;
								}
								
								//check if order in DB
								$db_order = Order::with('items.batch')
															->where('order_id', $order['purchaseOrderId'])
															->where('is_deleted', '0')
															->first();
								
							if (!$db_order) { 
									$this->insertOrder($store, $order);
									WalmartMkt::orderModify()->acknowledge($order['purchaseOrderId']);
								// } else if ($db_order && $order->getOrderStatus() == 'Canceled' && $db_order->order_status != '8') {
								// 	$this->cancelOrder($store, $order);                  
							} else {
								Log::info('Walmart Marketplace: Duplicate order not inserted ' . $order['purchaseOrderId']);
							}
						}
					}
				}
			} while (isset($list['meta']['nextCursor']));
		
		}
		
		private function insertOrder($store, $input) { 
			// -------------- Customers table data insertion started ----------------------//
			$customer = new Customer();
			
			$customer->order_id = $input['purchaseOrderId'];
			$address = $input['shippingInfo']['postalAddress']; 
			$customer->ship_full_name = $address['name'];
			$customer->ship_last_name = (strpos($address['name'], ' ') === false) ? '' : preg_replace('#.*\s([\w-]*)$#', '$1', $address['name']);
			$customer->ship_first_name = trim( preg_replace('#'.$customer->ship_last_name.'#', '', $address['name'] ) );
			$customer->ship_address_1 = $address['address1'];
			$customer->ship_address_2 = isset($address['address2']) ? $address['address2'] : null;
			$customer->ship_city = $address['city'];
			$customer->ship_state = Helper::stateAbbreviation($address['state']);
			$customer->ship_zip = $address['postalCode'];
			$customer->ship_country = $address['country'];
			$customer->ship_phone = $input['shippingInfo']['phone'];
			$customer->ship_email = isset($input['customerEmailId']) ? $input['customerEmailId'] : null;
			$customer->bill_email = isset($input['customerEmailId']) ? $input['customerEmailId'] : null;
			$customer->save();
			// -------------- Customers table data insertion ended ----------------------//
			// -------------- Orders table data insertion started ----------------------//
			$order = new Order();
			$order->order_id = $input['purchaseOrderId'];
			try {
				$order->customer_id = $customer->id;
			} catch ( \Exception $exception ) {
				Log::error('Failed to insert customer id in Walmart Marketplace');
			}
			$order->short_order = $input['purchaseOrderId'];
			$order->item_count = count($input['orderLines']);
			$order->shipping_charge = '0';
			$order->tax_charge = '0';
			$order->total = 0;
			$order->order_date = substr($input['orderDate'], 0, 10) . ' ' . substr($input['orderDate'], 11, 8);
			$order->store_id = 'wm-mkt01';
			$order->store_name = 'Walmart Marketplace';
			$order->order_status = 4;
			$order->ship_state = Helper::stateAbbreviation($address['state']);
			$order->ship_date = substr($input['shippingInfo']['estimatedShipDate'], 0, 10);
			
			$order->save();
			
			try {
				$order_5p = $order->id;
			} catch ( \Exception $exception ) {
				$order_5p = '0';
				Log::error('Failed to get 5p order id in Amazon');
			}
			// -------------- Orders table data insertion ended ----------------------//
			// -------------- Items table data insertion started ------------------------//
			
			$total = 0;
			$shipping = 0;
			$tax_total = 0;
			
			foreach ( $input['orderLines'] as $WLMitems ) { 
				
				$break = false;
				
				foreach ($WLMitems as $WLMitem) {
					
					if ($break) {
						break;
					}
					
					if (isset($WLMitems['charges'])) {
						$WLMitem = $WLMitems;
						$break = true;
					}
					
					if (isset($WLMitem['charges']['charge']['chargeAmount'])) {
						$charges = $WLMitem['charges'];
					} else {
						$charges = $WLMitem['charges']['charge'];
					}
					
					foreach ($charges as $charge) {
						if ($charge['chargeName'] == 'ItemPrice') {
							$price = $charge['chargeAmount']['amount'];
							$total = $total + $price;
						} else if ($charge['chargeName'] == 'Shipping') {
							$shipping += $charge['chargeAmount']['amount'];
						}
						if (isset($charge['tax']['taxAmount'])) {
							$tax_total += $charge['tax']['taxAmount']['amount'];
						}
					}
					
					$product = Helper::findProduct($WLMitem['item']['sku']);
					
					// $SKU = str_replace('_', '-', $WLMitem['item']['sku']);
					// 
					// $product = Product::where('product_model', $SKU)->first();
					// 
					// if ( ! $product ) { 
					// 	$product = Product::where('product_model', substr($SKU, 0, strrpos($SKU, '-')))->first();
					// 
					// 	if ( !$product ) { 
					// 		$product = Product::where('product_model', substr($SKU, 0, strrpos($SKU, '-', strrpos($SKU, '-') - strlen($SKU) - 1)))
					// 							->first();
					// 		if ( !$product ) {
					// 			$product = new Product();
					// 			$product->id_catalog = str_replace(' ', '-', strtolower($WLMitem['item']['productName']));
					// 			$product->product_model = $SKU;
					// 			// $product->product_url = ;
					// 			$product->product_name = $WLMitem['item']['productName'];
					// 			$product->product_thumb = 'http://order.monogramonline.com/assets/images/no_image.jpg';
					// 			//$product->product_upc = $EDI_item['UPC'];
					// 			$product->batch_route_id = Helper::getDefaultRouteId();
					// 			$product->save();
					// 
					// 		} else {
					// 			$SKU = substr($SKU, 0, strrpos($SKU, '-', strrpos($SKU, '-') - strlen($SKU) - 1));
					// 		}
					// 	} else {
					// 		$SKU = substr($SKU, 0, strrpos($SKU, '-'));
					// 	}
					// } 
					
					if ( !$product ) {
						$product = new Product();
						$product->id_catalog = str_replace('&nbsp;', '', str_replace(' ', '-', strtolower($WLMitem['item']['productName'])));
						$product->product_model = str_replace('_', '-', $WLMitem['item']['sku']);
						// $product->product_url = ;
						$product->product_name = str_replace('&nbsp;', '', $WLMitem['item']['productName']);
						$product->product_thumb = 'http://order.monogramonline.com/assets/images/no_image.jpg';
						//$product->product_upc = $EDI_item['UPC'];
						$product->save();
			
					}
					
					// -------------- Products table data insertion ended ---------------------- //
					
					$item = new Item();
					$item->order_5p = $order->id;
					$item->order_id = $order->order_id;
					$item->store_id = $store;
					$item->item_code = $product->product_model;
					$item->item_description = $WLMitem['item']['productName'];
					$item->item_quantity = $WLMitem['orderLineQuantity']['amount'];
					$item->item_unit_price = $price;
					$item->data_parse_type = 'API';
					$item->item_option = '{}';
					if ($product) {
						//$item->item_id = $product->id_catalog;
						$item->item_thumb = $product->product_thumb;
						$item->item_url = $product->product_url;
					}
					$item->edi_id = $WLMitem['lineNumber'];
					$item->child_sku = Helper::insertOption(str_replace('_', '-', $WLMitem['item']['sku']), $item);
					$item->save();
					
					if ($item->item_option == '[]') {
						$order->order_status = 15;
					} 
				
				// -------------- Items table data insertion ended ---------------------- //
				}
			}
			
			try {
				$isVerified = Shipper::isAddressVerified($customer);
			} catch ( \Exception $exception ) {
				$isVerified = 0;
			}
			
			if ($isVerified) {
				$customer->is_address_verified = 1;
			} else {
				$customer->is_address_verified = 0;
				if ($order->order_status != 15) {
					$order->order_status = 11;
				}
			}
			$customer->save();
			
			$order->tax_charge = $tax_total;
			$order->total = $total + $tax_total;
			$order->save();
			
			return;    
		}
		
		private function cancelOrder($input) { 
			
			if ($order->order_status == '6') {
				mail(['jennifer_it@monogramonline.com'], 'Walmart Marketplace Cancellation problem', 
							'Tried to cancel order ' . $order->order_id . ', but it has already shipped.');
				return false;
			}
			
			$msg = '';
			
			foreach ($order->items as $item) {
				
				if ($item->item_status != 'shipped' && 
						 ($item->batch_number == '0' || ($item->batch->summary_date == NULL && $item->batch->export_count == 0))) {
							 
							continue;
							
				} else {
					
					if ($item->item_status == 'shipped') {
						
						$msg .= 'Item ' . $item->item_number . ' has already shipped, ';
						
					} else {
						
						if ($item->batch_number != '0') {
							$msg .= 'Item ' . $item->item_number . ' is in batch ' . $item->batch_number .', ';
						}
						
						if ($item->batch && $item->batch->summary_date != NULL) {
							$msg .= ' and batch ' . $item->batch_number . ' has had a summary printed, ';
						}
						
						if ($item->batch && $item->batch->export_count != 0) {
							$msg .= ' and batch ' . $item->batch_number . ' has been exported, ';
						}
					}
				}
			}
			
			if ($msg != '') {
				
				mail(['jennifer_it@monogramonline.com'], 'Walmart Marketplace Cancellation problem', 
								'Tried to cancel order ' . $order->order_id . ' ' . $msg);
				
			} else {
				
				foreach ($order->items as $item) {
					
					$item->batch_number = '0';
					$item->item_status = 6;
					$item->save();
					Batch::isFinished($batch_number);
				}
				
				$order->order_status = 8;
				$order->save();
			}
		}
		
		
		
		public function shipmentNotification ($store, $shipments) { 
			
				foreach ($shipments as $shipment) {
					foreach($shipment->items as $item) {
						
						$response = WalmartMkt::orderModify()->shipItem($shipment->order->short_order,
																								new Carbon($shipment->created_at),
																								'UPS', 
																								$shipment->shipping_id, 
																								null,
																								$item->edi_id,
																								$item->item_quantity
																							);
						Log::info('Walmart Marketplace: shipping ' . $shipment->order->order_id);
					}
				}
			
			return true;
		} 
		
    public function exportShipments($store, $shipments) {
      
    }
		
		public function orderConfirmation($store, $order) {
			
		}
		 
		public function backorderNotification($store, $item) {
			
		}
		
		public function shipLabel($store, $unique_order_id, $order_id) {
			
		}

}
