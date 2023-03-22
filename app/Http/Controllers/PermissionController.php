<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $permissions = Permission::get();
        return $permissions;
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
            'name' => 'required|unique:permissions,name'
        ]);
        $role = Permission::create([
            'name' => $request->name,
            'guard_name' => 'api'
        ]);
        if (!$role) {
            return response()->json([
                'message' => 'Somthing Went Wrong!',
                'status' => 401,
                'data' => []
            ]);
        }
        return response()->json([
            'message' => 'Permission created successfully!',
            'status' => 201,
            'data' => []
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $permission = Permission::find($id);
        return $permission;
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
        $permission = Permission::whereId($id)->update(
            [
                'name' => $request->name,
                'guard_name' => 'api'
            ]
        );
        if (!$permission) {
            return response()->json([
                'message' => 'Somthing Went Wrong!',
                'status' => 401,
                'data' => []
            ]);
        }
        return response()->json([
            'message' => 'Permission update successfully!',
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

    public function userPermission()
    {
        return User::with('roles.permissions')->find(Auth()->user()->id);
    }
}
