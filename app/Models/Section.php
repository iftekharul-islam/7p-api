<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_name',
        'summaries',
        'start_finish',
        'same_user',
        'print_label',
        'inventory',
        'inv_control',
        'ret_name',
        'ret_address_1',
        'ret_address_2',
        'ret_city',
        'ret_state',
        'ret_zipcode',
        'ret_phone_number',
        'is_deleted',
    ];


}
