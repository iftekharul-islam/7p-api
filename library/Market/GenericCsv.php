<?php

namespace Market;

use App\Models\Order;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Ship;
use App\Models\Product;
use App\Models\Parameter;
use App\Models\StoreItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use library\Helper;
use Ship\CSV;
use Excel;
use Monogram\Sure3d;


class GenericCsv extends StoreInterface
{
  protected $dir = '/EDI/';

  public function importCsv($store, $file)
  {

    if ($file == null) {
      return;
    }

    if (!file_exists(storage_path() . $this->dir . $store->store_id)) {
      mkdir(storage_path() . $this->dir . $store->store_id);
    }

    $filename = 'import_' . date("Ymd_His", strtotime('now')) . '.csv';

    $saved = move_uploaded_file($file, storage_path() . $this->dir . $store->store_id . '/' . $filename);

    if (!$saved) {
      return false;
    }

    $csv = new CSV;
    $results = $csv->intoArray(storage_path() . $this->dir . $store->store_id . '/' . $filename, ',');

    $error = array();
    $order_ids = array();
    $id = '';

    set_time_limit(0);

    $valid_keys = [
      'order',
      'name',
      'address1',
      'address2',
      'city',
      'state',
      'zip',
      'country',
      'phone',
      'comment',
      'color',
      'sku',
      'child_sku',
      'qty',
      'price',
      'thumbnail',
      'graphic',
      // Andre added
      // 'ship via',
      // 'Ship By Date',
      //  'Status',
      //  'P1',
      // 'P2',
      // 'P3',
      //'P4',
      //  'P5',
      // 'P6',
    ];

    if (!$results[0] == $valid_keys) {
      $error[] = 'File does not have valid column headers';
      return ['errors' => $error, 'order_ids' => $order_ids];
    }

    foreach ($results as $line) {

      if ($line[0] == 'order') {
        continue;
      }

      if (!isset($line[0]) || $line[0] == '') {
        $error[] = 'Order ID not set, first column is blank.';
        continue;
      }

      Log::info('Generic Import: Processing order ' . $line[0]);


      if ($id == '' || $line[0] != $id) {

        $previous_order = Order::join('customers', 'orders.customer_id', '=', 'customers.id')
          ->where('orders.is_deleted', '0')
          ->where('orders.order_id', $store->store_id . '-' . $line[0])
          ->first();

        if ($previous_order) {
          Log::info('Generic Import : Order number already in Database ' . $line[0]);
          $error[] = 'Order number already in Database ' . $line[0];
          continue;
        }

        $order_5p = $this->insertOrder($store->store_id, $line);
        $order_ids[] = $order_5p;
        $id = $line[0];
      }

      if (!$this->insertItem($store->store_id, $order_5p, $line)) {
        $error[] = $item_result;
      }

      $this->setOrderTotals($order_5p);
    }

    if (count($order_ids) == 0) {
      $error[] = 'No Orders Imported from File';
    }

    return ['errors' => $error, 'order_ids' => $order_ids];
  }


