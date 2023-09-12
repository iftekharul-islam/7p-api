<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Task extends Model
{
    use HasFactory;

    public static function new(
        $text,
        $user_id = null,
        $model = null,
        $id = null,
        $close_event = null,
        $previous_task = null,
        $attachment = null,
        $due_date = null
    ) {
        $task = new Task;

        if (auth()->user()) {
            $task->create_user_id = auth()->user()->id;
        } else {
            $task->create_user_id = 87;
        }

        if ($user_id == null && $task->create_user_id != 87) {
            $task->assigned_user_id = $task->create_user_id;
        } else if ($user_id != null) {
            $task->assigned_user_id = $user_id;
        } else {
            Log::error('Error creating task: No user assigned - ' . $text);
            return false;
        }
        $task->text = $text;
        $task->taskable_type = $model;
        $task->taskable_id = $id;
        $task->close_event = $close_event;
        $task->previous_task_id = $previous_task;
        $task->attachment = $attachment;
        $task->due_date = $due_date;

        $task->save();

        return $task;
    }

    public function create_user()
    {
        return $this->belongsTo(User::class, 'create_user_id', 'id');
    }

    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id');
    }

    public function taskable()
    {
        return $this->morphTo();
    }

    public function notes()
    {
        return $this->hasMany(TaskNote::class, 'task_id', 'id');
    }

    public function scopeSearchUser($query, $id)
    {

        if (!$id || $id == 'ALL') {
            return;
        }

        return $query->where('assigned_user_id', $id);
    }

    public function scopeSearchCreator($query, $id)
    {

        if (!$id || $id == 'ALL') {
            return;
        }

        return $query->where('create_user_id', $id);
    }

    public function scopeSearchStatus($query, $status)
    {

        if ($status == 'ALL' || $status == 'all') {
            return;
        } else if (!$status) {
            $status = 'O';
        }

        return $query->where('status', $status);
    }

    public static function getTables()
    {

        return [
            'App\Models\Order'    => 'Order',
            'App\Models\Batch'    => 'Batch',
            'App\Models\Product'  => 'Product',
            // 'App\Models\Item'     => 'Item',
            'App\Models\Option'   => 'Child SKU',
            'App\Models\Purchase' => 'Purchase Order',
            'App\Models\Inventory' => 'Inventory Item',
            // 'App\Ship'     => 'Shipment',
        ];
    }

    public static function findTaskable($str, $class)
    {
        if (!$str || !$class) {
            return;
        }

        $str = trim($str);

        if ($class == 'App\Models\Order') {
            $result = Order::where('orders.short_order', 'LIKE', '%' . $str . '%')
                ->orWhere('orders.order_id', 'LIKE', '%' . $str . '%')
                ->orWhere('orders.id', $str)
                ->selectRaw('id')
                ->get();
        } else if ($class == 'App\Models\Batch') {

            $result = Batch::where('batch_number', 'LIKE', '%' . $str . '%')
                ->orWhere('id', $str)
                ->select('id')
                ->get();
        } else if ($class == 'App\Models\Product') {

            $result = Product::where('product_model', 'LIKE', $str)
                ->orWhere('product_name', 'LIKE', '%' . $str . '%')
                ->orWhere('id', $str)
                ->select('id')
                ->get();
        } else if ($class == 'App\Models\Item') {

            $result = Item::where('child_sku', 'LIKE', '%' . $str . '%')
                ->orWhere('item_description', 'LIKE', '%' . $str . '%')
                ->orWhere('id', $str)
                ->select('id')
                ->get();
        } else if ($class == 'App\Models\Option') {

            $result = Option::where('child_sku', 'LIKE', '%' . $str . '%')
                ->orWhere('id', $str)
                ->select('id')
                ->get();
        } else if ($class == 'App\Models\Purchase') {
            $result = Purchase::where('po_number', 'LIKE', '%' . $str . '%')
                ->orWhere('id', $str)
                ->select('id')
                ->get();
        } else if ($class == 'App\Models\Inventory') {

            $result = Inventory::where('stock_no_unique', $str)
                ->orWhere('stock_name_discription', 'LIKE', '%' . $str . '%')
                ->orWhere('id', $str)
                ->select('id')
                ->get();
        } else if ($class == 'App\Models\Ship') {

            $result = Ship::where('unique_order_id', 'LIKE', '%' . $str . '%')
                ->orWhere('shipping_id', 'LIKE', '%' . $str . '%')
                ->orWhere('tracking_number', 'LIKE', '%' . $str . '%')
                ->orWhere('id', $str)
                ->select('id')
                ->get();
        }

        if ($result) {
            return array_unique($result->pluck('id')->toArray());
        } else {
            return false;
        }
    }

    public function scopeSearchTaskable($query, $ids, $class)
    {
        if (count($ids) < 1 || !$class) {
            return;
        }

        return $query->where('taskable_type', $class)
            ->whereIn('taskable_id', $ids);
    }

    public static function getTaskable($id, $class)
    {

        if ($class == null) {
            return null;
        }

        $obj = call_user_func($class . '::find',  $id);

        if (!$obj) {
            return null;
        }

        return $obj->outputArray();
    }

    public static function outputTaskable($info)
    {

        if (!$info) {
            return '';
        }

        if (!is_array($info)) {
            $info = $info->outputArray();
        }

        $widget = Task::widget($info[0], $info[1], null, 15);

        return  '<a href = "' . $info[2] . '" target = "_blank">' . $info[3] . '</a> ' . $widget .
            '<br>' . $info[4] . '<br>' . $info[5];
    }

    public static function widget($type, $id, $color = null, $size = null)
    {

        if ($type == 'user') {
            $unread = Task::where('assigned_user_id', $id)
                ->where('status', 'O')
                ->where('msg_read', '=', '0')
                ->count();

            $all_msg = Task::where('assigned_user_id', $id)
                ->where('status', 'O')
                ->count();

            $widget = '<a href="/tasks">';

            $widget .= '<span class="glyphicon glyphicon-send"  style="font-size:27px;"></span>';

            if ($unread > 0) {
                $s = '';
                if ($unread > 1) {
                    $s = 'S';
                }
                $widget .= '<span class="label label-pill label-danger blink_me" style="font-size:30px;">' . $all_msg . ' NEW TASK' . $s . '</span>';
            } else if ($all_msg > 0) {
                $widget .= '<span class="label label-pill label-default" style="font-size:12px;">' . $all_msg . '</span>';
            }

            $widget .= '</a>';
        } else {

            if ($size != null) {
                $send_size = ' style="font-size:' . $size . 'px;" ';
                $pill_size = ' style="font-size:' . intval($size * .65) . 'px;" ';
            } else {
                $send_size = ' style="font-size:20px;" ';
                $pill_size = ' style="font-size:12px;" ';
            }

            $open = Task::where('taskable_type', $type)
                ->where('taskable_id', $id)
                ->where('status', 'O')
                ->count();

            $all_msg = Task::where('taskable_type', $type)
                ->where('taskable_id', $id)
                ->count();

            $widget = '<a href="/tasks?id=' . $id . '&search_in=' . $type . '&status=ALL" target="_blank">';

            $widget .= '<span class="glyphicon glyphicon-send ' . $color . '"' . $send_size . '></span>';

            if ($open > 0) {
                $widget .= '<span class="label label-pill label-warning" ' . $pill_size . '>' . $all_msg . '</span>';
            } else if ($all_msg > 0) {
                $widget .= '<span class="label label-pill label-default ' . $color . '"' . $pill_size . '>' . $all_msg . '</span>';
            }

            $widget .= '</a>';
        }

        return $widget;
    }
}
