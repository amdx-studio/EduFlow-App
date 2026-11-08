<?php

use App\Http\Controllers\Api\AttemptController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConceptController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\RemediationController;
use App\Models\Course;
use App\Models\LearningConcept;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
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

// --- Phase 6: Quiz Authoring ---
Route::bind('quiz', function ($value) {
    $user = request()->user();
    abort_unless($user, 401);

    return Quiz::visibleTo($user)->findOrFail($value);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('courses/{course}/quizzes', [QuizController::class, 'index']);
    Route::post('courses/{course}/quizzes', [QuizController::class, 'store'])->middleware('role:teacher');
    Route::get('quizzes/{quiz}', [QuizController::class, 'show']);
    Route::put('quizzes/{quiz}', [QuizController::class, 'update'])->middleware('role:teacher');
    Route::delete('quizzes/{quiz}', [QuizController::class, 'destroy'])->middleware('role:teacher');
});

Route::bind('question', function ($value) {
    $user = request()->user();
    abort_unless($user, 401);

    return Question::whereHas('quiz', fn ($q) => $q->visibleTo($user))->findOrFail($value);
});

Route::middleware(['auth:sanctum', 'role:teacher'])->group(function () {
    Route::post('quizzes/{quiz}/questions', [QuestionController::class, 'store']);
    Route::put('questions/{question}', [QuestionController::class, 'update']);
    Route::delete('questions/{question}', [QuestionController::class, 'destroy']);
});

Route::post('quizzes/{quiz}/publish', [QuizController::class, 'publish'])
    ->middleware(['auth:sanctum', 'role:teacher']);

// --- Phase 7: Attempts, Scoring, Mastery (API-32..36) ---
Route::bind('attempt', function ($value) {
    $user = request()->user();
    abort_unless($user, 401);

    return QuizAttempt::visibleTo($user)->findOrFail($value);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('quizzes/{quiz}/attempts', [AttemptController::class, 'store'])->middleware('role:student');
    Route::get('attempts/{attempt}', [AttemptController::class, 'show']);
    Route::put('attempts/{attempt}/answers', [AttemptController::class, 'answer'])->middleware('role:student');
    Route::post('attempts/{attempt}/submit', [AttemptController::class, 'submit'])->middleware('role:student');
    Route::get('attempts/{attempt}/result', [AttemptController::class, 'result']);
});

// --- Phase 8: Remediation (API-37) ---
Route::get('courses/{course}/remediation', [RemediationController::class, 'index'])
    ->middleware(['auth:sanctum', 'role:student']);
