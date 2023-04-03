<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RejectionReason extends Model
{
    use HasFactory;

    public static function getReasons()
    {
        return RejectionReason::orderBy('sort_order')
            ->get()
            ->pluck('rejection_message', 'id')
            ->prepend('Select a reason', 0);
    }
}