<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\BatchNote;
use App\Models\BatchRoute;
use App\Models\BatchScan;
use App\Models\InventoryAdjustment;
use App\Models\Item;
use App\Models\Order;
use App\Models\Rejection;
use App\Models\Ship;
use App\Models\Wap;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use library\Helper;

class BatchController extends Controller
{
    public function index(Request $request)
    {
        if ($request->hasAny(['batch', 'route', 'station', 'type', 'section', 'store', 'production_station', 'graphic_dir', 'printed', 'print_date', 'printed_by', 'status', 'graphic_found', 'start_date', 'end_date', 'order_start_date', 'order_end_date'])) {

            $batchQuery = Batch::where('is_deleted', '0')
                ->searchBatch($request->get('batch'))
                ->searchRoute($request->get('route'))
                ->searchStation($request->get('station'))
                ->searchStationType($request->get('type'))
                ->searchSection($request->get('section'))
                ->searchStore($request->get('store'))
                ->searchProductionStation($request->get('production_station'))
                ->searchGraphicDir($request->get('graphic_dir'))
                ->searchPrinted($request->get('printed'), $request->get('print_date'), $request->get('printed_by'))
                ->searchStatus($request->get('status'), $request->get('batch'))
                ->searchGraphic($request->get('graphic_found'))
                ->searchMinChangeDate($request->get('start_date'))
                ->searchMaxChangeDate($request->get('end_date'))
                ->searchOrderDate($request->get('order_start_date'), $request->get('order_end_date'));

            if ($request->get('status') == "complete") {
                $batchQuery->each(function ($batch) {
                    $dir = "/media/RDrive/archive/" . $batch->batch_number;
                    $this->removeShipedImagePdf($dir);
                });
            }

            $batches = $batchQuery->with('route', 'station', 'itemsCounts', 'first_item.product', 'store')
                ->latest('created_at')
                ->paginate(10);

            $total = Item::whereIn('batch_number', $batches->pluck('batch_number')->all())
                ->where('is_deleted', '0')
                ->selectRaw('SUM(item_quantity) as quantity, count(*) as count')
                ->first();
        } else {
            $batches = collect([]);
            $total = [];
        }

        return response()->json([
            'batches' => $batches,
            'total' => $total
        ], 200);
    }

    public function show(Request $request, string $batch_number)
    {
        if ($request->has('label')) {
            $label = $request->get('label');
        } else {
            $label = null;
        }

        Batch::isFinished($batch_number);

        $batch = Batch::with(
            'items.order.store',
            'items.rejections.user',
            'items.rejections.rejection_reason_info',
            'items.spec_sheet',
            'items.product',
            'station',
            'route',
            'section',
            'store',
            'summary_user'
        )
            ->where('is_deleted', '0')
            ->where('batch_number', $batch_number)
            ->get();

        if (count($batch) == '0') {
            return response()->json([
                'message' => 'Batch not found',
                'status' => 203
            ], 203);
        }

        $batch = $batch[0];

        if ($batch->station) {
            $station_name = $batch->station->station_name;
        } else {
            $station_name = 'Station not Found';
        }

        $original = Batch::getOriginalNumber($batch_number);

        $related = Batch::where('batch_number', 'LIKE', '%' . $original)
            ->where('batch_number', '!=', $batch_number)
            ->get()
            ->pluck('batch_number');

        if ($request->has('batch_note')) {
            Batch::note($batch_number, $batch->station_id, '2', $request->get('batch_note'));
        }


        $notes = BatchNote::with('station', 'user')
            ->where('batch_number', $batch_number)
            ->get();

        $scans = BatchScan::with('in_user', 'out_user', 'station')
            ->where('batch_number', $batch_number)
            ->get();

        $stations = BatchRoute::routeThroughStations($batch->batch_route_id, $station_name);

        $count = 1;

        $last_scan = Batch::lastScan($batch_number);

        $index = 0;
        if (count($batch->items) === 1) {
            $itemId = $batch->items[0]->id;
            $orderId = $batch->items[0]->order_5p;

            $order = Order::with("items")
                ->where("id", $orderId)
                ->get();

            if (count($order) === 1) {
                $order = $order[0];
                foreach ($order->items as $itIndex => $item) {
                    if ($item->id === $itemId) {
                        $index = $itIndex;
                    }
                }
            }
        }

        return response()->json([
            'batch' => $batch,
            'batch_number' => $batch_number,
            'last_scan' => $last_scan,
            'stations' => $stations,
            'count' => $count,
            'related' => $related,
            'notes' => $notes,
            'label' => $label,
            'scans' => $scans,
            'index' => $index,
            'request' => $request
        ], 200);

        // return view('batches.show', compact(
        //     'batch',
        //     'batch_number',
        //     'last_scan',
        //     'stations',
        //     'count',
        //     'related',
        //     'notes',
        //     'label',
        //     'scans',
        //     'index',
        //     'request'
        // ));
    }