  private function insertOrder($storeid, $data)
  {

    // -------------- Customers table data insertion started ----------------------//
    $customer = new Customer();

    $customer->order_id = $storeid . '-' . $data[0];
    $customer->ship_full_name = isset($data[1]) ? $data[1] : null;
    if (isset($data[1]) && $data[1] != '') {
      $customer->ship_last_name = (strpos($data[1], ' ') === false) ? '' : preg_replace('#.*\s([\w-]*)$#', '$1', $data[1]);
      $customer->ship_first_name = trim(preg_replace('#' . $customer->ship_last_name . '#', '', $data[1]));
    }
    $customer->ship_address_1 = isset($data[2]) ? $data[2] : null;
    $customer->ship_address_2 = isset($data[3]) ? $data[3] : null;
    $customer->ship_city = isset($data[4]) ? $data[4] : null;
    $customer->ship_state = isset($data[5]) ? Helper::stateAbbreviation($data[5]) : null;
    $customer->ship_zip = isset($data[6]) ? $data[6] : null;
    $customer->ship_country = (isset($data[7])  && $data[7] != '') ? $data[7] : 'US';
    $customer->ship_phone = isset($data[8]) ? $data[8] : null;

    $customer->bill_email = 'DROPSHIP@MONOGRAMONLINE.COM';

    // -------------- Customers table data insertion ended ----------------------//
    // -------------- Orders table data insertion started ----------------------//
    $order = new Order();
    $order->order_id = $storeid . '-' . $data[0];
    $order->short_order = $data[0];
    $order->item_count = 1;
    $order->shipping_charge = '0';
    $order->tax_charge = '0';
    $order->total = 0;
    $order->order_date = date("Y-m-d H:i:s");
    $order->store_id = $storeid;
    $order->ship_state = $data[5];
    $order->order_comments = $data[9];
    $order->order_status = 4;

    if (isset($data[17])) {
      $shipinfo = explode('*', $data[17]);
      $order->carrier = $shipinfo[0] ?? "";
      $order->method = $shipinfo[1] ?? ""; // Error maybe check
    }

    if (isset($data[18])) {
      $order->ship_date = Carbon::now()->format('Y-m-d H:i:s');
    }
    if (isset($data[19])) {
      $status = [
        4, 6, 7, 8, 9, 10, 11, 12, 15, 13, 23
      ];

      if (in_array($data[19], $status)) {
        $order->order_status = $data[19];
      }
    }

    // Batch option (20)
    $batch = $data[20] ?? false;

    /*
             * Handle batching the order if it's true
             */
    if ($batch) {
    }

    $customer->save();

    try {
      $order->customer_id = $customer->id;
    } catch (\Exception $exception) {
      Log::error('Failed to insert customer id in Generic');
    }

    /*
			 * 17 IS SHIPPED VIA
			 */
    $order->save();

    try {
      $order_5p = $order->id;
    } catch (\Exception $exception) {
      $order_5p = '0';
      Log::error('Failed to get 5p order id in Generic');
    }

    Order::note('Order imported from Generic CSV template', $order->id, $order->order_id);

    return $order->id;
  }

  private function insertItem($storeid, $order_5p, $data)
  {

    $product = null;

    if (isset($data[11])) {
      $product = Helper::findProduct($data[11]);
    }

    if ($product) {
      $sku = $product->product_model;
      $item_id = $product->id_catalog;
      $url = $product->product_url;
      $desc = $product->product_description;
      $thumb = $product->product_thumb;
    } else {
      Log::error('Generic: Product not found ' . $data[11]);
      $sku = trim($data[11]);
      $item_id = null;
      $url = null;
      $desc = 'PRODUCT NOT FOUND';
      $thumb = null;
    }

    $options = array();

    if (isset($data[10]) && trim($data[10]) != '') {
      $options['Color'] = $data[10];
    }

    if (isset($data[16]) && trim($data[16]) != '') {
      $options['graphic'] = $data[16];
    }

    $item = new Item();
    $item->order_id = $storeid . '-' . $data[0];
    $item->store_id = $storeid;
    $item->item_description = $desc;
    $item->item_quantity = isset($data[13]) ? intval($data[13]) : 1;
    $item->data_parse_type = 'CSV';
    $item->item_code = $sku;
    $item->item_id = $item_id;
    $item->item_thumb = isset($data[15]) ? $data[15] : $thumb;
    $item->sure3d = isset($data[16]) ? $data[16] : null;
    $item->item_url = $url;
    $item->item_option = json_encode($options);

    $item->item_unit_price = isset($data[14]) ? $data[14] : 0;

    $item->child_sku = isset($data[12]) ? $data[12] : null;

    $item->order_5p = $order_5p;

    /*
         * New options from file
         * P1 starts at 21
         * B1 starts at
         */

    $customOptions = [];

    // P1 starts
    if (isset($data[21])) {
      $customOptions["P1"] = $data[21];
    }
    if (isset($data[22])) {
      $customOptions["P2"] = $data[22];
    }
    if (isset($data[23])) {
      $customOptions["P3"] = $data[23];
    }
    if (isset($data[24])) {
      $customOptions["P4"] = $data[24];
    }
    if (isset($data[25])) {
      $customOptions["P5"] = $data[25];
    }
    if (isset($data[26])) {
      $customOptions["P6"] = $data[26];
    }

    // B1 starts
    if (isset($data[27])) {
      $customOptions["B1"] = $data[27];
    }
    if (isset($data[28])) {
      $customOptions["B2"] = $data[28];
    }
    if (isset($data[29])) {
      $customOptions["B3"] = $data[29];
    }
    if (isset($data[30])) {
      $customOptions["B4"] = $data[30];
    }
    if (isset($data[31])) {
      $customOptions["B5"] = $data[31];
    }
    if (isset($data[32])) {
      $customOptions["B6"] = $data[32];
    }

    foreach ($customOptions as $key => $value) {
      if ($value == "" or is_null($value)) {
        unset($customOptions[$key]);
      }
    }

    if (count($customOptions) >= 1) {
      $item->item_option = json_encode($customOptions);
    }

    $item->save();

    return true;
  }

