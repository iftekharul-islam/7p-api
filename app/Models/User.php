<?php

namespace App\Models;

use App\Traits\Notifiable;
use App\Traits\LogPreference;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRoles, LogPreference;

    /**
     * The name of the logs to differentiate
     *
     * @var string
     */
    protected $logName = 'users';


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'employee_id',
        'phone',
        'email',
        'password',
        'remember_token',
        'avatar',
        'status',
        'designation_id',
        'department_id',
        'supervisor_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'status' => 'boolean'
    ];

    public $appends = ["avatar_url"];

    public function getAvatarUrlAttribute()
    {
        return image($this->attributes['avatar'], $this->attributes['name']);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Get the details associated with the User
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */

    public function hasPermission($permission)
    {
        return $this->role->permissions->contains('slug', $permission);
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class, 'designation_id', 'id');
    }
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function supervisor()
    {
        return $this->hasOne(User::class, 'id', 'supervisor_id');
    }
    /**
     * Get all of the comments for the Employee
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function special_access()
    {
        return $this->hasMany(SpecialAccess::class, 'employee_id');
    }

    /**
     * Get the user associated with the Employee
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function departmentSetting()
    {
        return $this->hasOne(DepartmentSetting::class, 'department_id', 'department_id');
    }

    /**
     * Get the head associated with the Employee
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function head()
    {
        return $this->hasOne(Department::class, 'head_id', 'id');
    }

    public function window_employee()
    {
        return $this->hasMany(WindowEmployee::class, 'employee_id', 'id');
    }
}
