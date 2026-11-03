<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Models\Course;
use Illuminate\Support\Facades\Route;

// Autentikasi (API-02..05). API-01 (csrf-cookie) disediakan Sanctum.
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
});

// --- Phase 4: Courses & Enrollment (API-06...12) ---
Route::bind('course', function ($value) {
    $user = request()->user();
    abort_unless($user, 401);

    return Course::visibleTo($user)->findOrFail($value);
});

Route::middleware('auth:sanctum')->prefix('courses')->group(function () {
    Route::get('/', [CourseController::class, 'index']);
    Route::post('/', [CourseController::class, 'store'])->middleware('role:teacher');
    Route::post('join', [EnrollmentController::class, 'join'])->middleware('role:student');
    Route::get('{course}', [CourseController::class, 'show']);
    Route::put('{course}', [CourseController::class, 'update'])->middleware('role:teacher');
    Route::post('{course}/archive', [CourseController::class, 'archive'])->middleware('role:teacher');
    Route::get('{course}/students', [EnrollmentController::class, 'students'])->middleware('role:teacher');
});