    public function removeShipedImagePdf($dir)
    {
        if (file_exists($dir)) {
            if (is_dir($dir)) {
                $objects = scandir($dir);
                foreach ($objects as $object) {
                    if ($object != "." && $object != "..") {
                        if (is_dir($dir . "/" . $object) && !is_link($dir . "/" . $object))
                            rmdir($dir . "/" . $object);
                        else
                            unlink($dir . "/" . $object);
                    }
                }
                rmdir($dir);
            } elseif (is_file($dir)) {
                unlink($dir);
            }
        }
    }

    public function rejectItem(Request $request)
    {
        $origin = $request->get('origin');

        $rules = [
            'item_id'           => 'required',
            'reject_qty'        => 'required|integer|min:1',
            'graphic_status'    => 'required',
            'rejection_reason'  => 'required|exists:rejection_reasons,id',
        ];

        $validation = Validator::make($request->all(), $rules);

        if ($validation->fails()) {
            return response()->json([
                'message' => $validation->errors()->first(),
                'status' => 203
            ], 203);
        }

        $item = Item::with('inventoryunit')
            ->where('id', $request->get('item_id'))
            ->first();

        info($item);
        if ($item->item_status == 'rejected') {
            return response()->json([
                'message' => 'Item Already Rejected',
                'status' => 201
            ], 201);
        }

        $batch_number = $item->batch_number;

        if ($origin == 'QC') {
            $batch = Batch::find($request->get('id'));

            if ($batch && $batch->batch_number != $batch_number) {
                return response()->json([
                    'message' => 'Please scan user ID',
                    'status' => 203
                ], 203);
            }
        } elseif ($origin == 'SL') {
            $tracking_number = $item->tracking_number;
        }

        $result = $this->itemReject(
            $item,
            $request->get('reject_qty'),
            $request->get('graphic_status'),
            $request->get('rejection_reason'),
            $request->get('rejection_message'),
            $request->get('title'),
            $request->get('scrap')
        );

        //send first reprint to production
        if ($request->get('graphic_status') == '1') {

            $count = Rejection::where('item_id', $result['reject_id'])->count();

            if ($count == 1) {
                $this->moveStation($result['new_batch_number']);

                $msg = Batch::export($result['new_batch_number'], '0');

                if (isset($msg['error'])) {
                    Batch::note($result['new_batch_number'], '', 0, $msg['error']);
                }
            }
        }

        if ($origin == 'QC') {
            return redirect()->route('qcShow', ['id' => $request->get('id'), 'batch_number' => $batch_number, 'label' => $result['label']]);
        } elseif ($origin == 'BD') {
            return response()->json([
                'message' => $result['label'],
                'status' => 201
            ], 201);
        } elseif ($origin == 'WP') {

            return redirect()->route('wapShow', ['bin' => $request->get('bin_id'), 'label' => $result['label'], 'show_ship' => '1']);
        } elseif ($origin == 'SL') {
            $order = Order::find($item->order_5p);
            $order->order_status = 4;
            $order->save();
            Order::note("Order status changed from Shipped to To Be Processed - Item $item->id rejected after shipping", $order->order_5p, $order->order_id);
            $shipment = Ship::with('items')
                ->where('order_number', $order->id)
                ->where('tracking_number', $tracking_number)
                ->first();
            if ($shipment && $shipment->items && count($shipment->items) == 0) {
                $shipment->delete();
            }

            return redirect()->route('shipShow', ['search_for_first' => $tracking_number, 'search_in_first' => 'tracking_number', 'label' => $result['label']]);
        } elseif ($origin == 'MP') {

            return redirect()->action('GraphicsController@showBatch', ['scan_batches' => $request->get('scan_batches')]);
        } else {

            $label = $result['label'];
            return response()->json([
                'message' => $label,
                'status' => 201
            ], 201);
        }
    }

