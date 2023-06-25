<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\BatchNote;
use App\Models\BatchRoute;
use App\Models\BatchScan;
use App\Models\Item;
use App\Models\Order;
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
}
