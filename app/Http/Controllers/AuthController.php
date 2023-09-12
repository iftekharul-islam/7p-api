<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $user = User::where('email', $request->email)->first();
        if (!$user)
            return response()->json(['errors' => ['email' => 'No account found!!!']], 422);

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['errors' => ['password' => 'Password not matched!!!']], 422);
        }

        $token = $user->createToken('Token Name')->plainTextToken;

        // $permission = User::with('roles.permissions')->find($user->id);
        // $permissions = [];
        // foreach ($permission->roles[0]->permissions as $key => $value) {
        //     array_push($permissions, $value->name);
        // };

        $data['user'] = $user;
        $data['accessToken'] = $token;
        // $data['permissions'] = $permissions;
        $data['permissions'] = ['user'];
        return response()->json($data, 200);
    }
}
