<?php

namespace App\Http\Controllers;

use App\Models\User;
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
            'name' => $request->name
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
        ], 201);
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
                'name' => $request->name
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

    public function delete($id)
    {
        $role = Role::find($id);
        $users = User::role($role)->get();

        if ($role->name == 'Admin')
            return response()->json(['message' => "You can't delete Admin role", 'status' => 203], 203);
        if ($users)
            return response()->json(['message' => "You can't delete role with Employee", 'status' => 203], 203);
        if ($role->delete())
            return response()->json(['message' => "Role archived successfully", 'status' => 201], 201);

        // return message('Something went wrong', 401);
    }

    public function rolePermission(Request $request, $id)
    {
        $role = Role::find($id);

        if ($role->id == 1) {
            return response()->json([
                'message' => 'Role Permission update successfully!',
                'status' => 201,
                'data' => []
            ]);
        }
        foreach ($request->all() as $value) {
            if ($value['attach']) {
                info($role);
                info("A");
                info($value['permission_id']);
                $role->givePermissionTo($value['permission_id']);
            } else {
                info("B");
                $role->revokePermissionTo($value['permission_id']);
            }
        }

        return response()->json([
            'message' => 'Role Permission update successfully!',
            'status' => 201,
            'data' => []
        ]);
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
