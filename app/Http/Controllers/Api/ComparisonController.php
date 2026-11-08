<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\User;
use App\Services\ComparisonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComparisonController extends Controller
{
    // API-39: siswa melihat miliknya; guru wajib mengirim ?student_id= (anggota kelasnya).
    public function show(Request $request, Quiz $quiz, ComparisonService $service): JsonResponse
    {
        abort_unless($quiz->kind === 'reassessment', 404);

        $user = $request->user();

        if ($user->role === 'teacher') {
            $data = $request->validate(['student_id' => ['required', 'integer']]);
            $studentId = (int) $data['student_id'];

            abort_unless($quiz->course->students()->whereKey($studentId)->exists(), 404);

            $student = User::findOrFail($studentId);
        } else {
            $student = $user;
        }

        return response()->json(['data' => $service->compare($student, $quiz)]);
    }
}
