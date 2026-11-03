<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\JoinCourseRequest;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class EnrollmentController extends Controller
{
    public function join(JoinCourseRequest $request)
    {
        $course = Course::where('join_code', $request->validated('join_code'))
            ->where('is_archived', false)
            ->first();

        if (! $course) {
            throw ValidationException::withMessages([
                'join_code' => ['Kode gabung tidak valid atau kelas sudah diarsipkan.'],
            ]);
        }

        $course->students()->syncWithoutDetaching([
            $request->user()->id => ['enrolled_at' => now()],
        ]);

        return new CourseResource($course->load('teacher:id,name'));
    }

    public function students(Request $request, Course $course)
    {
        $students = $course->students()
            ->orderBy('users.name')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'enrolled_at' => Carbon::parse($u->pivot->enrolled_at)->utc()->toIso8601String(),
            ]);

        return response()->json(['data' => $students]);
    }
}