    public function itemReject($item, $qty, $graphic_status, $reason, $message, $title, $scrap)
    {

        if ($graphic_status == 7) {
            $prefix = 'X';
        } else {
            $prefix = 'R';
        }

        $batch_number = $item->batch_number;

        if ($batch_number == '0') {
            return ['new_batch_number' => '0', 'reject_id' => $item->id, 'label' => null];
        }

        $original_batch_number = Batch::getOriginalNumber($batch_number);

        $reject_batch = Batch::join('items', 'batches.batch_number', '=', 'items.batch_number')
            ->join('rejections', 'items.id', '=', 'rejections.item_id')
            ->select('batches.batch_number')
            ->where('batches.status', 3)
            ->where('items.item_status', 3)
            ->where('rejections.graphic_status', $graphic_status)
            ->where('batches.batch_number', 'LIKE', $prefix . '%' . $original_batch_number)
            ->get();

        $old_batch = Batch::where('batch_number', $batch_number)
            ->first();

        if (count($reject_batch) > 0) {

            $new_batch_number = $reject_batch->first()->batch_number;
        } else if ($old_batch) {

            $reject_batch = new Batch;
            $reject_batch->batch_number = Batch::getNewNumber($batch_number, $prefix);
            $reject_batch->save();
            $new_batch_number = $reject_batch->batch_number;
            $reject_batch->section_id = $old_batch->section_id;
            $reject_batch->station_id = $old_batch->station_id;
            $reject_batch->batch_route_id = $old_batch->batch_route_id;
            $reject_batch->production_station_id = $old_batch->production_station_id;
            $reject_batch->store_id = $old_batch->store_id;
            $reject_batch->creation_date = date("Y-m-d H:i:s");
            $reject_batch->change_date = date("Y-m-d H:i:s");
            $reject_batch->status = 'held';
            $reject_batch->save();
        }

        if ($item->item_quantity <= $qty) {
            $item->item_status = 'rejected';
            $item->batch_number = $new_batch_number;
            $item->tracking_number = null;
            $item->save();

            $reject_id = $item->id;
        } else {
            $update_qty = $item->item_quantity - $qty;

            $reject_item = new Item;
            $reject_item->batch_number = $item->batch_number;
            $reject_item->order_5p = $item->order_5p;
            $reject_item->order_id = $item->order_id;
            $reject_item->store_id = $item->store_id;
            $reject_item->item_code = $item->item_code;
            $reject_item->child_sku = $item->child_sku;
            $reject_item->item_description = $item->item_description;
            $reject_item->item_id = $item->item_id;
            $reject_item->item_option = $item->item_option;
            $reject_item->item_thumb = $item->item_thumb;
            $reject_item->item_unit_price = $item->item_unit_price;
            $reject_item->item_url = $item->item_url;
            $reject_item->data_parse_type = 'reject';
            $reject_item->sure3d = $item->sure3d;
            $reject_item->edi_id = $item->edi_id;
            $reject_item->item_quantity = $qty;
            $reject_item->tracking_number = null;
            $reject_item->item_status = 'rejected';
            $reject_item->save();

            $reject_item->batch_number = $new_batch_number;
            $reject_item->save();

            $item->item_quantity = $update_qty;
            $item->save();

            $reject_id = $reject_item->id;
        }

        $rejection = new Rejection;
        $rejection->item_id = $reject_id;
        $rejection->scrap = $scrap;
        $rejection->graphic_status = $graphic_status;
        $rejection->rejection_reason = $reason;
        $rejection->rejection_message = $message;
        $rejection->reject_qty = $qty;
        $rejection->rejection_user_id = auth()->user()->id;
        $rejection->from_station_id =  $old_batch->station_id;
        $rejection->from_batch =  $old_batch->batch_number;
        $rejection->to_batch =  $new_batch_number;
        $rejection->from_screen =  $title;
        $rejection->save();

        $label = null;

        if ($scrap == '1') {
            foreach ($item->inventoryunit as $stock_no) {
                InventoryAdjustment::adjustInventory(8, $stock_no->stock_no_unique, $rejection->reject_qty * $stock_no->unit_qty, $rejection->id, $rejection->item_id);
            }

            $label = $this->getLabel($rejection);
        }

        // Order::note("Item $reject_id rejected: " . $rejection->rejection_reason_info->rejection_message, $item->order_5p);

        Wap::removeItem($reject_id, $item->order_5p);

        Batch::isFinished($old_batch->batch_number);

        return ['new_batch_number' => $new_batch_number, 'reject_id' => $reject_id, 'label' => $label];
    }

