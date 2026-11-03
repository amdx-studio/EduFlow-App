<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $courses = Course::visibleTo($request->user())
            ->with('teacher:id,name')
            ->withCount('students')
            ->orderByDesc('created_at')
            ->get();

        return CourseResource::collection($courses);
    }

    public function store(StoreCourseRequest $request)
    {
        $course = Course::create([
            ...$request->validated(),
            'teacher_id' => $request->user()->id,
            'join_code' => Course::generateJoinCode(),
        ])->refresh();

        return (new CourseResource($course))->response()->setStatusCode(201);
    }

    public function show(Request $request, Course $course)
    {
        $course->load('teacher:id,name');

        return new CourseResource($course);
    }

    public function update(UpdateCourseRequest $request, Course $course)
    {
        Gate::authorize('update', $course);
        $course->update($request->validated());

        return new CourseResource($course->refresh());
    }

    public function archive(Request $request, Course $course)
    {
        Gate::authorize('archive', $course);
        $course->update(['is_archived' => true]);

        return new CourseResource($course->refresh());
    }
}
