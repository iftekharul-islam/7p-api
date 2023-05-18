<?php

namespace Market;

use App\Order;
use App\Customer;
use App\Item;
use App\Ship;
use App\Store;
use App\Product;
use App\StoreItem;
use Illuminate\Support\Facades\Log;
use Monogram\Helper;
use Monogram\CSV;

class Staples extends StoreInterface
{
    protected $download_dir = '/EDI/download/';

    public function importCsv($store, $file)
    {

        $filename = 'import_' . date("ymd_His", strtotime('now')) . '.csv';

        $saved = move_uploaded_file($file, storage_path() . '/EDI/Staples/' . $filename);

        if (!$saved) {
            return false;
        }

        $csv = new CSV;
        $data = $csv->intoArray(storage_path() . '/EDI/Staples/' . $filename, ",");

        $error = array();
        $order_ids = array();

        set_time_limit(0);

        $po = '';
        $total = 0;
        $count = 0;
        $order_5p = null;

        foreach ($data as $line) {

            if ($line[0] != 'PKG_PACKAGE_ID') {

                if (count($line) != 22) {
                    $error[] = 'Incorrect number of fields in file: ' . count($line);
                    break;
                }

                // if (substr(strtolower($line[21]),0,3) != substr(strtolower($store->store_name),0,3)) {
                // 	 $error[] = 'Incorrect Store name in file ' . substr(strtolower($line[21]),0,3);
                // 	 break;
                // }

                if ($line[15] != $po) {

                    if ($po != '') {
                        $this->setOrderTotals($order_5p);
                    }

                    $po = $line[15];
                    $count = 0;
                    $total = 0;

                    Log::info('Staples: Processing order ' . $line[15]);

                    if (!isset($this->store_lookup[$line[21]])) {
                        $error[] = 'Unknown Store name in file: ' . $line[21];
                        break;
                    }

                    $store = Store::where('store_id', $this->store_lookup[$line[21]])->first();

                    if (!$store) {
                        $error[] = 'Store not found in Database: ' . $this->store_lookup[$line[21]];
                        break;
                    }

                    $previous_order = Order::where('orders.is_deleted', '0')
                        ->where('orders.order_id', $line[15])
                        ->where('orders.store_id', $this->store_lookup[$line[21]])
                        ->first();

                    if (!$previous_order) {
                        $order_5p = $this->insertOrder($line, $store);
                        $order_ids[] = $order_5p;
                        Log::info('CommerceHub import: order ' . $line[15] . ' processed');
                    } else {
                        $order_5p = $previous_order->id;
                        Log::info('CommerceHub : Order number already in Database ' . $line[15]);
                        $error[] = 'Order number already in Database ' . $line[15];
                    }
                }

                $id = $line[13] . ':' . $line[15];

                $previous_item = Item::where('order_5p', $order_5p)
                    ->where('edi_id', $id)
                    ->where('is_deleted', '0')
                    ->first();

                if (!$previous_item) {
                    $this->insertItem($line, $store, $order_5p);
                    $count++;
                    $total += $line[16] * $line[14];
                    $this->setOrderTotals($order_5p);
                }
            }
        }


        return ['errors' => $error, 'order_ids' => $order_ids];
    }

    private function insertOrder($data, $store)
    {

        // -------------- Customers table data insertion started ----------------------//
        $customer = new Customer();

        $customer->order_id = $data[15];
        $customer->ship_full_name = $data[1];
        $customer->ship_last_name = (strpos($data[1], ' ') === false) ? '' : preg_replace('#.*\s([\w-]*)$#', '$1', $data[1]);
        $customer->ship_first_name = trim(preg_replace('#' . $customer->ship_last_name . '#', '', $data[1]));

        if (isset($data[4]) && $data[4] != '') {
            $customer->ship_company_name = $data[2];
            $customer->ship_address_1 = $data[4];
        } else {
            $customer->ship_address_1 = $data[2];
        }

        $customer->ship_address_2 = $data[3] != '' ? $data[3] : null;
        $customer->ship_city = $data[5];
        $customer->ship_state = Helper::stateAbbreviation($data[6]);
        $customer->ship_zip = $data[7];
        $customer->ship_country = $data[8] != '' ? $data[8] : 'US';
        $customer->ship_phone = $data[9];

        $customer->bill_email = null;
        $customer->bill_company_name = $store->store_name;
        $customer->ignore_validation = TRUE;

        // -------------- Customers table data insertion ended ----------------------//
        // -------------- Orders table data insertion started ----------------------//
        $order = new Order();
        $order->order_id = $data[15];
        $order->short_order = $data[15];
        $order->shipping_charge = '0';
        $order->tax_charge = '0';
        $order->order_date = date("Y-m-d H:i:s");
        $order->store_id = $store->store_id;
        $order->store_name = '';
        $order->order_status = 4;
        $order->order_comments = '';
        $order->ship_state = Helper::stateAbbreviation($data[6]);
        $order->carrier = 'UP'; //??
        $order->method = 'S_GROUND';

        // -------------- Orders table data insertion ended ----------------------//

        $customer->save();

        try {
            $order->customer_id = $customer->id;
        } catch (\Exception $exception) {
            Log::error('Failed to insert customer id in CommerceHub');
        }

        $order->save();

        try {
            $order_5p = $order->id;
        } catch (\Exception $exception) {
            $order_5p = '0';
            Log::error('Failed to get 5p order id in CommerceHub');
        }

        Order::note('Order imported from CSV', $order->id, $order->order_id);

        return $order->id;

    }

