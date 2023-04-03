<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rejection extends Model
{
    use HasFactory;

    public function rejection_reason_info()
    {
        return $this->belongsTo(RejectionReason::class, 'rejection_reason', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'rejection_user_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_user_id', 'id');
    }

    public function from_station()
    {
        return $this->belongsTo(Station::class, 'from_station_id', 'id');
    }

    public function from_batch_info()
    {
        return $this->belongsTo(Batch::class, 'from_batch', 'batch_number');
    }

    public static function graphicStatus($reject = 0)
    {
        $statuses = array();
        $statuses['0'] = 'Select Status';
        $statuses['1'] = 'Re-Print Graphic - No Change';
        $statuses['2'] = 'Re-Work Graphic - Changes Required';
        //$statuses['3'] = 'Graphic Still Good - No New Graphic Required';

        if ($reject == 0) {
            $statuses['4'] = 'Customer Service Issue';
        }

        $statuses['5'] = 'Customer Service Solved';
        //$statuses['6'] = 'Production Issue';
        $statuses['7'] = 'Redo Item';
        return $statuses;
    }

    public function getGraphicStatusAttribute($value)
    {
        if ($value == 1) {
            return 'Re-Print';
        } elseif ($value == 2) {
            return 'Re-Work';
        } elseif ($value == 3) {
            return 'Still Good';
        } elseif ($value == 4) {
            return 'Customer Service Issue';
        } elseif ($value == 5) {
            return 'Customer Service Solved';
        } elseif ($value == 6) {
            return 'Production Issue';
        } elseif ($value == 7) {
            return 'Redo Item';
        } else {
            return '?';
        }
    }

    public function scopeSearchItem($query, $item_id)
    {
        if (!$item_id) {
            return;
        }

        return $query->where('item_id', $item_id);
    }
}
