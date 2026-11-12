<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\ForumPost;
use App\Models\ForumThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PostController extends Controller
{
    /** AT-10: balasan per utas dibatasi agar API-45 selalu memuat semuanya. */
    public const MAX_PER_THREAD = 200;

    /** API-48 */
    public function store(StorePostRequest $request, ForumThread $thread): JsonResponse
    {
        if ($thread->course->is_archived) {
            return $this->archived();
        }

        if ($thread->is_locked) {
            return response()->json(['message' => 'Utas terkunci: balasan baru tidak diterima.'], 409);
        }

        if ($thread->posts()->count() >= self::MAX_PER_THREAD) {
            return response()->json(['message' => 'Utas sudah mencapai batas balasan.'], 409);
        }

        $post = $thread->posts()->create([
            'author_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        $post->refresh()->load('author:id,name,role');

        return (new PostResource($post))->response()->setStatusCode(201);
    }

    /** API-49: penulis saja (dicek di UpdatePostRequest). */
    public function update(UpdatePostRequest $request, ForumPost $post): PostResource|JsonResponse
    {
        if ($post->thread->course->is_archived) {
            return $this->archived();
        }

        $post->update($request->validated());

        $post->load('author:id,name,role');

        return new PostResource($post);
    }

    /** API-50: penulis atau guru pemilik. */
    public function destroy(ForumPost $post): JsonResponse
    {
        Gate::authorize('delete', $post);

        if ($post->thread->course->is_archived) {
            return $this->archived();
        }

        $post->delete();

        return response()->json(null, 204);
    }

    private function archived(): JsonResponse
    {
        return response()->json([
            'message' => 'Kelas diarsipkan: forum hanya dapat dibaca.',
        ], 409);
    }
}
