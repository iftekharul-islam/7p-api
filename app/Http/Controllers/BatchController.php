<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\BatchNote;
use App\Models\BatchRoute;
use App\Models\BatchScan;
use App\Models\Item;
use App\Models\Order;
use App\Models\Rejection;
use App\Models\Ship;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

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

            // $batches = $batchQuery->with('route', 'station', 'itemsCount', 'first_item.product', 'store')
            $batches = $batchQuery->with('route', 'station', 'itemsCounts', 'first_item.product', 'store')
                ->latest('created_at')
                ->paginate($request->get('per_page', 10));

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
}
