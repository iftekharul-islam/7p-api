<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Period;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemodataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Designation::create([
            'name' => 'Employee'
        ]);

        $divs = [
            ['name' => 'Finance', 'head_id' => 5],
            ['name' => 'Marketing', 'head_id' => 12],
            ['name' => 'Technology', 'head_id' => 14],
            ['name' => 'Investment', 'head_id' => 27],
            ['name' => 'HRMS', 'head_id' => 45],
        ];
        foreach ($divs as $div) {
            Division::create(['name' => $div['name'], 'head_id' => $div['head_id']]);
        }

        $depts = [
            ['name' => 'Business Finance', 'division_id' => 1, 'head_id' => 12],
            ['name' => 'Finance Operations', 'division_id' => 1, 'head_id' => 16],
            ['name' => 'Financial Institution', 'division_id' => 1, 'head_id' => 23],

            ['name' => 'Digital Marketing', 'division_id' => 2, 'head_id' => 28],
            ['name' => 'Media Marketing', 'division_id' => 2, 'head_id' => 29],
            ['name' => 'Brand Marking', 'division_id' => 2, 'head_id' => 31],
            ['name' => 'Corporate Marketong', 'division_id' => 2, 'head_id' => 33],

            ['name' => 'Development', 'division_id' => 3, 'head_id' => 35],
            ['name' => 'SQA', 'division_id' => 3, 'head_id' => 45],
            ['name' => 'Database', 'division_id' => 3, 'head_id' => 55],
            ['name' => 'IT Operations', 'division_id' => 3, 'head_id' => 56],
            ['name' => 'Networks', 'division_id' => 3, 'head_id' => 58],

            ['name' => 'Cash Investment', 'division_id' => 4, 'head_id' => 59],

            ['name' => 'Employee HRMS', 'division_id' => 5, 'head_id' => 70],
            ['name' => 'Administration', 'division_id' => 5, 'head_id' => 75],

        ];
        foreach ($depts as $dept) {
            Department::create(['name' => $dept['name'], 'division_id' => $dept['division_id'], 'head_id' => $dept['head_id']]);
        }

        $users = [
            'name' => 'Employee',
            'email' => 'test',
            'designation_id' => '2',
            'role' => '2'
        ];
        for ($i = 1; $i <= 100; $i++) {

            $rand = rand(1, User::get()->count());

            $mobile = [3, 4, 6, 7, 8, 9];

            $data = User::create([
                'name' => $users['name'] . $i,
                'employee_id' => 'VX' . 1030 + $i,
                'email' => $users['email'] . $i . '@example.com',
                'phone' => '01' . $mobile[array_rand($mobile)] . rand(10000000, 99999999),
                'password' => Hash::make("123456"),
                'designation_id' => '2',
                'department_id' => rand(1, 15),
                'supervisor_id' => $rand,
            ]);
            $data->roles()->sync(rand(2, 4));
        }
    }
}
