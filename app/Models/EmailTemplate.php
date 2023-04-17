<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $table = "email_templates";

    protected $fillable = [
        'message_type',
        'message_title',
        'message',
        'type',
        'is_deleted',
    ];
}