    public function getLabel($rejection)
    {

        $item = $rejection->item;
        $order_id = $rejection->item?->order?->short_order;

        if ($rejection->rejection_reason_info) {
            $reason = $rejection->rejection_reason_info->rejection_message;
        } else {
            $reason = '';
        }

        $username = $rejection->user->username;

        $last_scan = null;
        foreach ($rejection->from_batch_info->scans as $scan) {
            if ($scan->station->type == "P") {
                if (isset($scan->in_user)) {
                    $last_scan = $scan->station->station_name . ' : IN ' . $scan->in_user->username . ' ' . substr($scan->in_date, 0, 10);
                }

                if (isset($scan->out_user)) {
                    $last_scan .= ' - OUT ' . $scan->out_user->username . ' ' . substr($scan->in_date, 0, 10);
                }
                break;
            }
        }


        $label = "^XA" .
            "^FX^CF0,200^FO100,50^FDREJECT^FS^FO50,220^GB700,1,3^FS" .
            "^FX^CF0,30^FO50,240^FDItem ID: $rejection->item_id^FS^FO350,240^FDOrder ID:$order_id^FS^FO50,280^FDBatch: $rejection->to_batch^FS" .
            "^FO50,320^FDDate: $rejection->created_at^FS^FO50,370^GB700,1,3^FS^FO50,400^FB750,3,,^FD$item->item_description^FS" .
            "^FO50,440^FB750,2,,^FDSKU:$item->child_sku^FS" .
            "^FO100,480^FB560,6,,^FD" . Helper::optionTransformer($item->item_option, 1, 0, 0, 1, 0, ',  ') . "^FS" .
            "^FX^FO50,630^GB700,270,3^FS^CF0,40^FO75,650^FDRejected QTY: $rejection->reject_qty^FS" .
            "^FO350,650^FDRejected By: $username^FS^CF0,50^FO75,700^FDReason: $reason^FS" .
            "^FO75,750^FB700,3,,^FD$rejection->rejection_message^FS" .
            "^CF0,20^FO60,875^FDSCAN : $last_scan^FS" .
            "^FX^BY5,2,150^FO50,920^BC^FDITEM$rejection->item_id^FS" .
            "^XZ";

        return str_replace("'", " ", $label);
    }

    public function export_bulk(Request $request)
    {
        $batch_numbers = $request->get('batch_number');

        $success = array();
        $error = array();

        if (is_array($batch_numbers)) {

            if ($request->has('force')) {
                $force = $request->get('force');
            } else {
                $force = 0;
            }

            foreach ($batch_numbers as $batch_number) {

                $msg = Batch::export($batch_number, $force);

                if (isset($msg['success'])) {
                    $success[] = $msg['success'];
                }

                if (isset($msg['error'])) {
                    $error[] = $msg['error'];
                }
            }

            //$message = sprintf("Batches: %s are exported.", implode(", ", $batch_numbers));
            $message = '';
            if (count($success)) {
                $message = '<div class="text-success">';
                $message = $message . implode("<br />", $success);
                $message = $message . '</div>';
            }
            if (count($error)) {
                $message = '<div class="text-danger">';
                $message = $message . implode("<br />", $error);
                $message = $message . '</div>';
            }
            return response()->json([
                'message' => $message,
                'status' => 201
            ], 201);
        } else {
            return response()->json([
                'message' => 'No Batches Selected',
                'status' => 203
            ], 203);
        }
    }
}
