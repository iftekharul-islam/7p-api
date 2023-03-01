<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Table::truncate();
        $roles = [
            ['guard_name' => 'sanctum', 'name' => 'Admin'],
            ['guard_name' => 'sanctum', 'name' => 'Manager'],
            ['guard_name' => 'sanctum', 'name' => 'Dept_Head'],
            ['guard_name' => 'sanctum', 'name' => 'KPI'],
        ];

        $modules = [
            'Employees' => [
                ['name' => 'employees_access', 'guard_name' => 'sanctum'],
                ['name' => 'employees_create', 'guard_name' => 'sanctum'],
                ['name' => 'employees_view', 'guard_name' => 'sanctum'],
                ['name' => 'employees_edit', 'guard_name' => 'sanctum'],
                ['name' => 'employees_delete', 'guard_name' => 'sanctum'],
            ],
            'Designations' => [
                ['name' => 'designations_access', 'guard_name' => 'sanctum'],
                ['name' => 'designations_create', 'guard_name' => 'sanctum'],
                ['name' => 'designations_view', 'guard_name' => 'sanctum'],
                ['name' => 'designations_edit', 'guard_name' => 'sanctum'],
                ['name' => 'designations_delete', 'guard_name' => 'sanctum'],
            ],
            'Roles' => [
                ['name' => 'roles_access', 'guard_name' => 'sanctum'],
                ['name' => 'roles_create', 'guard_name' => 'sanctum'],
                ['name' => 'roles_view', 'guard_name' => 'sanctum'],
                ['name' => 'roles_edit', 'guard_name' => 'sanctum'],
                ['name' => 'roles_delete', 'guard_name' => 'sanctum'],
            ],
            'Divisions' => [
                ['name' => 'divisions_access', 'guard_name' => 'sanctum'],
                ['name' => 'divisions_create', 'guard_name' => 'sanctum'],
                ['name' => 'divisions_view', 'guard_name' => 'sanctum'],
                ['name' => 'divisions_edit', 'guard_name' => 'sanctum'],
                ['name' => 'divisions_delete', 'guard_name' => 'sanctum'],
            ],
            'Departments' => [
                ['name' => 'departments_access', 'guard_name' => 'sanctum'],
                ['name' => 'departments_create', 'guard_name' => 'sanctum'],
                ['name' => 'departments_view', 'guard_name' => 'sanctum'],
                ['name' => 'departments_edit', 'guard_name' => 'sanctum'],
                ['name' => 'departments_delete', 'guard_name' => 'sanctum'],
            ],
        ];

        foreach ($modules as $key => $permissions) {
            $module = Module::create(['name' => $key]);
            foreach ($permissions as $permission)
                Permission::create(['name' => $permission['name'], 'guard_name' => $permission['guard_name'], 'module_id' => $module->id]);
        }

        $permissions = [];

        Role::insert($roles);
    }
}
