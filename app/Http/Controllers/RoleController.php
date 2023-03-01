<?php

namespace App\Http\Controllers;

use App\Events\PermissionEvent;
use App\Http\Resources\RoleCollection;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use App\Http\Resources\RoleResource;
use App\Models\Module;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $roles = Role::with('users')->withCount('users')->get();

        $userPermission = ['roles_create', 'roles_view', 'roles_edit', 'roles_delete'];
        $permission = checkPermission(auth()->user(), $userPermission);

        return [
            'roles' => RoleCollection::collection($roles),
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
        $request->validate([
            'name' => 'required|unique:roles,name',
        ]);

        try {
            $role = Role::create(['name' => $request->input('name')]);
        } catch (\Throwable $th) {
            return message($th->getMessage(), 400);
        }

        return response()->json([
            "message" => "Role Created Successfully"
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Role $role)
    {
        $role->load('permissions');

        return RoleResource::make($role);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $role = Role::with('users')->withCount('users')->find($id);

        if ($role->name == 'Admin')
            return message("You can't delete Admin role", 401);
        if ($role->users_count > 0)
            return message("You can't delete role with Employee", 401);
        if ($role->delete())
            return message('Role archived successfully');

        return message('Something went wrong', 401);
    }


    public function getPermission()
    {
        $modules = Module::with('permissions')->get();
        return response()->json($modules);
    }


    public function updatePermission(Request $request, Role $role)
    {
        foreach ($request->all() as $value) {
            if ($value['attach']) {
                $role->givePermissionTo($value['permission_id']);
            } else {
                $role->revokePermissionTo($value['permission_id']);
            }
        }

        event(new PermissionEvent($role));

        return response()->json([
            'message' => 'Permission updated Successfully'
        ]);
    }
}
