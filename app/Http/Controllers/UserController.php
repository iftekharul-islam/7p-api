<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $users = User::with('roles:name')->paginate($request->get('perPage', 10));
        return $users;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|unique:users,email',
            'password' => 'required|string|min:6'
        ]);
        $data = $request->only([
            'name',
            'email',
            'password',
            'vendor_id',
            'zip_code',
            'state',
            'remote',
            'section_id',
            'station_id',
            'permit_manufactures'
        ]);
        $data['password'] = Hash::make($request->password);
        $data['permit_manufactures'] = json_encode($request->permit_manufactures);

        $user = User::create($data);
        $user->syncRoles(Role::find($request->role));

        return response()->json([
            'message' => 'User created successfully!',
            'status' => 201,
            'data' => []
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return User::with(['roles.permissions'])->find($id);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'message' => 'User not found!',
                'status' => 203,
                'data' => []
            ], 203);
        }
        $request->validate([
            'name' => 'required',
            'email' => 'required|unique:users,email,' . $id
        ]);
        $data = $request->only([
            'name',
            'email',
            'password',
            'vendor_id',
            'zip_code',
            'state',
            'remote',
            'section_id',
            'station_id',
            'permit_manufactures'
        ]);

        $user->update($data);
        return response()->json([
            'message' => 'User update Successfully!',
            'status' => 201,
            'data' => []
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function userRole(Request $request)
    {
        $user = User::find($request->user_id);
        $role = Role::find($request->role_id);
        $user->syncRoles($role);
        return true;
    }
}
