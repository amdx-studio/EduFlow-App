<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    // API-40: dashboard kelas per kuis dasar (?quiz_id opsional). Guru pemilik saja (bind + role).
    public function show(Request $request, Course $course, DashboardService $service): JsonResponse
    {
        $data = $request->validate(['quiz_id' => ['sometimes', 'integer']]);

        return response()->json([
            'data' => $service->build($course, isset($data['quiz_id']) ? (int) $data['quiz_id'] : null),
        ]);
    }
}
