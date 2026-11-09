<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use App\Services\StudentDetailService;
use Illuminate\Http\JsonResponse;

class StudentController extends Controller
{
    // API-41: detail siswa untuk guru pemilik. Siswa harus anggota kelas (selain itu 404).
    public function show(Course $course, string $student, StudentDetailService $service): JsonResponse
    {
        abort_unless(ctype_digit($student), 404);
        abort_unless($course->students()->whereKey((int) $student)->exists(), 404);

        return response()->json([
            'data' => $service->build($course, User::findOrFail((int) $student)),
        ]);
    }
}
