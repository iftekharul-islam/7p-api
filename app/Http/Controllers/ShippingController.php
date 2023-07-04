<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Ship\Shipper;

class ShippingController extends Controller
{
    public function manualShip(Request $request)
    {
        /*
         * Stops orders from being duplicated
         */
        $track = $request->get('track_number', "ERR");

        if (Cache::has("TRACKING_DUPLICATE_$track")) {
        } else {
            Cache::add("TRACKING_DUPLICATE_$track", $track, 60 * 3);
        }

        if (strlen($request->get('track_number')) > 0) {

            $shipper = new Shipper;

            $info = $shipper->enterTracking(
                $request->get('track_item_id'),
                $request->get('track_order_id'),
                $request->get('track_number'),
                $request->get('method')
            );

            if (is_array($info)) {
                $shipper->setOrderFulfillment($request->get('track_shopify_order_id'), $request->get('track_shopify_item_line_id'), $request->get('track_shopify_item_quantity'), $request->get('track_number'), $request->get('method')); // method = $trackingCompany
                return redirect()->action('ShippingController@index', [
                    'unique_order_id' => $info['unique_order_id'],
                    'reminder' => $info['reminder']
                ]);
            } else {
                return response()->json([
                    'message' => $info,
                    'status' => 203
                ], 203);
                // return redirect()->back()->withErrors($info);
            }
        } else {
            return response()->json([
                'message' => 'Tracking number not set',
                'status' => 203
            ], 203);
            // return redirect()->back()->withErrors(['error' => "Tracking number not set"]);
        }
        return response()->json([
            'message' => 'Updated Successfully',
            'status' => 201
        ], 201);
    }
}
