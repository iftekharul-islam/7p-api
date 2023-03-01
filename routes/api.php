<?php

use App\Http\Controllers\ActivityController;

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PermissionController;

// Login routes

Route::post('login', [AuthController::class, 'login']);
Route::post('forget-password', [AuthController::class, 'forgetPassword']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('user', fn () => auth()->user());
    Route::apiResource('users', UserController::class);

    // Dashboard routes
    Route::get('dashboard', [DashboardController::class, 'index']);

    // Profile routes
    Route::get('user-profile', [ProfileController::class, 'getProfile']);
    Route::post('password-update', [ProfileController::class, 'changePassword']);
    Route::post('profile-update', [ProfileController::class, 'updateProfile']);
    Route::post('image-profile-update', [ProfileController::class, 'updateProfileImage']);

    // Employees routes
    Route::apiResource('employees', EmployeeController::class);
    Route::get('employees-getuser/{id}', [EmployeeController::class, 'getUser']);
    Route::get('update-employees/{id}', [EmployeeController::class, 'update']);
    Route::get('employees-employees', [EmployeeController::class, 'getSupervisor']);
    Route::get('employees-roles', [EmployeeController::class, 'getRole']);
    Route::get('employees-designations', [EmployeeController::class, 'getDesignations']);
    Route::get('employees-divisions', [EmployeeController::class, 'getDivisions']);
    Route::get('employees-departments/{id}', [EmployeeController::class, 'getDepartments']);
    Route::get('employees-departments', [EmployeeController::class, 'getAllDepartments']);

    // Designation routes
    Route::apiResource('designations', DesignationController::class);
    Route::post('designation-update', [DesignationController::class, 'updateDesignation']);

    // Role Routes
    Route::apiResource('roles', RoleController::class);
    Route::get('get-permission', [RoleController::class, 'getPermission']);
    Route::post('roles/{role}/permission-update', [RoleController::class, 'updatePermission']);

    // Division Route
    Route::apiResource('division', DivisionController::class);
    Route::post('division-update', [DivisionController::class, 'updateDivision']);

    // Department Route
    Route::apiResource('department', DepartmentController::class);
    Route::get('department-divisions', [DepartmentController::class, 'getDivisions']);
    Route::post('department-update', [DepartmentController::class, 'updateDepartment']);

    // Activities Route
    Route::apiResource('activities', ActivityController::class);

    ///Notification
    Route::apiResource('/notification', NotificationController::class);
    Route::get('/notification/read/{id}', [NotificationController::class, 'notificationRead']);

    ///Permissions
    Route::apiResource('/permissions', PermissionController::class);
});
