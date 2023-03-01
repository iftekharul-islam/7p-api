<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function getProfile()
    {
        $user_id = Auth::user()->id;
        $user = User::with('supervisor', 'designation', 'department.division')->find($user_id);
        return [
            'id' => $user->id,
            'avatar_url' => $user->avatar_url,
            'name' => $user->name,
            'employee_id' => $user->employee_id,
            'email' => $user->email,
            'phone' => $user->phone,
            'supervisor' => $user->supervisor->name ?? '--',
            'division' => $user->department->division->name ?? '--',
            'department' => $user->department->name ?? '--',
            'designation' => $user->designation->name ?? '--',
            'status' => $user->status,
        ];
    }
    public function changePassword(Request $request)
    {
        $user = User::find(user()->id);
        if (!Hash::check($request->currentPassword, $user->password)) {
            return message('Current password does not match!', 400);
        }
        if (strcmp($request->newPassword, $request->retypeNewPassword)) {
            return message('Password and confirm password does not match!', 400);
        }
        $user->update(['password' => Hash::make($request->newPassword)]);
        return message('Password changed successfully', 201);
    }


    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'avatar' => 'nullable|image|max:1024',
            'password' => 'nullable',
        ]);

        $user = user();
        $data = $request->only('name', 'email', 'avatar');
        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('users/avatar');

            //Delete the previos logo if exists
            if (Storage::exists($user->avatar))
                Storage::delete($user->avatar);
        }

        $user->update($data);

        return message('User updated successfully', 200);
    }

    public function updateProfileImage(Request $request)
    {
        $user = User::find(user()->id);
        if ($request->hasFile('avatar')) {
            $path = 'uploads/profileImage/';
            $_avatar = $request->file('avatar');

            if (!file_exists($path))
                mkdir($path, 0777, true);

            if (file_exists($path . $user->avatar))
                @unlink($path . $user->avatar);

            $avatar = trim(sprintf('%s', $user->id)) . '.' . $_avatar->getClientOriginalExtension();
            $_avatar->move($path, $avatar);
            $user->avatar = $avatar;
            $user->save();

            $user = auth()->user();
            $user = UserResource::make($user);

            $user['avatar'] = $avatar;
            return [
                'userData' => $user
            ];
        } else {
            return message('Profile Image update failed', 400);
        }
    }
}
