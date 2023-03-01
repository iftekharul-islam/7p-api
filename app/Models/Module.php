<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;

class Module extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description'];


    /**
     * Get all of the permissions for the Module
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function permissions()
    {
        $this->permissionClass = app(PermissionRegistrar::class)->getPermissionClass();

        return $this->hasMany($this->permissionClass);
    }
}
