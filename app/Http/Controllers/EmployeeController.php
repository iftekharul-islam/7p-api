<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\RoleCollection;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Division;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //Authorize the user
        abort_unless(access('employees_access'), 403);


        $employees = User::with(['roles:id,name', 'designation:id,name', 'department.division', 'supervisor:id,name,avatar']);

        //Search the employees
        if ($request->q)
            $employees = $employees->where(function ($employees) use ($request) {
                //Search the data by name
                $employees = $employees->where('name', 'LIKE', '%' . $request->q . '%');
            });

        if ($request->role)
            $employees = $employees->where(function ($employees) use ($request) {
                //Search the data by name
                $employees = $employees->whereHas('roles', fn ($q) => $q->where('id', $request->role));
            });

        if ($request->designation)
            $employees = $employees->where(function ($employees) use ($request) {
                //Search the data by name
                $employees = $employees->whereHas('designation', fn ($q) => $q->where('id', $request->designation));
            });

        if (isset($request->status))
            $employees = $employees->where(function ($employee) use ($request) {
                $employee = $employee->where('status', $request->status);
            });

        if (isset($request->division))
            $employees = $employees->where(function ($employee) use ($request) {
                $employee = $employee->whereHas('department.division', fn ($q) => $q->where('id', $request->division));
            });
        if (isset($request->department))
            $employees = $employees->where(function ($employee) use ($request) {
                $employee = $employee->whereHas('department', fn ($q) => $q->where('id', $request->department));
            });



        //Ordering the collection

        if (isset($request->sort))
            $employees = $employees->where(function ($employees) use ($request) {
                // Order by name field
                if ($request->sortColumn == 'name')
                    $employees = $employees->orderBy('name', $request->sort);

                // Order by name field
                if ($request->sortColumn == 'id') {
                    $employees = $employees->orderBy('id', 'DESC');
                }
            });


        $employees = $employees->paginate($request->get('perPage', 10));

        $employees->getCollection()->transform(function ($item) {
            return [
                'id' => $item->id,
                'avatar' => $item->avatar_url,
                'name' => $item->name,
                'employee_id' => $item->employee_id,
                'email' => $item->email,
                'status' => $item->status,
                'designation' => $item->designation->name ?? '--',
                'role' => $item->roles->first()->name ?? '--',
                'department' => $item->department,
                'supervisor' => $item->supervisor
            ];
        });

        $userPermission = ['employees_create', 'employees_view', 'employees_edit', 'employees_delete'];
        $permission = checkPermission(auth()->user(), $userPermission);

        return [
            'users' => $employees,
            'permission' => $permission
        ];
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //Authorize the user
        // abort_unless(access('employees_create'), 403);

        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|string',
            'designation_id' => 'required|exists:designations,id',
            'department_id' => 'required|exists:departments,id',
            'role' => 'required'
        ]);

        try {
            //Upload the avatar
            if ($request->hasFile('avatar'))
                $avatar = $request->file('avatar')->store('users/avatar');

            //Store the data
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'designation_id' => $request->designation_id,
                'department_id' => $request->department_id,
                'employee_id' => $request->employee_id,
                'supervisor_id' => $request->supervisor_id ?? null,
                'password' => Hash::make($request->password),
                'avatar' => $avatar ?? null
            ]);

            //Assign role
            if ($request->role)
                $user->roles()->sync($request->role);

            //Generate the token for authentication
            $token = $user->createToken('auth_token')->plainTextToken;

            //Assign the designation
            // if ($request->designation_id)
            //     $user->employee()->create($request->all());
        } catch (\Throwable $th) {
            return message($th->getMessage());
        }

        return message("User account created successfully", 200, $user);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\User  $employee
     * @return \Illuminate\Http\Response
     */
    public function show(User $employee)
    {
        //Authorize the user
        // abort_unless(access('employees_show'), 403);
        return EmployeeResource::make($employee);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\User  $employee
     * @return \Illuminate\Http\Response
     */
    public function edit()
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $employee
     * @return \Illuminate\Http\Response
     */

    public function getUser($id)
    {
        $employee = User::with(['designation', 'department.division', 'supervisor'])->find($id);
        return [
            'id' => $employee->id,
            'avatar_url' => $employee->avatar_url,
            'name' => $employee->name,
            'employee_id' => $employee->employee_id,
            'department_id' => $employee->department->id,
            'designation_id' => $employee->designation->id,
            'division_id' => $employee->department->division->id,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'supervisor_id' => $employee->supervisor->id,
            'role' => 'X',
            'status' => $employee->status ? 'Active' : 'Incative',

        ];
    }

    public function update(Request $request, User $employee)
    {
        try {
            //Authorize the user
            abort_unless(access('employees_edit'), 403);

            $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $employee->id,
                'password' => 'nullable|string',
                'avatar' => 'nullable|image|max:1024',
                'designation_id' => 'sometimes|exists:designations,id',
                'role' => 'sometimes|exists:roles,id',
            ]);

            //Collect data in variable
            $data = $request->only('name', 'email', 'avatar', 'designation_id');
            $data['status'] = $request->has('status');

            if ($request->password)
                $data['password'] = Hash::make($request->password);

            //Store logo if the file exists in the request
            if ($request->hasFile('avatar')) {
                $data['avatar'] = $request->file('avatar')->store('users/avatar');

                //Delete the previos logo if exists
                if (Storage::exists($employee->avatar))
                    Storage::delete($employee->avatar);
            }

            //Update employee
            $employee->update($data);
            $request->role && $employee->roles()->sync($request->role);

            return message('Employee updated successfully');
        } catch (\Throwable $th) {
            return message($th->getMessage(), 400);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\User  $employee
     * @return \Illuminate\Http\Response
     */
    public function getSupervisor()
    {
        $supervisors = User::active()->get();
        return response()->json($supervisors, 200);
    }

    public function getRole()
    {
        $roles = Role::all();
        return response()->json($roles, 200);
    }

    public function getDesignations()
    {
        $designations = Designation::all();
        return response()->json($designations, 200);
    }

    public function getDivisions()
    {
        $division = Division::all();
        return response()->json($division, 200);
    }

    public function getDepartments($id = null)
    {
        $division = Division::with('departments')->whereId($id)->first();
        return response()->json($division->departments, 200);
    }

    public function getAllDepartments()
    {
        $departments = Department::all();
        return response()->json($departments, 200);
    }
}
