<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HomeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    // API-42: ringkasan beranda per peran. Data hanya dari kelas yang terlihat oleh pengguna.
    public function show(Request $request, HomeService $service): JsonResponse
    {
        return response()->json(['data' => $service->build($request->user())]);
    }
}
