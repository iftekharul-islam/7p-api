<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Item;
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
