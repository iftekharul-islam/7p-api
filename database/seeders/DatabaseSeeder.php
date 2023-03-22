<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\Sections;
use App\Models\User;
use App\Models\Vendors;
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
        User::create([
            'name' => 'Test User',
            'email' => 'admin@example.com',
            'password' => Hash::make('123456')
        ]);

        Role::create(['name' => 'Admin']);
        Role::create(['name' => 'Employee']);

        Permission::create(['name' => 'Permission-1']);
        Permission::create(['name' => 'Permission-2']);
        Permission::create(['name' => 'Permission-3']);
        Permission::create(['name' => 'Permission-4']);

        Vendors::create(['name' => 'Vendor A']);
        Vendors::create(['name' => 'Vendor B']);
        Vendors::create(['name' => 'Vendor C']);
        Vendors::create(['name' => 'Vendor D']);

        Sections::create(['name' => 'Sections A']);
        Sections::create(['name' => 'Sections B']);
        Sections::create(['name' => 'Sections C']);
        Sections::create(['name' => 'Sections D']);
    }
}
