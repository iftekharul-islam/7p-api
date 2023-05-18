<?php 
 
namespace Market; 

use App\Http\Controllers\ZakekeController;
use App\Section;
use Exception;
use Illuminate\Support\Facades\Log;
use Excel;
use App\Store;
use App\Ship;

class Quickbooks 
{ 
		public static function export($shipments) {
      
      // $shipments = Ship::with('order.customer', 'order.store', 'items')
      //                         ->whereIn('id', $shipment_ids)
      //                         ->where('is_deleted', '0')
      //                         ->get();
		  $lines = array();
		   
      $line = array();
      
      $line[] = 'blank';
      $line[] = 'RefNumber';
      $line[] = 'Customer';
      $line[] = 'TxnDate';
      $line[] = 'ShipDate';
      $line[] = 'ShipMethodName';
      $line[] = 'TrackingNum';
      $line[] = 'ShipAddrLine1';
      $line[] = 'ShipAddrLine2';
      $line[] = 'ShipAddrLine3';
      $line[] = 'ShipAddrCity';
      $line[] = 'ShipAddrState';
      $line[] = 'ShipAddrPostalCode';
      $line[] = 'ShipAddrCountry';
      $line[] = 'PrivateNote';
      $line[] = 'Msg';
      $line[] = 'Currency';
      $line[] = 'LineItem';
      $line[] = 'LineQty';
      $line[] = 'LineDesc';
      $line[] = 'LineUnitPrice';
      
      $lines[] = $line;
      
			foreach ($shipments as $shipment) {
        
				foreach ($shipment->items as $item) {
					
					$line = array();
					
          $line[] = '';
          $line[] = $shipment->unique_order_id;
          $line[] = $shipment->order->store->qb_name ?? $shipment->order->store->store_name;
          $line[] = date("m/d/Y", strtotime($shipment->order->order_date));
          $line[] = date("m/d/Y", strtotime($shipment->transaction_datetime));
          $line[] = $shipment->mail_class;
          $line[] = $shipment->tracking_number;
          $line[] = $shipment->order->customer->ship_full_name;
          $line[] = $shipment->order->customer->ship_address_1;
          $line[] = $shipment->order->customer->ship_address_2;
          $line[] = $shipment->order->customer->ship_city;
          $line[] = $shipment->order->customer->ship_state;
          $line[] = $shipment->order->customer->ship_zip;
          $line[] = $shipment->order->customer->ship_country;
          $line[] = '';
          if ($shipment->order->purchase_order != null) {
            $line[] = $shipment->order->purchase_order . '-' . $shipment->order->short_order;
          } else {
            $line[] = $shipment->order->short_order;
          }
          $line[] = 'USD';
          $line[] = $item->item_code;
          $line[] = $item->item_quantity;
          $line[] = $item->id . ' - ' . $item->item_description;
          $line[] = $item->item_unit_price;
          
					$lines[] = $line;
				}
        
        // $shipment->export = '1';
        $shipment->save();
			}
			
			if (count($lines) > 0) {
				$filename = strtoupper('QB_' . date('ymd_His')); 
        Log::info($filename);
				$path = storage_path() . '/EDI/Quickbooks/'; 
        
        try {
          Excel::create($filename, function($excel) use ($lines) {
              $excel->sheet('Invoices', function($sheet) use ($lines) {
                  $sheet->fromArray($lines, null, 'A1', false, false);
              });

          })->store('xlsx', $path);
          
          // copy($path . $filename . '.xls', storage_path() . '/EDI/download/' . $filename . '.xls');
        } catch (\Exception $e) {
          Log::error('Error Creating Quickbooks XLSX - ' . $e->getMessage());
        }
			}
			
			Log::info('Quickbooks invoice csv created');

			return $path . $filename . '.xlsx';
		}

    public static function csvExport($shipments)
    {
        $lines = array();

        $line = array();
        $line[] = 'PO';
        $line[] = 'OrderDate';
        $line[] = 'ShipDate';
        $line[] = 'Tracking Number';
        $line[] = 'LineItem';
        $line[] = 'LineQty';
        $line[] = 'LineUnitPrice';

        $line[] = 'Order #';
        $line[] = 'Inventory Description';
        $line[] = 'Inventory Section';
        $line[] = 'Dropship Cost';
        $line[] = 'Total';
        $lines[] = $line;

        $file = "/var/www/order.monogramonline.com/Inventories.json";
        $data = json_decode(file_get_contents($file), true);

        $sectionsAfter = Section::where('is_deleted', '0')
            ->get();

        $sections = [];

        foreach ($sectionsAfter as $section) {
            $sections[$section->id] = $section->section_name;
        }

        foreach ($shipments as $item) {

            $inventory = ZakekeController::getInventoryInformation($item->child_sku)->inventoryunit_relation->first()->inventory;

            $line = array();
            $line[] = $item->order->purchase_order;
            $line[] = date("m/d/Y", strtotime($item->order->order_date));
            $line[] = date("m/d/Y", strtotime($item->transaction_datetime));
            //$line[] = '"'.$item->shipping_id.'"';
            $line[] = "_".$item->shipping_id;
            $line[] = $item->item_code;
            $line[] = $item->item_quantity;
            $line[] = $item->item_unit_price;

            $line[] = $item->order->short_order;
            $line[] = $inventory->stock_name_discription ?? "Field stock_name_discription not found";
            $line[] = $sections[$inventory->section_id] ?? "ID for section  " . $inventory->section_id . " not found";

            if(isset($data[$inventory->id])) {
                $line[] = $data[$inventory->id]['DROPSHIP_COST'];
                $line[] = $data[$inventory->id]['DROPSHIP_COST'] * $item->item_quantity;
            } else {
                $line[] = "0";
                $line[] = "0";
            }

            $lines[] = $line;
        }

        if (count($lines) > 0) {
            $filename = strtoupper('QB_' . date('ymd_His'));
            $path = storage_path() . '/EDI/Quickbooks/';
            Log::info(storage_path() . $filename);

            try {
                Excel::create($filename, function ($excel) use ($lines) {
                    $excel->sheet('Invoices', function ($sheet) use ($lines) {
                        $sheet->fromArray($lines, null, 'A1', false, false);
                    });
                })->store('csv', $path);

            } catch (Exception $e) {
                Log::error('Error Creating Quickbooks XLSX - ' . $e->getMessage());
            }
        }

        Log::info('Quickbooks invoice csv created');

        return $path . $filename . '.csv';
    }

}
