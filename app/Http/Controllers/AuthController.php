<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use App\Mail\ResetPassword;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        if (!Auth::attempt($request->only('email', 'password')))
            return message('Invalid Login Details', 400);

        $user = auth()->user();
        $token = $user->createToken('auth_token')->plainTextToken;
        $user = UserResource::make($user);

        $data['userData'] = $user;
        $data['accessToken'] = $token;
        $data['refreshToken'] = $token;

        return response()->json($data);
    }

    public function forgetPassword(Request $request)
    {
        $user = User::where('email', $request->email)->first();
        if ($user) {
            $rem_token = Str::random(16);
            Mail::to($request->email)->send(new ResetPassword($rem_token, $user));
            $user->update(['remember_token' => $rem_token]);
            return message('Please check your email!', 201);
        }
        return message('User not found!', 401);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|min:8',
        ]);

        $user = User::where('remember_token', $request->token)->first();
        if ($user) {
            $user->update([
                'password' => Hash::make($request->password),
                'remember_token' => null
            ]);
            return message('Password successfully changed!', 201);
        }
        return message('Invalid Link!', 401);
    }

    public function Shanto(){
        return "shanto";
    }
}
