<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\PostController;
use App\Models\Course;
use App\Models\ForumPost;
use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAttemptData;
use Tests\Concerns\BuildsForumData;
use Tests\TestCase;

/**
 * P12.3: TC-046 sampai TC-052 (BR-019 sampai BR-022, US-023 sampai US-025).
 */
class ForumTest extends TestCase
{
    use BuildsAttemptData;
    use BuildsForumData;
    use RefreshDatabase;

    /**
     * @return array{owner: User, other: User, member: User, peer: User, outsider: User, course: Course}
     */
    private function world(): array
    {
        $owner = $this->teacher();
        $course = $this->course($owner);
        $member = $this->student();
        $peer = $this->student();

        $this->enroll($course, $member);
        $this->enroll($course, $peer);

        return [
            'owner' => $owner,
            'other' => $this->teacher(),
            'member' => $member,
            'peer' => $peer,
            'outsider' => $this->student(),
            'course' => $course,
        ];
    }

    // TC-046
    public function test_tc046_member_creates_thread_and_it_appears_in_list(): void
    {
        $w = $this->world();
        $url = "/api/courses/{$w['course']->id}/threads";

        $this->signIn($w['member']);

        $id = $this->postJson($url, ['title' => 'Bagaimana pecahan?', 'body' => 'Tolong **jelaskan**.'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Bagaimana pecahan?')
            ->assertJsonPath('data.body', 'Tolong **jelaskan**.')
            ->assertJsonPath('data.author.id', $w['member']->id)
            ->assertJsonPath('data.author.role', 'student')
            ->assertJsonPath('data.is_pinned', false)
            ->assertJsonPath('data.is_locked', false)
            ->assertJsonPath('data.posts_count', 0)
            ->json('data.id');

        $this->assertDatabaseHas('forum_threads', [
            'id' => $id,
            'course_id' => $w['course']->id,
            'author_id' => $w['member']->id,
        ]);

        $this->getJson($url)
            ->assertOk()
            ->assertJsonCount(1, 'data.threads')
            ->assertJsonCount(0, 'data.pinned')
            ->assertJsonPath('data.threads.0.id', $id)
            ->assertJsonPath('data.has_more', false)
            ->assertJsonMissingPath('data.threads.0.body');

        // Guru pemilik juga anggota forum.
        $this->signIn($w['owner']);
        $this->postJson($url, ['title' => 'Pengumuman', 'body' => 'Halo.'])
            ->assertCreated()
            ->assertJsonPath('data.author.role', 'teacher');
    }

    public function test_tc046_non_member_gets_404_and_guest_gets_401(): void
    {
        $w = $this->world();
        $url = "/api/courses/{$w['course']->id}/threads";
        $thread = $this->makeThread($w['course'], $w['member']);

        foreach (['outsider', 'other'] as $actor) {
            $this->signIn($w[$actor]);

            $this->getJson($url)->assertNotFound();
            $this->postJson($url, ['title' => 'X', 'body' => 'Y'])->assertNotFound();
            $this->getJson("/api/threads/{$thread->id}")->assertNotFound();
            $this->postJson("/api/threads/{$thread->id}/posts", ['body' => 'Y'])->assertNotFound();
        }

        $this->assertDatabaseCount('forum_threads', 1);
        $this->assertDatabaseCount('forum_posts', 0);

        $this->app['auth']->forgetGuards();
        $this->getJson($url)->assertUnauthorized();
        $this->postJson($url, ['title' => 'X', 'body' => 'Y'])->assertUnauthorized();
    }

    public function test_tc046_title_and_body_are_validated(): void
    {
        $w = $this->world();
        $url = "/api/courses/{$w['course']->id}/threads";
        $this->signIn($w['member']);

        $invalid = [
            [[], ['title', 'body']],
            [['title' => 'T'], ['body']],
            [['body' => 'B'], ['title']],
            [['title' => '   ', 'body' => 'B'], ['title']],
            [['title' => str_repeat('a', 151), 'body' => 'B'], ['title']],
            [['title' => 'T', 'body' => str_repeat('a', 10001)], ['body']],
            [['title' => ['x'], 'body' => 'B'], ['title']],
            [['title' => 'T', 'body' => ['x']], ['body']],
        ];

        foreach ($invalid as [$payload, $fields]) {
            $this->postJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors($fields);
        }

        $this->assertDatabaseCount('forum_threads', 0);

        // Batas tepat diterima: judul 150 dan isi 10.000 karakter.
        $this->postJson($url, ['title' => str_repeat('a', 150), 'body' => str_repeat('b', 10000)])->assertCreated();
    }

    // TC-047
    public function test_tc047_replies_are_listed_in_order(): void
    {
        $w = $this->world();
        $thread = $this->makeThread($w['course'], $w['member']);

        $this->signIn($w['member']);
        $this->postJson("/api/threads/{$thread->id}/posts", ['body' => 'Satu'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Satu')
            ->assertJsonPath('data.author.id', $w['member']->id);

        $this->signIn($w['owner']);
        $this->postJson("/api/threads/{$thread->id}/posts", ['body' => 'Dua'])
            ->assertCreated()
            ->assertJsonPath('data.author.role', 'teacher');

        $this->signIn($w['peer']);
        $this->postJson("/api/threads/{$thread->id}/posts", ['body' => 'Tiga'])->assertCreated();

        $this->getJson("/api/threads/{$thread->id}")
            ->assertOk()
            ->assertJsonPath('data.body', 'Isi utas.')
            ->assertJsonPath('data.posts_count', 3)
            ->assertJsonCount(3, 'data.posts')
            ->assertJsonPath('data.posts.0.body', 'Satu')
            ->assertJsonPath('data.posts.1.body', 'Dua')
            ->assertJsonPath('data.posts.2.body', 'Tiga');
    }

    public function test_tc047_locked_thread_rejects_replies_until_unlocked(): void
    {
        $w = $this->world();
        $thread = $this->makeThread($w['course'], $w['member']);
        $posts = "/api/threads/{$thread->id}/posts";

        $this->signIn($w['owner']);
        $this->putJson("/api/threads/{$thread->id}", ['is_locked' => true])
            ->assertOk()
            ->assertJsonPath('data.is_locked', true);

        $this->postJson($posts, ['body' => 'Guru'])->assertStatus(409);

        $this->signIn($w['member']);
        $this->postJson($posts, ['body' => 'Siswa'])->assertStatus(409);
        $this->assertDatabaseCount('forum_posts', 0);

        $this->signIn($w['owner']);
        $this->putJson("/api/threads/{$thread->id}", ['is_locked' => false])
            ->assertOk()
            ->assertJsonPath('data.is_locked', false);

        $this->signIn($w['member']);
        $this->postJson($posts, ['body' => 'Siswa'])->assertCreated();
        $this->assertDatabaseCount('forum_posts', 1);
    }

    public function test_tc047_post_body_is_validated_and_author_can_edit_own_reply(): void
    {
        $w = $this->world();
        $thread = $this->makeThread($w['course'], $w['owner']);
        $post = $this->makePost($thread, $w['member'], 'Awal');

        $this->signIn($w['member']);

        foreach ([[], ['body' => ''], ['body' => ['x']], ['body' => str_repeat('a', 10001)]] as $payload) {
            $this->postJson("/api/threads/{$thread->id}/posts", $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('body');
            $this->putJson("/api/posts/{$post->id}", $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('body');
        }

        $this->putJson("/api/posts/{$post->id}", ['body' => 'Diubah'])
            ->assertOk()
            ->assertJsonPath('data.body', 'Diubah');

        $this->assertDatabaseHas('forum_posts', ['id' => $post->id, 'body' => 'Diubah']);
    }

    public function test_reply_limit_per_thread_returns_409_and_show_returns_all_posts(): void
    {
        $w = $this->world();
        $thread = $this->makeThread($w['course'], $w['owner']);

        $rows = [];
        for ($i = 1; $i <= PostController::MAX_PER_THREAD; $i++) {
            $rows[] = [
                'thread_id' => $thread->id,
                'author_id' => $w['peer']->id,
                'body' => "B{$i}",
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        ForumPost::insert($rows);

        $this->signIn($w['member']);
        $this->postJson("/api/threads/{$thread->id}/posts", ['body' => 'Lebih'])->assertStatus(409);

        $this->getJson("/api/threads/{$thread->id}")
            ->assertOk()
            ->assertJsonPath('data.posts_count', PostController::MAX_PER_THREAD)
            ->assertJsonCount(PostController::MAX_PER_THREAD, 'data.posts');
    }

    // TC-048
    public function test_tc048_only_owner_teacher_moderates_and_only_author_edits_content(): void
    {
        $w = $this->world();
        $thread = $this->makeThread($w['course'], $w['member']);
        $url = "/api/threads/{$thread->id}";

        $this->signIn($w['owner']);
        $this->putJson($url, ['is_pinned' => true, 'is_locked' => true])
            ->assertOk()
            ->assertJsonPath('data.is_pinned', true)
            ->assertJsonPath('data.is_locked', true);

        // Guru pemilik tidak dapat mengubah judul atau isi milik orang lain.
        $this->putJson($url, ['title' => 'Disunting guru'])->assertForbidden();

        $this->signIn($w['member']);
        $this->putJson($url, ['is_pinned' => false])->assertForbidden();
        $this->putJson($url, ['is_locked' => false])->assertForbidden();
        // Tidak dapat menyelipkan sematan lewat permintaan yang juga memuat judul.
        $this->putJson($url, ['title' => 'Baru', 'is_pinned' => false])->assertForbidden();
        $this->assertDatabaseHas('forum_threads', ['id' => $thread->id, 'title' => 'Judul utas', 'is_pinned' => true, 'is_locked' => true]);

        $this->putJson($url, ['title' => 'Judul baru', 'body' => 'Isi baru'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Judul baru')
            ->assertJsonPath('data.body', 'Isi baru');

        $this->signIn($w['peer']);
        $this->putJson($url, ['title' => 'Curang'])->assertForbidden();

        foreach ([['title' => ''], ['title' => str_repeat('a', 151)], ['is_pinned' => 'abc']] as $payload) {
            $this->signIn($payload === ['is_pinned' => 'abc'] ? $w['owner'] : $w['member']);
            $this->putJson($url, $payload)->assertUnprocessable();
        }
    }

    public function test_tc048_thread_delete_rules(): void
    {
        $w = $this->world();

        // Moderator menghapus utas siapa pun; balasan ikut terhapus (hapus keras).
        $withReplies = $this->makeThread($w['course'], $w['member']);
        $this->makePost($withReplies, $w['peer']);
        $this->makePost($withReplies, $w['member']);

        $this->signIn($w['peer']);
        $this->deleteJson("/api/threads/{$withReplies->id}")->assertForbidden();

        // Penulis non-guru: ditolak bila ada balasan dari orang lain (BR-020, OI-20).
        $this->signIn($w['member']);
        $this->deleteJson("/api/threads/{$withReplies->id}")->assertStatus(409);
        $this->assertDatabaseHas('forum_threads', ['id' => $withReplies->id]);

        $this->signIn($w['owner']);
        $this->deleteJson("/api/threads/{$withReplies->id}")->assertNoContent();
        $this->assertDatabaseMissing('forum_threads', ['id' => $withReplies->id]);
        $this->assertDatabaseCount('forum_posts', 0);

        // Balasan guru juga dihitung sebagai "orang lain".
        $byTeacher = $this->makeThread($w['course'], $w['member']);
        $this->makePost($byTeacher, $w['owner']);
        $this->signIn($w['member']);
        $this->deleteJson("/api/threads/{$byTeacher->id}")->assertStatus(409);

        // Tanpa balasan, atau hanya balasan penulis sendiri: boleh.
        $alone = $this->makeThread($w['course'], $w['member']);
        $this->deleteJson("/api/threads/{$alone->id}")->assertNoContent();

        $ownReplies = $this->makeThread($w['course'], $w['member']);
        $this->makePost($ownReplies, $w['member']);
        $this->deleteJson("/api/threads/{$ownReplies->id}")->assertNoContent();

        $this->assertDatabaseMissing('forum_threads', ['id' => $alone->id]);
        $this->assertDatabaseMissing('forum_threads', ['id' => $ownReplies->id]);
        $this->assertDatabaseHas('forum_threads', ['id' => $byTeacher->id]);
    }

    public function test_tc048_post_delete_and_edit_rules(): void
    {
        $w = $this->world();
        $thread = $this->makeThread($w['course'], $w['owner']);
        $post = $this->makePost($thread, $w['member']);
        $url = "/api/posts/{$post->id}";

        $this->signIn($w['peer']);
        $this->putJson($url, ['body' => 'Curang'])->assertForbidden();
        $this->deleteJson($url)->assertForbidden();

        // Guru pemilik tidak menyunting balasan orang lain, tetapi dapat menghapusnya.
        $this->signIn($w['owner']);
        $this->putJson($url, ['body' => 'Disunting guru'])->assertForbidden();
        $this->assertDatabaseHas('forum_posts', ['id' => $post->id, 'body' => 'Balasan.']);
        $this->deleteJson($url)->assertNoContent();
        $this->assertDatabaseMissing('forum_posts', ['id' => $post->id]);

        $own = $this->makePost($thread, $w['member']);
        $this->signIn($w['member']);
        $this->deleteJson("/api/posts/{$own->id}")->assertNoContent();

        // Utas tetap ada setelah balasannya dihapus.
        $this->assertDatabaseHas('forum_threads', ['id' => $thread->id]);
    }

    public function test_tc048_other_teacher_and_outsider_get_404_and_nothing_changes(): void
    {
        $w = $this->world();
        $thread = $this->makeThread($w['course'], $w['member']);
        $post = $this->makePost($thread, $w['member']);

        foreach (['other', 'outsider'] as $actor) {
            $this->signIn($w[$actor]);

            $this->getJson("/api/threads/{$thread->id}")->assertNotFound();
            $this->putJson("/api/threads/{$thread->id}", ['is_pinned' => true])->assertNotFound();
            $this->putJson("/api/threads/{$thread->id}", ['title' => 'X'])->assertNotFound();
            $this->deleteJson("/api/threads/{$thread->id}")->assertNotFound();
            $this->putJson("/api/posts/{$post->id}", ['body' => 'X'])->assertNotFound();
            $this->deleteJson("/api/posts/{$post->id}")->assertNotFound();
        }

        $this->assertDatabaseHas('forum_threads', ['id' => $thread->id, 'title' => 'Judul utas', 'is_pinned' => false]);
        $this->assertDatabaseHas('forum_posts', ['id' => $post->id, 'body' => 'Balasan.']);
    }

    // TC-049 (bagian server; penyaringan tampilan oleh MarkdownView diuji manual di P12.5)
    public function test_tc049_server_stores_and_returns_dangerous_text_verbatim_as_json(): void
    {
        $w = $this->world();
        $this->signIn($w['member']);

        $title = '<script>alert(1)</script>';
        $body = "<img src=x onerror=alert(1)>\n\n[klik](javascript:alert(1))";

        $id = $this->postJson("/api/courses/{$w['course']->id}/threads", ['title' => $title, 'body' => $body])
            ->assertCreated()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('data.title', $title)
            ->assertJsonPath('data.body', $body)
            ->json('data.id');

        $this->postJson("/api/threads/{$id}/posts", ['body' => $body])
            ->assertCreated()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('data.body', $body);

        $this->getJson("/api/threads/{$id}")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('data.title', $title)
            ->assertJsonPath('data.posts.0.body', $body);
    }

    // TC-050
    public function test_tc050_archived_course_is_read_only_for_everyone(): void
    {
        $w = $this->world();
        $thread = $this->makeThread($w['course'], $w['member']);
        $post = $this->makePost($thread, $w['member']);
        $w['course']->update(['is_archived' => true]);

        $threads = "/api/courses/{$w['course']->id}/threads";
        $t = "/api/threads/{$thread->id}";
        $p = "/api/posts/{$post->id}";

        foreach (['member', 'owner'] as $actor) {
            $this->signIn($w[$actor]);

            $this->getJson($threads)->assertOk()->assertJsonCount(1, 'data.threads');
            $this->getJson($t)->assertOk()->assertJsonCount(1, 'data.posts');

            $this->postJson($threads, ['title' => 'Baru', 'body' => 'Isi'])->assertStatus(409);
            $this->postJson("{$t}/posts", ['body' => 'Balas'])->assertStatus(409);
        }

        $this->signIn($w['member']);
        $this->putJson($t, ['title' => 'Ubah'])->assertStatus(409);
        $this->putJson($p, ['body' => 'Ubah'])->assertStatus(409);
        $this->deleteJson($p)->assertStatus(409);
        $this->deleteJson($t)->assertStatus(409);

        $this->signIn($w['owner']);
        $this->putJson($t, ['is_pinned' => true])->assertStatus(409);
        $this->putJson($t, ['is_locked' => true])->assertStatus(409);
        $this->deleteJson($p)->assertStatus(409);
        $this->deleteJson($t)->assertStatus(409);

        $this->assertDatabaseCount('forum_threads', 1);
        $this->assertDatabaseCount('forum_posts', 1);
        $this->assertDatabaseHas('forum_threads', ['id' => $thread->id, 'title' => 'Judul utas', 'is_pinned' => false, 'is_locked' => false]);
    }

    // TC-051
    public function test_tc051_cursor_pagination_gives_20_then_5_without_duplicates(): void
    {
        $w = $this->world();
        $url = "/api/courses/{$w['course']->id}/threads";

        // Dua utas tersemat (satu paling lama, satu paling baru) dan 25 utas biasa.
        $this->makeThread($w['course'], $w['owner'], ['title' => 'Sematan lama', 'is_pinned' => true]);
        for ($i = 1; $i <= 25; $i++) {
            $this->makeThread($w['course'], $w['member'], ['title' => "Utas {$i}"]);
        }
        $this->makeThread($w['course'], $w['owner'], ['title' => 'Sematan baru', 'is_pinned' => true]);

        $this->signIn($w['member']);

        $first = $this->getJson($url)
            ->assertOk()
            ->assertJsonCount(20, 'data.threads')
            ->assertJsonCount(2, 'data.pinned')
            ->assertJsonPath('data.has_more', true)
            ->json('data');

        $firstIds = array_column($first['threads'], 'id');
        $sorted = $firstIds;
        rsort($sorted);
        $this->assertSame($sorted, $firstIds, 'id harus menurun');
        $this->assertSame('Utas 25', $first['threads'][0]['title']);

        $cursor = end($firstIds);

        $second = $this->getJson("{$url}?before_id={$cursor}")
            ->assertOk()
            ->assertJsonCount(5, 'data.threads')
            ->assertJsonCount(2, 'data.pinned')
            ->assertJsonPath('data.has_more', false)
            ->json('data');

        $secondIds = array_column($second['threads'], 'id');
        $all = array_merge($firstIds, $secondIds);

        $this->assertSame(25, count(array_unique($all)), 'tanpa duplikat');

        $expected = ForumThread::where('is_pinned', false)->pluck('id')->sort()->values()->all();
        $actual = collect($all)->sort()->values()->all();
        $this->assertSame($expected, $actual);

        $pinnedIds = array_column($first['pinned'], 'id');
        $this->assertSame([], array_values(array_intersect($pinnedIds, $all)), 'sematan tidak muncul di threads');
        $this->assertSame(['Sematan baru', 'Sematan lama'], array_column($first['pinned'], 'title'));
    }

    public function test_tc051_exactly_20_threads_has_no_more_and_bad_cursor_is_422(): void
    {
        $w = $this->world();
        $url = "/api/courses/{$w['course']->id}/threads";

        for ($i = 1; $i <= 20; $i++) {
            $this->makeThread($w['course'], $w['member'], ['title' => "Utas {$i}"]);
        }

        $this->signIn($w['member']);

        $this->getJson($url)->assertOk()->assertJsonCount(20, 'data.threads')->assertJsonPath('data.has_more', false);

        foreach (['abc', '0', '-1', '99999999999999999999', '1.5'] as $bad) {
            $this->getJson("{$url}?before_id={$bad}")->assertUnprocessable()->assertJsonValidationErrors('before_id');
        }

        // Utas kelas lain tidak ikut.
        $this->makeThread($this->course($w['owner'], ['title' => 'Kelas lain']), $w['owner']);
        $this->getJson($url)->assertJsonCount(20, 'data.threads');
    }

    public function test_thread_list_reports_reply_counts(): void
    {
        $w = $this->world();
        $thread = $this->makeThread($w['course'], $w['member']);
        $this->makePost($thread, $w['peer']);
        $this->makePost($thread, $w['owner']);

        $this->signIn($w['member']);
        $this->getJson("/api/courses/{$w['course']->id}/threads")
            ->assertOk()
            ->assertJsonPath('data.threads.0.posts_count', 2)
            ->assertJsonPath('data.threads.0.author.name', $w['member']->name);
    }

    // TC-052
    public function test_tc052_21st_forum_post_within_a_minute_gets_429(): void
    {
        $w = $this->world();
        $url = "/api/courses/{$w['course']->id}/threads";
        $thread = $this->makeThread($w['course'], $w['owner']);

        $this->signIn($w['member']);

        // Utas dan balasan memakai satu batas (BR-022): 10 + 10 = 20 berhasil.
        for ($i = 1; $i <= 10; $i++) {
            $this->postJson($url, ['title' => "T{$i}", 'body' => 'B'])->assertCreated();
            $this->postJson("/api/threads/{$thread->id}/posts", ['body' => "R{$i}"])->assertCreated();
        }

        $this->postJson($url, ['title' => 'T21', 'body' => 'B'])->assertStatus(429);
        $this->postJson("/api/threads/{$thread->id}/posts", ['body' => 'R21'])->assertStatus(429);

        // Membaca tidak dibatasi, dan pengguna lain tidak terpengaruh.
        $this->getJson($url)->assertOk();

        $this->signIn($w['peer']);
        $this->postJson("/api/threads/{$thread->id}/posts", ['body' => 'Peer'])->assertCreated();
    }
}
