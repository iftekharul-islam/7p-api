<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'head_id',
        'description'
    ];

    /**
     * Get all of the comments for the Division
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function departments()
    {
        return $this->hasMany(Department::class, 'division_id');
    }

    /**
     * Get the user associated with the Division
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function head()
    {
        return $this->hasOne(User::class, 'id', 'head_id');
    }
}
