<?php

namespace Tests\Concerns;

use App\Models\Course;
use App\Models\ForumPost;
use App\Models\ForumThread;
use App\Models\User;

/**
 * Pembantu data forum. Nama diawali "make" agar tidak bentrok dengan post() milik TestCase.
 */
trait BuildsForumData
{
    private function makeThread(Course $course, User $author, array $extra = []): ForumThread
    {
        return ForumThread::create(array_merge([
            'course_id' => $course->id,
            'author_id' => $author->id,
            'title' => 'Judul utas',
            'body' => 'Isi utas.',
        ], $extra))->refresh();
    }

    private function makePost(ForumThread $thread, User $author, string $body = 'Balasan.'): ForumPost
    {
        return ForumPost::create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => $body,
        ])->refresh();
    }
}
