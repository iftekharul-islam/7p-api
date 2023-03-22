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
            return response()->json(['message' => 'No account found!!!'], 401);

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Password not matched!!!'], 401);
        }

        $token = $user->createToken('Token Name')->plainTextToken;

        $data['user'] = $user;
        $data['accessToken'] = $token;
        return response()->json($data, 200);
    }
}
