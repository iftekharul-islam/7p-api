<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DepartmentSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'hierarchy_level',
        'include_department_head',
        'include_division_head',
        'include_special_access_id',
        'include_hr',
        'include_final_approver',
    ];

    public function specialAccess()
    {
        return $this->hasOne(User::class, 'id', 'include_special_access_id');
    }

    /**
     * Get the user associated with the DepartmentSetting
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function department()
    {
        return $this->hasOne(Department::class, 'id', 'department_id');
    }
}
