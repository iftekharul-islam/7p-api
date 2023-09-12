<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Item;
use App\Models\Order;
use App\Models\Wap;
use App\Models\WapItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use library\Helper;
use Ship\Sure3d;

class WapController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->has('end_date')) {
            $end_date = NULL;

            $bins = Wap::with('order.shippable_items', 'order.store', 'order.items')
                ->whereHas('order.store', function ($q) {
                    //     //     // $q->where('permit_users', 'like', "%" . auth()->user()->id . "%")
                    $q->where('is_deleted', '0')
                        ->where('invisible', '0');
                })
                ->whereNotNull('wap.order_id')
                ->select('wap.*', DB::raw('(SELECT MAX(created_at) FROM wap_items WHERE wap_items.bin_id = wap.id ) as last,
                                                (SELECT COUNT(*) FROM wap_items WHERE wap_items.bin_id = wap.id ) as item_count'))
                ->orderBy('order_id', 'ASC');
        } else {
            $end_date = $request->get('end_date');

            $bin_list = Order::join('wap', 'wap.order_id', '=', 'orders.id')
                // ->whereHas('store', function ($q) {
                //     $q->where('permit_users', 'like', "%" . auth()->user()->id . "%");
                // })
                ->where('orders.order_date', '<', $end_date)
                ->whereNotNull('wap.order_id')
                ->selectRaw('wap.id')
                ->get()
                ->pluck('id')
                ->toArray();

            $bins = Wap::with('order.shippable_items', 'order.items')
                ->whereIn('wap.id', $bin_list)
                ->select('wap.*', DB::raw('(SELECT MAX(created_at) FROM wap_items WHERE wap_items.bin_id = wap.id ) as last,
                                                    (SELECT COUNT(*) FROM wap_items WHERE wap_items.bin_id = wap.id ) as item_count'))
                ->orderBy('order_id', 'ASC');
        }

        $sorted_bins = $bins->orderBy('last')->get();
        $bins = $bins->get();

        $statuses = [];
        foreach (Order::statuses() as $key => $value) {
            $statuses[] = [
                'label' => $value,
                'value' => $key,
            ];
        }
        return response()->json([
            'bins' => $bins,
            'sorted_bins' => $sorted_bins,
            'statuses' => $statuses,
        ]);
    }

    public function ShowBin(Request $request)
    {
        $show_ship = null;
        $reminder = $request->get('reminder');

        if ($request->has('label')) {

            $label = $request->get('label');

            if ($request->has('show_ship')) {
                $show_ship = $request->get('show_ship');
            }
        } elseif ($request->has('unique_order_id')) {

            $filename = 'assets/images/shipping_label/' . $request->get('unique_order_id') . '.zpl';

            if (file_exists($filename)) {
                $label = file_get_contents($filename);
                $label = trim(preg_replace('/\n+/', ' ', $label));
            } else {
                return response()->json([
                    'message' => 'Label not found',
                    'status' => 203
                ], 203);
                // session()->flash('error', 'Label Not Found');
            }
        } else {
            $label = null;
        }

        if ($request->has('bin')) {

            //TODO : check if query is valid or not

            $bin = Wap::with('items.batch', 'order.shippable_items', 'order.items.batch', 'order.customer', 'order.store')
                ->where('id', $request->get('bin'))
                ->first();

            if (!$bin) {
                return response()->json([
                    'message' => 'Bin not found',
                    'status' => 203
                ], 203);
            }

            if ($bin->order) {
                $order = $bin->order;
            } else {
                $order = Order::where('id', $request->get('order_id'))->first();
            }

            if (!$order) {
                $order = null;
            }
        } elseif ($request->has('bin_name')) {

            $bin = Wap::with('items.batch', 'order.shippable_items')
                ->where('name', 'LIKE',  $request->get('bin_name'))
                ->first();

            if (!$bin) {
                return response()->json([
                    'message' => 'Bin ' . $request->get('bin_name') . ' not found',
                    'status' => 203
                ], 203);
            }

            if ($bin->order) {
                $order = $bin->order;
            } else {
                $order = Order::where('id', $request->get('order_id'))->first();
            }

            if (!$order) {
                $order = null;
            }
        } elseif ($request->has('order_id')) {

            if (strtoupper(substr(trim($request->get('order_id')), 0, 4)) == 'ORDR') {
                $order_id = substr(trim($request->get('order_id')), 4);
            } else {
                $order_id = trim($request->get('order_id'));
            }

            $bin = Wap::with('items.batch', 'order.shippable_items')
                ->where('order_id', $order_id)
                ->first();

            if ($bin) {

                $order = Order::with('items.batch.station', 'items.shipInfo', 'items.rejections.rejection_reason_info')
                    ->where('short_order', $order_id)
                    ->where('orders.is_deleted', '0')
                    ->first();

                if ($order) {
                    $order_id = $order->id;

                    $bin = Wap::with('items.batch', 'order.shippable_items')
                        ->where('order_id', $order->id)
                        ->first();

                    if ($bin) {
                        return response()->json([
                            'message' => 'Bin not found',
                            'status' => 203
                        ], 203);
                    }
                } else {
                    return response()->json([
                        'message' => 'Order not found',
                        'status' => 203
                    ], 203);
                }
            }

            $order = $bin?->order;
        } else {
            return response()->json([
                'message' => 'Bin not specified',
                'status' => 203
            ], 203);
        }

        if (!$order) {
            return response()->json([
                'message' => 'Order not found',
                'status' => 203
            ], 203);
        }

        $item_options = array();
        $thumbs = array();

        foreach ($order->items as $item) {
            $item_options[$item->id] = Helper::optionTransformer($item->item_option, 1, 1, 1, 1, 0, '<br>');
            $thumbs[$item->id] = Sure3d::getThumb($item);
        }

        return response()->json([
            'bin' => $bin,
            'order' => $order,
            'label' => $label,
            'show_ship' => $show_ship,
            'reminder' => $reminder,
            'item_options' => $item_options,
            'thumbs' => $thumbs,
            'status' => 200
        ], 200);
    }

    public function reprintWapLabel(Request $request)
    {

        $bin = Wap::find($request->get('bin_id'));

        $item = Item::find($request->get('item_id'));

        $count = Item::where('order_5p', $bin->order_id)
            ->searchStatus('shippable')
            ->where('is_deleted', '0')
            ->count();

        $label = $this->getLabel($bin, $item, $count);

        return response()->json([
            'show_ship' => '1',
            'label' => $label,
            'bin' => $request->get('bin_id'),
        ], 200);
    }

    private function getLabel($bin, $item, $count)
    {

        $date = date("Y-m-d H:i:s");
        $wap_item = WapItem::where('item_id', $item->id)->first();
        $order = $item->order;

        if ($wap_item && ($wap_item->item_count == $count && $order?->order_status == 4)) {
            $box = "^FO150,50^GB475,180,120^FS";
        } else {
            $box = '';
        }

        if (isset($order->order_status) && $order->order_status == 4) {
            $title = '^CF0,200^FO200,60^FR^AC^FDWAP^FS';
        } else {
            $title = '^CF0,100^FO125,60^FR^AC^FDWAP HOLD^FS';
        }

        $label = "^XA^FX$box$title^FO50,230^GB700,1,3^FS" .
            "^FX^CF0,30^FO50,260^FDItem ID: $item->id^FS^FO350,260^FDOrder ID: $order?->short_order^FS^FO50,300^FDBatch: $item->batch_number^FS" .
            "^FO350,300^FDOrder Date: $order?->order_date^FS" .
            "^FO50,340^FDPrinted: $date^FS^FO50,370^GB700,1,3^FS^FO50,370^GB700,1,3^FS" .
            "^FO50,400^FDSKU: $item->child_sku^FS^FO50,440^FB750,3,,^FDQTY: $item->item_quantity^FS" .
            "^FO50,480^FB750,3,,^FD$item->item_description^FS" .
            "^FO75,520^FB550,6,,^FD" . Helper::optionTransformer($item->item_option, 1, 0, 0, 1, 0, ' , ') . "^FS" .
            "^FX^FO50,725^GB700,250,3^FS^CF0,100^FO100,750^FDBin $bin->name^FS^FO100,850^FD Item $wap_item->item_count of $count ^FS" .
            "^FX^BY4,4,100^FO100,1000^BC^FDORDR$item->order_5p^FS^XZ";

        return str_replace("'", " ", $label);
    }

    public function addItems(Request $request)
    {
        $batch = Batch::find($request->get('id'));

        if ($batch->batch_number != $request->get('batch_number')) {
            return response()->json([
                'message' => 'Please scan user ID',
                'status' => 203
            ], 203);
        }

        if ($request->get('action') == 'address') {

            $order = Order::find($request->get('order_id'));

            if ($order->order_status == 4) {
                Log::info('Order ' . $request->get('order_id') . ' added to WAP as address hold.');
                $order->order_status = 11;
                $order->save();
            } else {
                Log::info('Order ' . $request->get('order_id') . ' failed to add to WAP as address hold.');
                return response()->json([
                    'message' => 'Order status not IN PROGRESS, cannot change status',
                    'status' => 203
                ], 203);
            }
        }

        $items = Item::where('order_5p', $request->get('order_id'))
            ->where('batch_number', $request->get('batch_number'))
            ->searchStatus('production')
            ->where('is_deleted', '0')
            ->get();

        $label = '';

        $shippable = Item::where('order_5p', $request->get('order_id'))
            ->searchStatus('shippable')
            ->where('is_deleted', '0')
            ->get();

        $item_codes = $shippable->pluck('item_code')->toArray();

        $size = 'A';

        // foreach ($item_codes as $sku) {
        //   if (substr($sku, 0, 2) == 'RW' || substr($sku, 0, 2) == 'RG' || substr($sku, 0, 2) == 'SD') {
        //     $size = 'B';
        //   }
        // }

        $bin = $this->findBin($request->get('order_id'), $size);

        $user = auth()->user()->id;

        $count = count($bin->items) + 1;

        foreach ($items as $item) {
            $wapitem = new WapItem;
            $wapitem->item_id = $item->id;
            $wapitem->bin_id = $bin->id;
            $wapitem->user_id = $user;
            $wapitem->item_count = $count++;
            $wapitem->save();

            $label .= $this->getLabel($bin, $item, count($shippable));

            $item->item_status = 'WAP';
            $item->save();

            Order::note('Item ' . $item->id . ' added to WAP Bin ' . $bin->name, $item->order_5p, $item->order_id);
        }

        // $request->session()->put('label', $label);

        return response()->json([
            'params' => [
                'id' => $request->get('id'),
                'batch_number' => $request->get('batch_number'),
                'label' => 'session',
                'order_id' => $request->get('order_id'),
            ],
            'message' => 'Items added to WAP',
            'status' => 201
        ], 201);
    }

    private function findBin($order_id, $size = NULL)
    {
        //find order bin
        $bin = Wap::where('order_id', $order_id)->first();

        if (empty($bin)) {
            //determine size
            //$size = $this->binSize($order_id);
            // $size = 'A';
            //find empty bin
            $bin_assigned = Wap::whereNull('order_id')
                ->where('size', $size)
                ->orderBy('id', 'ASC')
                ->limit(1)
                ->update([
                    'order_id' => $order_id
                ]);

            if ($bin_assigned == 1) {
                $bin = Wap::where('order_id', $order_id)->first();
            } else {
                //create bin
                $bin = $this->createBin($order_id, $size);
            }
        }

        return $bin;
    }

    private function createBin($order_ID, $size = NULL)
    {
        //add a wap bin
        $last = Wap::where('size', $size)
            ->orderBy('id', 'DESC')
            ->first();

        if ($last) {
            $num = $last->num + 1;
        } else {
            $num = 1000;
        }

        $bin = new Wap;
        $bin->size = $size;
        $bin->num = $num;
        $bin->name = sprintf('%s-%04d', $bin->size, $bin->num);
        $bin->order_ID = $order_ID;
        $bin->save();

        return $bin;
    }

    public function badAddress(Request $request)
    {
        $order = Order::find($request->get('order_id'));

        if (!$order) {
            return response()->json([
                'message' => 'Order not found',
                'status' => 203
            ], 203);
        }

        if ($order->order_status == 4) {
            Log::info('Order ' . $request->get('order_id') . ' updated in WAP to address hold.');
            $order->order_status = 11;
            $order->save();

            return response()->json([
                'message' => $order->short_order . ' Address Sent to Customer Service',
                'status' => 201
            ], 201);
        } else {
            return response()->json([
                'message' => $order->short_order . ' Cannot be placed in Address Hold, order is not in progress',
                'status' => 203
            ], 203);
        }
    }
}
