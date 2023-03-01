<?php

namespace App\Models;

use App\Traits\LogPreference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Designation extends Model
{
    use HasFactory, LogPreference;

    /**
     * The name of the logs to differentiate
     *
     * @var string
     */
    protected $logName = 'designations';

    protected $fillable = [
        'id', 'name', 'description'
    ];

    public function employees()
    {
        return $this->hasMany(User::class);
    }
}
