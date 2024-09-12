<?php

namespace App\Http\Controllers;



use App\Models\Batch;
use App\Models\Ship;
use App\Models\Order;
use App\Models\Note;


class DataController extends Controller
{

    public function mo_orders () {

        set_time_limit(0);

        $csv = new CSV;
        $results = $csv->intoArray(storage_path() . '/order_eval/discount_upsell.csv', ",");

        foreach ($results as $line) {

            $order = Order::with('items')
                ->where('store_id', '52053152')
                ->where('short_order', $line[0])
                ->where('is_deleted', '0')
                ->first();

            if (count($order) == 0) {
                continue;
            } else {

                $order->item_count = $order->items->sum('item_quantity');
                $subtotal = 0;

                foreach ($order->items as $item) {
                    $subtotal += ($item->item_quantity * $item->item_unit_price);
                }

                if (abs($line[2]) > .009) {
                    if ($order->coupon_id != null && $order->coupon_id != '') {
                        $coupon = true;
                    } else {
                        $coupon = false;
                    }

                    if ($order->promotion_id != null && $order->promotion_id != '') {
                        $promotion = true;
                    } else {
                        $promotion = false;
                    }

                    if ($coupon && !$promotion) {
                        $order->coupon_value = abs($line[2]);
                        $order->promotion_id = 'Discount upsell';
                        $order->promotion_value = abs($line[9]);
                    } else if ($promotion && !$coupon) {
                        $order->promotion_value = abs($line[2]);
                        $order->coupon_id = 'Discount upsell';
                        $order->coupon_value = abs($line[9]);
                    } else {
                        echo $line[0];
                        continue;
                    }
                }

                $order->shipping_charge = $line[3];
                $order->tax_charge = $line[5];

                $order->total =  $subtotal + $line[2] + $line[9] + $line[3] + $line[5];
                $order->save();
            }

        }

        return;
    }

    public function mo_orders5 () {

        set_time_limit(0);

        $csv = new CSV;
        $results = $csv->intoArray(storage_path() . '/order_eval/unit_problem.csv', ",");

        $fall_through_units = array();

        foreach ($results as $line) {

            $order = Order::with('items')
                ->where('store_id', '52053152')
                ->where('short_order', $line[0])
                ->where('is_deleted', '0')
                ->first();

            if (count($order) == 0) {
                continue;
            } else {

                $subtotal = 0;
                $div_subtotal = 0;

                foreach ($order->items as $item) {
                    $subtotal += ($item->item_quantity * $item->item_unit_price);
                    $div_subtotal += $item->item_unit_price;
                }

                if (abs($line[4] - $div_subtotal) < .01) {

                    $subtotal = 0;
                    foreach ($order->items as $item) {
                        $item->item_unit_price = $item->item_unit_price / $item->item_quantity;
                        $item->save();
                        $subtotal += ($item->item_quantity * $item->item_unit_price);
                    }

                    if (abs($line[2]) > .009) {
                        if ($order->coupon_id != null && $order->coupon_id != '') {
                            $coupon = true;
                        } else {
                            $coupon = false;
                        }

                        if ($order->promotion_id != null && $order->promotion_id != '') {
                            $promotion = true;
                        } else {
                            $promotion = false;
                        }

                        if ($coupon && !$promotion) {
                            $order->coupon_value = abs($line[2]);
                            $order->promotion_value = null;
                        } else if ($promotion && !$coupon) {
                            $order->promotion_value = abs($line[2]);
                            $order->coupon_value = null;
                        } else {
                            $coupon_problem[] = $line;
                            continue;
                        }
                    }

                    $order->shipping_charge = $line[3];
                    $order->tax_charge = $line[5];


                    $order->save();

                } else {
                    $fall_through_units[] = $line;
                }
            }
        }

        $pathToFile = $csv->createFile($fall_through_units, storage_path() . '/order_eval' , null, '/fall_through_units.csv');

        return;
    }

