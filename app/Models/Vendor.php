<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'image',
        'name',
        'zip_code',
        'state',
        'country',
        'email',
        'phone_number',
        'contact_person_name',
        'account_link',
        'account_login',
        'account_password',
        'bank_info',
        'paypal_info',
        'notes'
    ];
}
