<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\Section;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'admin@example.com',
            'password' => Hash::make('123456')
        ]);

        $role = Role::create(['name' => 'Admin']);
        $user->syncRoles($role);
        Role::create(['name' => 'Employee']);

        Permission::create(['name' => 'user']);
        Permission::create(['name' => 'role']);
        Permission::create(['name' => 'product']);
        Permission::create(['name' => 'vendor']);

        $permission = Permission::get();
        $role->syncPermissions($permission);

        Vendor::create(['name' => 'Vendor A', 'email' => 'a@gmail.com', 'phone_number' => '01688848996']);
        Vendor::create(['name' => 'Vendor B', 'email' => 'b@gmail.com', 'phone_number' => '01366659884']);
        Vendor::create(['name' => 'Vendor C', 'email' => 'c@gmail.com', 'phone_number' => '01658448996']);
        Vendor::create(['name' => 'Vendor D', 'email' => 'd@gmail.com', 'phone_number' => '01688954996']);

        Section::create(['name' => 'Section A']);
        Section::create(['name' => 'Section B']);
        Section::create(['name' => 'Section C']);
        Section::create(['name' => 'Section D']);
    }
}