    public function mo_orders4 () {

        set_time_limit(0);

        $csv = new CSV;
        $results = $csv->intoArray(storage_path() . '/order_eval/item_not_added.csv', ",");

        $coupon_problem = array();
        $fall_through = array();
        $unit_problem = array();

        foreach ($results as $line) {
            $order = Order::with('items')
                ->where('store_id', '52053152')
                ->where('short_order', $line[0])
                ->where('is_deleted', '0')
                ->first();


            if (count($order) == 0) {
                continue;
            } else {

                $notes = Note::where('order_5p', $order->id)
                    ->where('note_text', 'LIKE', 'CS: Item%added to order')
                    ->get();

                echo count($notes) . '<br>';

                $add_ids = array();

                foreach ($notes as $note) {
                    $add_ids[] = substr($note->note_text, 9, 6);
                }

                $add_subtotal = 0;
                $subtotal = 0;
                $div_subtotal = 0;

                foreach ($order->items as $item) {
                    $subtotal += ($item->item_quantity * $item->item_unit_price);
                    if (in_array($item->id, $add_ids)) {
                        $add_subtotal += ($item->item_quantity * $item->item_unit_price);
                    }
                    $div_subtotal += $item->item_unit_price;
                }

                $diff = abs($subtotal - $line[4]);

                if (abs($line[4] - $div_subtotal) < .01) {

                    $unit_problem[] = $line;

                } else if (abs($diff - $add_subtotal) < .01) {


                    if (abs($line[2]) > .009) {
                        if ($order->coupon_id != null && $order->coupon_id != '') {
                            $coupon = true;
                        } else {
                            $coupon = false;
                        }

                        if ($order->promotion_id != null && $order->promotion_id != '') {
                            $promotion = true;
                        } else {
                            $promotion = false;
                        }

                        if ($coupon && !$promotion) {
                            $order->coupon_value = abs($line[2]);
                            $order->promotion_value = null;
                        } else if ($promotion && !$coupon) {
                            $order->promotion_value = abs($line[2]);
                            $order->coupon_value = null;
                        } else {
                            $coupon_problem[] = $line;
                            continue;
                        }
                    }

                    $order->shipping_charge = $line[3];
                    $order->tax_charge = $line[5];

                    // $total = $subtotal + $line[2] + $line[3] + $line[5];
                    // $order->adjustment = $line[6] - $total;

                    $order->total =  $subtotal + $line[2] + $line[3] + $line[5];
                    $order->save();

                } else {
                    $fall_through[] = $line;
                }
            }
        }

        $pathToFile = $csv->createFile($coupon_problem, storage_path() . '/order_eval' , null, '/coupon_problem.csv');
        $pathToFile = $csv->createFile($fall_through, storage_path() . '/order_eval' , null, '/fall_through.csv');
        $pathToFile = $csv->createFile($unit_problem, storage_path() . '/order_eval' , null, '/unit_problem.csv');

        return;
    }

    private function mo_orders3 () {

        set_time_limit(0);

        $csv = new CSV;
        $results = $csv->intoArray(storage_path() . '/order_eval/still_wrong.csv', ",");

        $wrong_subtotal = array();
        $coupon_problem = array();

        foreach ($results as $line) {
            $order = Order::with('items')
                ->where('store_id', '52053152')
                ->where('short_order', $line[0])
                ->where('is_deleted', '0')
                ->first();


            if (count($order) == 0) {
                continue;
            } else {
                $subtotal = 0;
                foreach ($order->items as $item) {
                    $subtotal += ($item->item_quantity * $item->item_unit_price);
                }

                if (abs($subtotal -$line[4]) < .01) {

                    if (abs($line[2]) > .009) {
                        if ($order->coupon_id != null && $order->coupon_id != '') {
                            $coupon = true;
                        } else {
                            $coupon = false;
                        }

                        if ($order->promotion_id != null && $order->promotion_id != '') {
                            $promotion = true;
                        } else {
                            $promotion = false;
                        }

                        if ($coupon && !$promotion) {
                            $order->coupon_value = abs($line[2]);
                            $order->promotion_value = null;
                        } else if ($promotion && !$coupon) {
                            $order->promotion_value = abs($line[2]);
                            $order->coupon_value = null;
                        } else {
                            $coupon_problem[] = $line;
                            continue;
                        }
                    }

                    $order->shipping_charge = $line[3];
                    $order->tax_charge = $line[5];

                    // $total = $subtotal + $line[2] + $line[3] + $line[5];
                    // $order->adjustment = $line[6] - $total;

                    // $order->total = $line[6];
                    $order->save();

                    // Order::note('Adjustment added for discount upsell');

                } else {
                    $wrong_subtotal[] = $line;
                    continue;
                }
            }

        }

        $pathToFile = $csv->createFile($wrong_subtotal, storage_path() . '/order_eval' , null, '/wrong_subtotal.csv');
        $pathToFile = $csv->createFile($coupon_problem, storage_path() . '/order_eval' , null, '/coupon_problem.csv');
        // $pathToFile = $csv->createFile($still_wrong, storage_path() . '/order_eval' , null, '/still_wrong.csv');

        return;
    }

