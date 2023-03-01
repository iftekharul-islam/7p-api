<?php

use App\Models\File;
use App\Models\Setting;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;

if (!function_exists('message')) {
    /**
     * Message response for the API
     *
     * @param string $message Message to return
     * @param int $statusCode Response code
     * @return \Illuminate\Http\JsonResponse
     */
    function message($message = "Operation successful", $statusCode = 200, $data = [])
    {
        return response()->json(['message' => $message, 'data' => $data, 'status' => $statusCode], $statusCode);
    }
}


if (!function_exists('image')) {
    function image($file = null, $name = 'Avatar')
    {
        if ($file)
            $url = asset('uploads/profileImage/' . $file);
        else
            $url = 'https://i2.wp.com/ui-avatars.com/api/' . Str::slug($name) . '/400';
        return $url;
    }
}

if (!function_exists('uploadFile')) {
    /**
     * Upload a file
     *
     * @param mixed $requestFiles
     * @param string $path
     * @param mixed $remarks
     * @return mixed
     */
    function uploadFile($requestFiles, $path = '', $remarks = null)
    {
        $files = [];
        if (is_array($requestFiles))
            foreach ($requestFiles as $key => $file) {
                $ext = $file->getClientOriginalExtension();
                $size = $file->getSize() / 1024; //Convert to KB
                $loc = $file->store($path);
                $nameExt = $file->getClientOriginalName();
                $name = pathinfo($nameExt, PATHINFO_FILENAME);

                $files[$key] = File::create([
                    'file_name' => $name,
                    'file_ext' => $ext,
                    'file_size' => $size,
                    'path' => $loc,
                    'remarks' => $remarks
                ]);
            }

        else
            $files = $requestFiles->store($path);

        return $files;
    }
}

if (!function_exists('createFile')) {
    /**
     * Create a file from the request
     *
     * @param mixed $key
     * @param mixed $file
     * @param mixed $path
     */
    function createFile($key, $file, $path, $remarks)
    {
        $ext = $file->getClientOriginalExtension();
        $size = $file->getSize() / 1024; //Convert to KB
        $loc = $file->store($path);
        $nameExt = $file->getClientOriginalName();
        $name = pathinfo($nameExt, PATHINFO_FILENAME);

        $files[$key] = File::create([
            'file_name' => $name,
            'file_ext' => $ext,
            'file_size' => $size,
            'path' => $loc,
            'remarks' => $remarks
        ]);
    }
}

if (!function_exists('user')) {

    /**
     * Get the authenticated user instance
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    function user()
    {
        return auth()->user();
    }
}

if (!function_exists('access')) {
    /**
     * Check whether the user has access to the endpoint
     *
     * @param string $permission
     * @return bool
     */
    function access($permission)
    {
        $user = user();

        if ($user->hasRole('Admin'))
            return true;

        if ($user->can($permission))
            return true;

        return false;
    }
}

if (!function_exists('getDirtyFields')) {

    function getDirtyFields($model, $fields = [])
    {
        $new = collect($model->getDirty());
        $old = collect($model->getOriginal());

        if (count($fields)) {
            $new = $new->only($fields);
            $old = $old->only($new->keys());
        }

        $old = $old->toArray();
        $new = $new->toArray();

        return [
            'old' => $old,
            'new' => $new,
        ];
    }
}

if (!function_exists('settings')) {
    function settings()
    {
        return Setting::all()->map(fn ($d) => [
            'key' => $d->key,
            'value' => $d->value
        ]);
    }
}


if (!function_exists('setting')) {
    function setting($key)
    {
        return Setting::find($key)->value ?? null;
    }
}

if (!function_exists('getPermission')) {
    function getPermission($user)
    {
        $role = $user->roles()
            ->with('permissions:name')
            ->first();

        return Arr::pluck($role->permissions, 'name');
    }
}

if (!function_exists('checkPermission')) {
    function checkPermission($user, $grantPermission)
    {
        $permissions = getPermission($user);
        foreach ($grantPermission as $item) {
            if ($user->roles[0]->name == "Admin") {
                $permission["is" . ucfirst(explode("_", $item)[1])] = true;
            } else {
                $permission["is" . ucfirst(explode("_", $item)[1])] = in_array($item, $permissions);
            }
        }
        return $permission;
    }
}
