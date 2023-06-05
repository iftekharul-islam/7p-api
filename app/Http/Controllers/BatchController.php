<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class BatchController extends Controller
{
    public function index(Request $request)
    {
        $batch_numbers = [];
        Batch::where('is_deleted', '0')
            ->searchBatch($request->get('batch'))
            ->searchRoute($request->get('route'))
            ->searchStation($request->get('station'))
            ->searchStationType($request->get('type'))
            ->searchSection($request->get('section'))
            ->searchStore($request->get('store_id'))
            ->searchProductionStation($request->get('production_station'))
            ->searchGraphicDir($request->get('graphic_dir'))
            ->searchPrinted($request->get('printed'), $request->get('print_date'), $request->get('printed_by'))
            // ->searchStatus($request->get('status'), $request->get('batch'))
            ->searchGraphic($request->get('graphic_found'))
            ->searchMinChangeDate($request->get('start_date'))
            ->searchMaxChangeDate($request->get('end_date'))
            ->searchOrderDate($request->get('order_start_date'), $request->get('order_end_date'))
            ->orderBy('id', 'DESC')
            ->chunk(50000, function ($batches) use (&$batch_numbers) {
                $batch_numbers = array_merge($batch_numbers, $batches->pluck('batch_number')->toArray());
            });

        if ($request->get('status') == "complete") {
            $chunkSize = 10000;
            $totalChunks = ceil(count($batch_numbers) / $chunkSize);

            for ($i = 0; $i < $totalChunks; $i++) {
                $chunk = array_slice($batch_numbers, $i * $chunkSize, $chunkSize);

                $placeholders = implode(',', array_fill(0, count($chunk), '?'));

                $query = "SELECT batch_number FROM batches WHERE batch_number IN ($placeholders)";

                $results = DB::select($query, $chunk);

                // foreach ($results as $result) {
                //     $val = $result->batch_number;
                //     $dir = "/var/www/" . Sure3d::getEnv() . "/public_html/media/graphics/archive/" . $val;
                //     $this->removeShipedImagePdf($dir);

                //     $dir = "/media/graphics/MAIN/" . $val;
                //     $this->removeShipedImagePdf($dir);

                //     $files = "/var/www/" . Sure3d::getEnv() . "/public_html/media/graphics/summaries/" . $val . ".pdf";
                //     $this->removeShipedImagePdf($files);

                //     $globPattern = "/media/" . env("GRAPHICS_ENV") . "/Sure3d/*" . $val . "-*";
                //     $result = glob($globPattern);

                //     if (!empty($result)) {
                //         foreach ($result as $filePathName) {
                //             $this->removeShipedImagePdf($filePathName);
                //         }
                //     }
                // }
            }
        }

        $batches = new Collection();
        $total = (object) ['quantity' => 0, 'count' => 0];

        $chunkSize = 10000;
        $totalChunks = ceil(count($batch_numbers) / $chunkSize);

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunk = array_slice($batch_numbers, $i * $chunkSize, $chunkSize);

            $batchChunk = Batch::with('route', 'station', 'itemsCount', 'first_item.product', 'store')
                ->whereIn('batch_number', $chunk)
                ->latest('created_at')
                ->get();

            $batches = $batches->concat($batchChunk);

            $totalChunk = Item::whereIn('batch_number', $chunk)
                ->where('is_deleted', '0')
                ->selectRaw('SUM(item_quantity) as quantity, count(*) as count')
                ->get();

            $totalChunk = $totalChunk[0];
            $total->quantity += $totalChunk->quantity;
            $total->count += $totalChunk->count;
        }

        $batches = $batches->paginate(500);


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
