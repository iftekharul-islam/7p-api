<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    public $timestamps = false;

    public static function orderConfirm($order)
    {

        //TODO: This is a temporary fix to prevent the order confirmation email from being sent to the customer.

        // $store = Store::where('store_id', $order->store_id)->first();

        // if ($store->confirm == '2' || $store->confirm == '3') {
        //     if ( Appmailer::storeConfirmEmail($store, $order, 'emails.order_confirm') ) {
        //           Log::info(sprintf("Order Confirmation Email sent to %s Order# %s.", $order->customer->bill_email, $order->order_id));

        //           $record = new Notification;
        //           $record->type = 'Order Confirmation';
        //           $record->order_5p = $order->id;
        //           $record->save();
        //     } else {
        //       Order::note('Email Order Confirmation Failed to Send', $order->id, $order->order_id);
        //     }
        // }

        // if ($store->confirm == '1' || $store->confirm == '3') {
        //     $className =  'Market\\' . $store->class_name; 
        //     $controller =  new $className;
        //     $controller->orderConfirmation($store->store_id, $order);
        // }
    }
}
