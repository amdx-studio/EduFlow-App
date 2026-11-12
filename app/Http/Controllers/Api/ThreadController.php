<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreThreadRequest;
use App\Http\Requests\UpdateThreadRequest;
use App\Http\Resources\ThreadResource;
use App\Models\Course;
use App\Models\ForumThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ThreadController extends Controller
{
    private const PAGE_SIZE = 20;

    /** API-43: utas tersemat + satu halaman kursor (KT-26). */
    public function index(Request $request, Course $course): JsonResponse
    {
        $request->validate([
            'before_id' => ['nullable', 'integer', 'min:1', 'max:999999999999999999'],
        ]);

        $listing = fn () => $course->forumThreads()
            ->select(['id', 'course_id', 'author_id', 'title', 'is_pinned', 'is_locked', 'created_at', 'updated_at'])
            ->with('author:id,name,role')
            ->withCount('posts')
            ->orderByDesc('id');

        $pinned = $listing()->where('is_pinned', true)->get();

        // Ambil satu baris lebih untuk mengetahui apakah masih ada halaman berikutnya.
        $rows = $listing()
            ->where('is_pinned', false)
            ->when($request->filled('before_id'), fn ($q) => $q->where('id', '<', (int) $request->input('before_id')))
            ->limit(self::PAGE_SIZE + 1)
            ->get();

        $present = fn ($threads) => $threads
            ->map(fn (ForumThread $thread) => (new ThreadResource($thread))->resolve($request))
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'pinned' => $present($pinned),
                'threads' => $present($rows->take(self::PAGE_SIZE)),
                'has_more' => $rows->count() > self::PAGE_SIZE,
            ],
        ]);
    }

    /** API-44 */
    public function store(StoreThreadRequest $request, Course $course): JsonResponse
    {
        if ($course->is_archived) {
            return $this->archived();
        }

        $thread = $course->forumThreads()->create(
            array_merge($request->validated(), ['author_id' => $request->user()->id])
        );

        $thread->refresh()->load('author:id,name,role')->loadCount('posts');

        return (new ThreadResource($thread))->response()->setStatusCode(201);
    }

    /** API-45: utas + balasan berurutan, maksimum PostController::MAX_PER_THREAD. */
    public function show(ForumThread $thread): ThreadResource
    {
        $thread->loadCount('posts')->load([
            'author:id,name,role',
            'posts' => fn ($q) => $q->with('author:id,name,role')->orderBy('id')->limit(PostController::MAX_PER_THREAD),
        ]);

        return new ThreadResource($thread);
    }

    /** API-46: otorisasi per kolom ada di UpdateThreadRequest. */
    public function update(UpdateThreadRequest $request, ForumThread $thread): ThreadResource|JsonResponse
    {
        if ($thread->course->is_archived) {
            return $this->archived();
        }

        $thread->update($request->validated());

        $thread->load('author:id,name,role')->loadCount('posts');

        return new ThreadResource($thread);
    }

    /** API-47 */
    public function destroy(Request $request, ForumThread $thread): JsonResponse
    {
        Gate::authorize('delete', $thread);

        if ($thread->course->is_archived) {
            return $this->archived();
        }

        // BR-020 / OI-20: penulis non-guru tidak boleh menghapus utas yang sudah dibalas orang lain.
        $isModerator = $request->user()->id === $thread->course->teacher_id;

        if (! $isModerator && $thread->posts()->where('author_id', '!=', $thread->author_id)->exists()) {
            return response()->json([
                'message' => 'Utas tidak dapat dihapus karena sudah dibalas orang lain.',
            ], 409);
        }

        $thread->delete();

        return response()->json(null, 204);
    }

    private function archived(): JsonResponse
    {
        return response()->json([
            'message' => 'Kelas diarsipkan: forum hanya dapat dibaca.',
        ], 409);
    }
}