    public function mo_orders1 () {

        set_time_limit(0);

        $csv = new CSV;
        $results = $csv->intoArray(storage_path() . '/order_eval/mo_orders.csv', ",");

        $wrong_subtotal = array();
        $coupon_problem = array();
        $still_wrong = array();
        $item_added = array();

        foreach ($results as $line) {
            $notes = 0;

            $order = Order::with('items')
                ->where('store_id', '52053152')
                ->where('short_order', $line[0])
                ->where('is_deleted', '0')
                ->first();


            if (count($order) == 0) {
                continue;
            } else {
                $subtotal = 0;

                foreach ($order->items as $item) {
                    $subtotal += ($item->item_quantity * $item->item_unit_price);
                }

                if (abs($subtotal -$line[4]) > .009) {
                    $notes = Note::where('order_5p', $order->id)
                        ->where('note_text', 'LIKE', 'CS: Item%added to order')
                        ->count();

                    if ($notes > 0) {
                        $item_added[] = $line;
                    } else {
                        $wrong_subtotal[] = $line;
                        continue;
                    }

                }


                if (abs($line[2]) > .009) {
                    if ($order->coupon_id != null && $order->coupon_id != '') {
                        $coupon = true;
                    } else {
                        $coupon = false;
                    }

                    if ($order->promotion_id != null && $order->promotion_id != '') {
                        $promotion = true;
                    } else {
                        $promotion = false;
                    }

                    if ($coupon && !$promotion) {
                        $order->coupon_value = abs($line[2]);
                        $order->promotion_value = null;
                    } else if ($promotion && !$coupon) {
                        $order->promotion_value = abs($line[2]);
                        $order->coupon_value = null;
                    } else {
                        $coupon_problem[] = $line;
                        continue;
                    }
                }

                $order->shipping_charge = $line[3];
                $order->tax_charge = $line[5];

                $total = $subtotal + $line[2] + $line[3] + $line[5];

                if (abs($total - $line[6]) < .01 || $notes > 0)  {
                    $order->total = $line[6];
                    $order->save();
                } else {
                    $still_wrong[] = $line;
                    continue;
                }
            }
        }

        $pathToFile = $csv->createFile($item_added, storage_path() . '/order_eval' , null, '/item_added.csv');
        $pathToFile = $csv->createFile($wrong_subtotal, storage_path() . '/order_eval' , null, '/subtotal_wrong.csv');
        $pathToFile = $csv->createFile($coupon_problem, storage_path() . '/order_eval' , null, '/coupon_problem.csv');
        $pathToFile = $csv->createFile($still_wrong, storage_path() . '/order_eval' , null, '/still_wrong.csv');

        return;
    }

    public function note_job () {
        $f = new fixup;
        $f->note_orderID();
    }

    public function cleanup()
    {
        $ships = Ship::leftjoin('items', 'items.tracking_number', '=', 'shipping.tracking_number')
            ->whereNull('items.tracking_number')
            ->where('transaction_datetime', '>', '2017-06-07 00:00:00')
            ->where('shipping.is_deleted', '0')
            ->selectRaw('shipping.*')
            ->get();

        foreach ($ships as $ship) {
            // echo 'Problem Shipment: ' . $ship->unique_order_id . '<br>';
            $ship->is_deleted = '1';
            $ship->save();
        }


        // $null_section = Batch::where(function ($query) {
        //                   $query->whereNull('production_station_id')
        //                         ->orWhereNull('section_id');
        //                   })
        //                   ->get();
        //
        // foreach($null_section as $batch) {
        //   echo 'NULL Section : ' . $batch->batch_number . '<br>';
        // }

        $bad_batch_status = Batch::where(function ($query) {
            $query->whereNull('status')
                ->orWhere('status', 0);
        })
            ->get();

        foreach($bad_batch_status as $batch) {
            Batch::isFinished($batch->batch_number);
            // echo 'Bad Batch Status : ' . $batch->batch_number . '<br>';
        }

        $held_batches = Batch::join('items', 'batches.batch_number', '=', 'items.batch_number')
            ->where('batches.status', 3)
            ->whereNotIn('items.item_status', [3,7])
            ->where('items.is_deleted', '0')
            ->get();

        foreach($held_batches as $batch) {
            Batch::isFinished($batch->batch_number);
            // echo 'Held Batch with wrong item status : ' . $batch->batch_number . '<br>';
        }

        $held_items = Batch::join('items', 'batches.batch_number', '=', 'items.batch_number')
            ->where('batches.status', '!=', 3)
            ->whereIn('items.item_status', [3,7])
            ->where('items.is_deleted', '0')
            ->get();

        foreach($held_items as $batch) {
            Batch::isFinished($batch->batch_number);
            // echo 'Held Items with wrong batch status : ' . $batch->batch_number . '<br>';
        }

        $bo_batches = Batch::join('items', 'batches.batch_number', '=', 'items.batch_number')
            ->where('batches.status', 4)
            ->where('items.item_status', '!=', 4)
            ->where('items.is_deleted', '0')
            ->get();

        foreach($bo_batches as $batch) {
            Batch::isFinished($batch->batch_number);
            // echo 'Back order batches with wrong item status : ' . $batch->batch_number . '<br>';
        }

        $bo_items = Batch::join('items', 'batches.batch_number', '=', 'items.batch_number')
            ->where('batches.status', '!=', 4)
            ->where('items.item_status', 4)
            ->where('items.is_deleted', '0')
            ->get();

        foreach($bo_items as $batch) {
            Batch::isFinished($batch->batch_number);
            // echo 'Back order Items with wrong batch status : ' . $batch->batch_number . '<br>';
        }

    }


    private function activeBatches()
    {
        $batches = Batch::with('items')
            ->searchStatus('active')
            ->get();

        $problems = array();

        foreach ($batches as $batch) {
            $prod = 0;

            foreach ($batch->items as $item) {
                if ($item->item_status == 'production') {
                    $prod += 1;
                }
            }

            if ($prod == 0) {
                $problems[] = $batch->batch_number;
            }
        }

        return $problems;
    }

    private function Orders()
    {

    }


    private function Items()
    {

    }
}
