<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Order;
use App\Models\Ship;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use library\AppMailer;

class NotificationController extends Controller
{
    public function sendMail(Request $request)
    {
        $message_type = $request->get('message_types');
        $subject = $request->get('subject');
        $recipient = $request->get('recipient');
        $order_id = $request->get('order_5p');

        $message = '<html><head><style>p{margin-bottom:2em;}</style><body>' .
            $request->get('message') .
            '</body></html>';

        $order = Order::with('customer', 'store')
            ->where('id', $order_id)
            ->where('is_deleted', '0')
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Order not found',
                'status' => 203
            ], 203);
        }

        $from = $order->store->email;
        $from_name = $order->store->store_name;

        //TODO - add email funtion
        // if (AppMailer::sendMessage($from, $from_name, $recipient, $subject, $message)) {

        //     Order::note("Email Subj:" . $subject, $order->id, $order->order_id);

        //     $record = new Notification;
        //     $record->type = $subject;
        //     $record->order_5p = $order->id;
        //     $record->save();

        //     return response()->json([
        //         'message' => 'E-mail sent to customer',
        //         'status' => 201
        //     ], 201);
        // } else {

        //     Log::error('Message Failed to Send:' . $subject . ' - ' . $order->order_id);
        //     Order::note("Message Failed to Send:" . $subject, $order->id, $order->order_id);

        //     return response()->json([
        //         'message' => 'E-mail failed to send',
        //         'status' => 203
        //     ], 203);
        // }
        return response()->json([
            'message' => 'E-mail sent to customer',
            'status' => 201
        ], 201);
    }

    public static function shipNotify () {

        $ships = Ship::with('order.customer', 'items')
            ->whereNull('shipping_unique_id')
            ->whereNotNull('tracking_number')
            ->groupBy('unique_order_id')
            ->orderBy('id', 'ASC')
            ->take(500)
            ->get();

        if (count($ships) < 1) {
            return;
        }

        $orders = array();

        $stores = array();

        foreach ($ships as $ship){
            Ship::where('order_number', $ship->order_number)
                ->update([
                    'shipping_unique_id' => 'pro',
                ]);

            $stores[$ship->order->store_id][] = $ship;
        }

        foreach ($stores as $store_id => $shipments) {

            $store = Store::where('store_id', $store_id)->first();

            if ($store->ship == '2' || $store->ship == '3') {
                set_time_limit(0);

                foreach ($shipments as $shipment) {

                    $order = $shipment->order;

                    if ( !$order->customer->bill_email ) {

                        Log::error('No email address found for order ' . $order->id);
                        Ship::where('id',  $shipment->id)
                            ->update([
                                'shipping_unique_id' => 'No Email',
                            ]);

                    } else { // if (!substr($order->items->first()->item_code, 0, 3) == 'KIT') {

                        if(AppMailer::storeConfirmEmail($store, $order, 'emails.ship_confirm')){
                            Log::info( sprintf("Shipping Confirmation Email sent to %s Order %s.", $order->customer->bill_email, $order->id) );

                            Ship::where('id',  $shipment->id)
                                ->update([
                                    'shipping_unique_id' => 's',
                                ]);

                            $record = new Notification;
                            $record->type = 'Shipment Notification ' . $shipment->unique_order_id;
                            $record->order_5p = $order->id;
                            $record->save();

                        } else {

                            Ship::where('id',  $shipment->id)
                                ->update([
                                    'shipping_unique_id' => 'Not',
                                ]);

                            Order::note('Email Shipping Confirmation Failed to Send', $order->id);
                            Log::error('No shipping confirmation email sent for order# '.$order->id);
                        }
                        // } else {
                        //     Log::info('No shipping confirmation email sent for order# '.$order->id);
                    }
                }
            }

            if ($store->ship == '1' || $store->ship == '3') {
                $className =  'Market\\' . $store->class_name;
                $controller =  new $className;

                try {
                    $controller->shipmentNotification($store->store_id, $shipments);
                } catch (\Exception $e) {
                    Log::error('Shipment Notification failure ' . $store->store_name);
                }

            }
        }

    }
}
