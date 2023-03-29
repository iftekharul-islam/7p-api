<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $fillable = [
        'vendor_name',
        'email',
        'zip_code',
        'state',
        'phone_number',
        'country',
        'image',
        'contact_person_name',
        'link',
        'login_id',
        'password',
        'bank_info',
        'paypal_info',
        'notes'
    ];

    use HasFactory;
}