    public function setOrderTotals($order_5p)
    {
        if ($order_5p != null) {
            $order = Order::find($order_5p);
            $order->item_count = count($order->items);
            $total = 0;
            foreach ($order->items as $item) {
                $total += $item->item_quantity * $item->item_unit_price;
            }
            $order->total = sprintf("%01.2f", $total);
            $order->save();
        }
    }

    private function insertItem($data, $store, $order_5p)
    {

        $item = new Item();

        $store_item = StoreItem::where('vendor_sku', $data[13])->first();

        if ($store_item) {
            $item->item_unit_price = $store_item->cost != '' ? $store_item->cost : 0;
            $item->child_sku = $store_item->child_sku;
            $product = Helper::findProduct($store_item->parent_sku);
            if (!$product) {
                $product = Helper::findProduct($data[12]);
            }
        } else {
            $item->item_unit_price = 0;
            $item->child_sku = $data[12];
            $product = Helper::findProduct($data[12]);
        }

        if ($product != false) {
            $item->item_code = $product->product_model;
            $item->item_id = $product->id_catalog;
            $item->item_thumb = $product->product_thumb;
            $item->item_url = $product->product_url;
            $item->item_description = $product->product_name;
        } else {
            $item->item_code = $data[12];
            $item->item_description = 'PRODUCT NOT FOUND';
            Log::error('CommerceHub Product not found ' . $data[13] . ' in order ' . $data[15]);
        }

        $item->order_id = $data[15];
        $item->store_id = $store->store_id;
        $item->item_quantity = $data[16];
        $item->data_parse_type = 'CSV';
        $item->item_option = '{}';
        $item->edi_id = $data[13] . ':' . $data[15];
        $item->order_5p = $order_5p;
        $item->save();
    }

    public function orderConfirmation($store, $order)
    {

    }

    public function shipmentNotification($store, $shipments)
    {

    }

    public function exportShipments($store, $shipments)
    {

        Log::info('Staples shipment csv started');

        $lines = array();

        $line = array();

        $line[] = 'po_number';
        $line[] = 'po_line_number';
        $line[] = 'vendor_sku';
        $line[] = 'serial_number';
        $line[] = 'invoice_number';
        $line[] = 'tracking_id';
        $line[] = 'carrier_id';
        $line[] = 'ship_quantity';
        $line[] = 'bol_number';
        $line[] = 'addl_charge_amount1';
        $line[] = 'addl_charge_type1';
        $line[] = 'addl_charge_currency1';
        $line[] = 'addl_charge_amount2';
        $line[] = 'addl_charge_type2';
        $line[] = 'addl_charge_currency2';

        $lines[] = $line;

        foreach ($shipments as $shipment) {

            foreach ($shipment->items as $item) {

                $line = array();

                $line[] = $item->order_id;
                $line[] = $item->edi_id;
                $line[] = $item->child_sku;
                $line[] = '';
                $line[] = $shipment->unique_order_id;
                $line[] = $shipment->tracking_number;
                if ($store != 'staples-04') {
                    $line[] = 'UPSM';
                } else {
                    $line[] = 'PRLA';
                }
                $line[] = $item->item_quantity;
                $line[] = '';
                $line[] = '';
                $line[] = '';
                $line[] = '';
                $line[] = '';
                $line[] = '';
                $line[] = '';

                $lines[] = $line;
            }
        }

        if (count($lines) > 0) {
            $filename = $store . '_SHIP_' . date('ymd_His') . '.csv';
            $path = storage_path() . '/EDI/Staples/';
            try {
                $csv = new CSV;
                $pathToFile = $csv->createFile($lines, $path, null, $filename, ',');
                // copy($path . $filename, storage_path() . '/EDI/Staples/' . $filename);
            } catch (\Exception $e) {
                Log::error('Error Creating Staples CSV - ' . $e->getMessage());
                return;
            }
        }

        Log::info('Staples shipment csv upload created');

        return $path . $filename;
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
