<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConceptController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\LessonController;
use App\Models\Course;
use App\Models\LearningConcept;
use App\Models\Lesson;
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

// --- Phase 5: Concepts & Lessons ---
Route::bind('concept', function ($value) {
    $user = request()->user();
    abort_unless($user, 401);

    return LearningConcept::visibleTo($user)->findOrFail($value);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('courses/{course}/concepts', [ConceptController::class, 'index']);
    Route::post('courses/{course}/concepts', [ConceptController::class, 'store'])->middleware('role:teacher');
    Route::put('concepts/{concept}', [ConceptController::class, 'update'])->middleware('role:teacher');
    Route::delete('concepts/{concept}', [ConceptController::class, 'destroy'])->middleware('role:teacher');
});

Route::bind('lesson', function ($value) {
    $user = request()->user();
    abort_unless($user, 401);

    return Lesson::visibleTo($user)->findOrFail($value);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('courses/{course}/lessons', [LessonController::class, 'index']);
    Route::post('courses/{course}/lessons', [LessonController::class, 'store'])->middleware('role:teacher');
    Route::get('lessons/{lesson}', [LessonController::class, 'show']);
    Route::put('lessons/{lesson}', [LessonController::class, 'update'])->middleware('role:teacher');
    Route::delete('lessons/{lesson}', [LessonController::class, 'destroy'])->middleware('role:teacher');
});

Route::post('lessons/{lesson}/complete', [LessonController::class, 'complete'])
    ->middleware(['auth:sanctum', 'role:student']);