  private function setOrderTotals($order_5p)
  {
    if ($order_5p != null) {
      $order = Order::with('items')
        ->where('id', $order_5p)
        ->first();
      if (!$order) {
        Log::error('Generic setOrderTotals: order not found!');
        return;
      }
      $order->item_count = $order->items->count();
      $order->total = $order->items->sum(function ($i) {
        return $i->item_quantity * $i->item_unit_price;
      });
      $order->save();
      return;
    }
  }

  public function orderConfirmation($store, $order)
  {
  }

  public function shipmentNotification($store, $shipments)
  {
  }

  public function exportShipments($store, $shipments)
  {

    Log::info('Generic shipment csv started');


    $header = array();

    $header[] = 'ship date';
    $header[] = 'carrier';
    $header[] = 'tracking';
    $header[] = 'order';
    $header[] = 'name';
    $header[] = 'address1';
    $header[] = 'address2';
    $header[] = 'city';
    $header[] = 'state';
    $header[] = 'zip';
    $header[] = 'country';
    $header[] = 'sku';
    $header[] = 'child_sku';
    $header[] = 'qty';
    $header[] = 'price';

    $lines = array();
    $lines[] = $header;

    foreach ($shipments as $shipment) {

      foreach ($shipment->items as $item) {

        $line = array();

        $line[] = $shipment->transaction_datetime;
        $line[] = substr($shipment->mail_class, 0, strpos($shipment->mail_class, ' '));
        $line[] = $shipment->tracking_number;
        $line[] = $shipment->order->short_order;
        $line[] = $shipment->order->customer->ship_full_name;
        $line[] = $shipment->order->customer->ship_address_1;
        $line[] = $shipment->order->customer->ship_address_2;
        $line[] = $shipment->order->customer->ship_city;
        $line[] = $shipment->order->customer->ship_state;
        $line[] = $shipment->order->customer->ship_zip;
        $line[] = $shipment->order->customer->ship_country;
        $line[] = $item->item_code;
        $line[] = $item->child_sku;
        $line[] = $item->item_quantity;
        $line[] = $item->item_unit_price;

        $lines[] = $line;
      }
    }

    if (count($lines) > 1) {

      $filename = $store . '_SHIP_' . date('ymd_His');
      $path = storage_path() . $this->dir . $store . '/';


      try {
        Excel::create($filename, function ($excel) use ($lines) {
          $excel->sheet('Sheet1', function ($sheet) use ($lines) {
            $sheet->fromArray($lines, null, 'A1', false, false);
          });
        })->store('xlsx', $path);


        // copy($path . $filename . '.xlsx', storage_path() . '/EDI/download/' . $filename . '.xlsx');

        Log::info('Generic shipment csv upload created');
        return $path . $filename . '.xlsx';
      } catch (\Exception $e) {
        Log::error('Error Creating Generic XLS - ' . $e->getMessage());
      }
    }
  }

  public function getInput($store)
  {
  }

  public function backorderNotification($store, $item)
  {
  }

  public function shipLabel($store, $unique_order_id, $order_id)
  {
  }
}
