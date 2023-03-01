<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'division_id',
        'name',
        'head_id',
        'description'
    ];

    /**
     * Get the user that owns the Department
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function division()
    {
        return $this->belongsTo(Division::class, 'division_id', 'id');
    }

    /**
     * Get all of the comments for the Department
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function employees()
    {
        return $this->hasMany(User::class, 'department_id', 'id');
    }

    public function departmentSettings()
    {
        return $this->hasOne(DepartmentSetting::class, 'department_id', 'id');
    }
    public function head()
    {
        return $this->hasOne(User::class, 'id', 'head_id');
    }
}
