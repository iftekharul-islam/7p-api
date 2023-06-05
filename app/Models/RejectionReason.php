<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RejectionReason extends Model
{
    use HasFactory;

    protected $fillable = [
        'rejection_message',
        'sort_order'
    ];

    public static function getReasons()
    {
        return RejectionReason::where('is_deleted', '0')
            ->orderBy('sort_order')
            ->get()
            ->pluck('rejection_message', 'id');
    }
}
