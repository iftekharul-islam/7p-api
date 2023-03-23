<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProductController;
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
    Route::get('users', [UserController::class, 'index']);
    Route::post('users', [UserController::class, 'store']);
    Route::get('users/{id}', [UserController::class, 'show']);
    Route::post('user-role', [UserController::class, 'userRole']);
    Route::get('user-permission', [PermissionController::class, 'userPermission']);

    Route::get('roles', [RoleController::class, 'index']);
    Route::post('roles', [RoleController::class, 'store']);
    Route::get('roles/{id}', [RoleController::class, 'show']);
    Route::post('roles-update/{id}', [RoleController::class, 'update']);
    Route::post('role-permission', [RoleController::class, 'rolePermission']);

    Route::get('permissions', [PermissionController::class, 'index']);
    Route::post('permissions', [PermissionController::class, 'store']);
    Route::get('permissions/{id}', [PermissionController::class, 'show']);
    Route::post('permissions-update/{id}', [PermissionController::class, 'update']);

    Route::get('products', [ProductController::class, 'index']);
    Route::post('products', [ProductController::class, 'store']);
    Route::post('add-stock-products', [ProductController::class, 'addStock']);
    Route::post('products-update/{id}', [ProductController::class, 'update']);

    Route::get('role-options', [RoleController::class, 'roleOption']);
    Route::get('stock-options', [ProductController::class, 'stockOption']);
    Route::get('vendor-options', [ProductController::class, 'vendorOption']);
});
