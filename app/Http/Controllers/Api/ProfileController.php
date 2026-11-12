<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    /** API-53: ubah nama. Email dan peran tidak pernah disentuh. */
    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $user->name = $request->validated('name');
        $user->save();

        return new UserResource($user);
    }

    /**
     * API-54: ganti password. Sesi lain milik pengguna ini dihapus (OI-21),
     * sesi yang sedang dipakai tetap hidup.
     */
    public function password(UpdatePasswordRequest $request): Response
    {
        $user = $request->user();
        $user->password = $request->validated('password');
        $user->save();

        $this->revokeOtherSessions($request, $user->id);

        return response()->noContent();
    }

    private function revokeOtherSessions(Request $request, int $userId): void
    {
        $query = DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $userId);

        if ($request->hasSession()) {
            $query->where('id', '!=', $request->session()->getId());
        }

        $query->delete();
    }
}
