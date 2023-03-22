<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPermissionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::apiResource('users', UserController::class);
    Route::post('user-role', [UserController::class, 'userRole']);

    Route::apiResource('roles', RoleController::class);
    Route::get('role-options', [RoleController::class, 'roleOption']);
    Route::post('roles-update/{id}', [RoleController::class, 'update']);
    Route::post('role-permission', [RoleController::class, 'rolePermission']);

    Route::apiResource('permissions', PermissionController::class);
    Route::post('permissions-update/{id}', [PermissionController::class, 'update']);
    Route::get('user-permission', [PermissionController::class, 'userPermission']);
});
