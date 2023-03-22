<?php

namespace App\Http\Controllers;

use GuzzleHttp\Psr7\Message;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $roles = Role::get();
        return $roles;
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
            'name' => 'required|unique:roles,name'
        ]);
        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'web'
        ]);
        if (!$role) {
            return response()->json([
                'message' => 'Somthing Went Wrong!',
                'status' => 401,
                'data' => []
            ]);
        }
        return response()->json([
            'message' => 'Role created successfully!',
            'status' => 201,
            'data' => []
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $role = Role::with('permissions')->find($id);
        return $role;
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
        $request->validate([
            'name' => 'required|unique:roles,name,except,id'
        ]);
        $role = Role::whereId($id)->update(
            [
                'name' => $request->name,
                'guard_name' => 'api'
            ]
        );
        if (!$role) {
            return response()->json([
                'message' => 'Somthing Went Wrong!',
                'status' => 401,
                'data' => []
            ]);
        }
        return response()->json([
            'message' => 'Role update successfully!',
            'status' => 201,
            'data' => []
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function rolePermission(Request $request)
    {
        $role = Role::find($request->role_id);
        $permission = Permission::find($request->permission_id);
        $role->syncPermissions($permission);
        return true;
    }

    public function roleOption()
    {
        $role = Role::get();
        $role->transform(function ($item) {
            return [
                'value' => $item['id'],
                'label' => $item['name'],
            ];
        });
        return $role;
    }
}
