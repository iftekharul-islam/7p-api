<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

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
}
