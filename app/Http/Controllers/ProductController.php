<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\BatchRoute;
use App\Models\Item;
use App\Models\Product;
use App\Models\Station;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $products = Product::where('is_deleted', '0')
            ->searchInOption($request->get('search_in'), $request->get("search_for"))
            ->searchProductionCategory($request->get('product_production_category'));
        return $products->paginate($request->get('perPage', 10));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $id_catalog = trim($request->get('id_catalog'));
        $product_model = trim($request->get('product_model'));
        $checkExisting = Product::where('id_catalog', $id_catalog)
            ->orWhere('product_model', $product_model)
            ->first();
        if ($checkExisting) {
            return response()->json([
                'message' => 'Product already exists either with id catalog or model!',
                'status' => 203,
                'data' => []
            ], 203);
        }

        $data = $request->only([
            'id_catalog',
            'product_model',
            'product_upc',
            'product_asin',
            'product_default_cost',
            'product_url',
            'product_name',
            'ship_weight',
            'product_production_category',
            'product_price',
            'product_sale_price',
            'product_wholesale_price',
            'product_thumb',
            'product_description',
            'height',
            'width',
        ]);
        try {
            Product::create($data);
            return response()->json([
                'message' => 'Production created successfully!',
                'status' => 201,
                'data' => []
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 403,
                'data' => []
            ], 403);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::query()
            ->where('is_deleted', '0')
            ->find($id);
        if (!$product) {
            return response()->json([
                'message' => 'Product Not Found',
                'status' => 203,
                'data' => []
            ], 203);
        }
        return $product;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json([
                'message' => 'Product not found!',
                'status' => 203,
                'data' => []
            ], 203);
        }
        $data = $request->only([
            'id_catalog',
            'product_upc',
            'product_asin',
            'product_default_cost',
            'product_url',
            'product_name',
            'ship_weight',
            'product_production_category',
            'product_price',
            'product_sale_price',
            'product_wholesale_price',
            'product_thumb',
            'product_description',
            'manufacture_id',
            'height',
            'width'
        ]);
        $product->product_note = $request->get("product_note") . "@" . $request->get("msg_flag");

        $product->update($data);
        return response()->json([
            'message' => 'Product update successfully!',
            'status' => 201,
            'data' => []
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $data = Product::find($id);
        if ($data) {
            $data->delete();
            return response()->json([
                'message' => 'Product delete successfully!',
                'status' => 201,
                'data' => []
            ], 201);
        }
        return response()->json([
            'message' => "Product didn't found!",
            'status' => 203,
            'data' => []
        ], 203);
    }

    public function moveNextStation(Request $request)
    {
        $success = NULL;
        $error = NULL;
        $scan_batches = NULL;
        $scan_batches_image = NULL;

        if ($request->get('task') == 'next') {
            if (!$request->has('batch_number')) {
                return response()->json([
                    'message' => 'Please select batch number!',
                    'status' => 203,
                ], 203);
            }

            $batch_update = Batch::with('route', 'station')
                ->whereIn('batch_number', $request->get('batch_number'))
                ->get();

            foreach ($batch_update as $batch) {
                $next_station = Batch::getNextStation('object', $batch->batch_route_id, $batch->station_id);
                if ($next_station && $next_station->id != '0') {
                    $batch->prev_station_id = $batch->station_id;
                    $batch->station_id = $next_station->id;
                    $batch->save();
                    $success[] = sprintf('Batch %s Successfully Moved to %s<br>', $batch->batch_number, $next_station->station_name);
                } else {
                    $error .= sprintf('Batch %s has no further stations on route <br>', $batch->batch_number);
                }
            }
        } elseif ($request->get('task') == 'move') {

            if ($request->has('station_change') && $request->get('station_change') != '' && $request->get('station_change') != '0') {

                // return $request->get('batch_number');
                $batch_update = Batch::with('route', 'station')
                    ->whereIn('batch_number', $request->get('batch_number'))
                    ->get();

                foreach ($batch_update as $batch) {
                    $batch->prev_station_id = $batch->station_id;
                    $batch->station_id = $request->get('station_change');;
                    $batch->save();
                    $success[] = sprintf('Batch %s Successfully Moved <br>', $batch->batch_number);
                }
            } else {
                $error .= 'No Station Selected';
            }
        }

        if ($request->has('scan_batches') && $request->get('scan_batches') != ',') {

            ini_set('memory_limit', '256M');

            $scan_batches = str_replace(',,', ',', $request->get('scan_batches'));
            $scan_list = explode(',', rtrim(trim($request->get('scan_batches')), ','));
            $batch_array = array();

            foreach ($scan_list as $input) {
                if (substr(trim($input), 0, 4) == 'BATC') {
                    $batch_array[] = substr(trim($input), 4);
                } else {
                    $batch_array[] = trim($input);
                }
            }

            $found = Batch::with('first_item.order', 'route', 'station', 'itemsCounts')
                ->where('is_deleted', '0')
                ->whereIn('batch_number', $batch_array)
                ->searchRoute($request->get('route'))
                ->searchStatus('movable')
                ->get();

            $routes_in_station = array();
            $routes_in_station['all'] = 'Select Route';
            $next_in_route = array();

            foreach ($batch_array as $batch_number) {

                $batch = $found->where('batch_number', $batch_number)->first();

                if (!$batch) {

                    $related = Batch::related($batch_number);

                    if ($related) {
                        $batch = $related;
                        $success = sprintf('Batch %s selected, Batch %s is inactive <br>', $batch->batch_number, $batch_number);
                    } else {
                        $error = sprintf('Problem with Batch %s <br>', $batch_number);
                        continue;
                    }
                }

                $routes_in_station[$batch->batch_route_id] =
                    $batch->route->batch_route_name . " => " . $batch->route->batch_code;
                $next_station = Batch::getNextStation('object', $batch->batch_route_id, $batch->station_id);
                if ($next_station) {
                    $next_in_route[$batch->batch_route_id] = $next_station->station_name . ' - ' . $next_station->station_description;
                    $next_type[$batch->batch_route_id] = $next_station->type;
                }

                $batches[] = $batch;
            }

            unset($found);

            if (count($routes_in_station) < 2) {
                $routes_in_station = NULL;
            }
        } elseif ($request->has('station')) {

            $batches = Batch::with('first_item.order', 'route', 'itemsCounts')
                ->join('stations', 'batches.station_id', '=', 'stations.id')
                ->where('batches.is_deleted', '0')
                ->searchRoute($request->get('route'))
                ->searchStatus('movable')
                //->where('stations.type', 'P')
                ->where('station_id', $request->get('station'))
                ->orderBy('batch_number', 'ASC')
                ->get();

            $routes = Batch::with('route')
                ->join('stations', 'batches.station_id', '=', 'stations.id')
                ->where('batches.is_deleted', '0')
                ->where('station_id', $request->get('station'))
                ->searchStatus('movable')
                //->where('stations.type', 'P')
                ->select('batch_route_id')
                ->groupBy('batch_route_id')
                ->get();

            $routes_in_station = array();
            $routes_in_station['all'] = 'Select Route';
            $next_in_route = array();

            foreach ($routes as $route) {
                $routes_in_station[$route->batch_route_id] =
                    $route->route->batch_route_name . " => " . $route->route->batch_code;
                $next_station = Batch::getNextStation('object', $route->batch_route_id, $request->get('station'));
                if ($next_station) {
                    $next_in_route[$route->batch_route_id] = $next_station->station_name . ' - ' . $next_station->station_description;
                    $next_type[$route->batch_route_id] = $next_station->type;
                }
            }

            if (count($routes_in_station) < 2) {
                $routes_in_station = NULL;
            }

            $station = Station::where('id', $request->get('station'))
                ->first();
        } else {
            $error = 'No Batches or Station Selected';
            $station = NULL;
        }


        if ($request->has('route') || (isset($batches) && count($batches) == 1)) {
            if ($request->has('route')) {
                $route = $request->get('route');
            } else {
                $route = $batches[0]->batch_route_id;
            }

            $stations_in_route = array();
            $stations_in_route[0] = 'Move to any station in route';

            $all_route_stations = BatchRoute::with('stations_list')
                ->where('id', $route)
                ->get();

            foreach ($all_route_stations as $route_stations) {
                foreach ($route_stations->stations_list as $route_station) {
                    $stations_in_route[$route_station->station_id] = $route_station->station_name . ' => ' . $route_station->station_description;
                }
            }
        } else {
            $route = NULL;
        }

        if ($error) return response()->json([
            'message' => $error,
            'status' => 203
        ], 203);
        if ($success) return response()->json([
            'message' => $success,
            'status' => 201
        ], 201);

        $route_station = [];
        foreach ($routes_in_station as $key => $value) {
            $route_station[] = [
                'label' => $value,
                'value' => $key,
            ];
        }

        $stations_route = [];
        foreach ($stations_in_route ?? [] as $key => $value) {
            $stations_route[] = [
                'label' => $value,
                'value' => $key,
            ];
        }

        return response()->json([
            'batches' => $batches ?? NULL,
            'routes_in_station' => $route_station ?? NULL,
            'next_in_route' => $next_in_route ?? NULL,
            'next_type' => $next_type ?? NULL,
            'station' => $station ?? NULL,
            'route' => $route ?? NULL,
            'stations_in_route' => $stations_route ?? NULL,
        ]);
    }

    public function searchableFieldsOption()
    {
        $searchable_fields = [];
        foreach (Product::$searchable_fields ?? [] as $key => $value) {
            $searchable_fields[] = [
                'label' => $value,
                'value' => $key,
            ];
        };
        return $searchable_fields;
    }

    public function productOption(Request $request)
    {
        $searchAble = sprintf("%%%s%%", str_replace(' ', '%', trim($request->get('query'))));

        $product = Product::where('product_model', "LIKE", $searchAble)
            ->orWhere('id_catalog', 'LIKE', $searchAble)
            ->orWhere('product_name', 'LIKE', $searchAble)
            ->where('is_deleted', '0')
            ->selectRaw('id_catalog, product_model, product_name, product_thumb, product_price')
            ->get();
        $result = [];
        foreach ($product as $model) {
            $result[] = [
                'label' => $model->product_model . ' - ' . $model->product_name,
                'data' => $model
            ];
        }
        return $result;
    }

    public function download_images() {

        set_time_limit(0);

        $products = Product::select('id', 'product_model', 'product_thumb')
            ->where('product_thumb', 'NOT LIKE', 'http://order.monogramonline.com%')
            ->get();

        foreach ($products as $product) {

            if ($product->product_thumb != null) {

                try {

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL,$product->thumb);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
                    curl_setopt($ch, CURLOPT_NOBODY, 1);
                    curl_setopt($ch, CURLOPT_FAILONERROR, 1);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch,CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
                    $result = curl_exec($ch);
                    curl_close($ch);

                } catch (\Exception $e) {
                    //
                }

            }

            if (!isset($result) || $result == FALSE) {

                $url = 'https://www.monogramonline.com/imageurl?sku=' . trim($product->product_model);

                $attempts = 0;

                do {

                    try {

                        $ch = curl_init();
                        curl_setopt($ch,CURLOPT_URL,$url);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                        curl_setopt($ch,CURLOPT_USERAGENT,'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)');
                        $json=curl_exec($ch);

                        curl_close($ch);

                        // $json = file_get_contents($url);
                    } catch (\Exception $e) {
                        echo $e->getMessage();
                        $attempts++;
                        info('Magento item thumb: Error ' . $e->getMessage() . ' - attempt # ' . $attempts);
                        sleep(2);
                        continue;
                    }

                    break;

                } while($attempts < 5);

                if ($attempts == 5) {

                    info('Magento item thumb: Failure, SKU ' . $product->product_model);
                    continue;

                } else {

                    $decoded = json_decode(substr($json, 5, -6), true);

                    if (isset($decoded['image_url']) && $decoded['image_url'] != "Image Not found") {
                        $thumb = $decoded['image_url'];
                    } else {
                        $item = Item::where('item_code', $product->product_model)
                            ->whereNotNull('item_thumb')
                            ->select('item_thumb')
                            ->latest()
                            ->first();
                        if ($item) {
                            $thumb = $item->item_thumb;
                        } else {
                            continue;
                        }
                    }
                }
            } else {
                $thumb = $product->product_thumb;
            }


            $img = '/assets/images/product_thumb/' . $product->product_model . substr($thumb, -4);

            try {

                if (file_exists(base_path() . '/public_html' . $img) && filesize(base_path() . '/public_html' . $img) > 0) {
                    unlink(base_path() . '/public_html' . $img);
                }

                if (!file_exists(base_path() . '/public_html' . $img)) {
                    @file_put_contents(base_path() . '/public_html' . $img, file_get_contents($thumb));
                }

                if (file_exists(base_path() . '/public_html' . $img) && filesize(base_path() . '/public_html' . $img) > 0) {
                    $product->product_thumb = 'http://order.monogramonline.com' . $img;
                    $product->save();
                } elseif (file_exists(base_path() . '/public_html' . $img)) {
                    unlink(base_path() . '/public_html' . $img);
                }


            } catch (\Exception $e) {
                info($product->product_model . ' - ' . $e->getMessage());
            }

        }

        return;
    }

}
