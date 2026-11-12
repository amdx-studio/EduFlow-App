<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsAttemptData;
use Tests\Concerns\BuildsForumData;
use Tests\TestCase;

/**
 * P10.1: audit otorisasi lintas pengguna dan kelas (NFR-001), serta input yang tidak wajar.
 */
class AccessHardeningTest extends TestCase
{
    use BuildsAttemptData;
    use BuildsForumData;
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $w = [];

    private function world(): void
    {
        $owner = $this->teacher();
        $other = $this->teacher();
        $member = $this->student();
        $outsider = $this->student();
        $course = $this->course($owner);
        $this->enroll($course, $member);
        $concept = $this->concept($course);
        $quiz = $this->quiz($course);
        $q1 = $this->question($quiz, $concept, 1);
        $q2 = $this->question($quiz, $concept, 2);
        $lesson = $this->remedialLesson($course, $concept, 'Remedial', 1);
        $attempt = $this->submitViaService($member, $quiz, [[$q1, false], [$q2, false]]);
        $reassessment = $this->quiz($course, [
            'title' => 'Ulang',
            'kind' => 'reassessment',
            'base_quiz_id' => $quiz->id,
        ]);

        $thread = $this->makeThread($course, $owner);
        $post = $this->makePost($thread, $owner);

        $this->w = [
            'owner' => $owner,
            'other' => $other,
            'member' => $member,
            'outsider' => $outsider,
            'course' => $course,
            'concept' => $concept,
            'quiz' => $quiz,
            'question' => $q1,
            'lesson' => $lesson,
            'attempt' => $attempt,
            'reassessment' => $reassessment,
            'thread' => $thread,
            'post' => $post,
        ];
    }

    /** Satu permintaan di dalam savepoint, supaya kegagalan query tidak merusak transaksi test. */
    private function probe(string $method, string $url, array $payload = []): int
    {
        DB::beginTransaction();

        try {
            return $this->json($method, $url, $payload)->getStatusCode();
        } finally {
            DB::rollBack();
        }
    }

    /**
     * Metode, url, isi, dan aktor yang harus ditolak.
     *
     * @return array<int, array{0: string, 1: string, 2: array<string, mixed>, 3: array<int, string>}>
     */
    private function routes(): array
    {
        $c = $this->w['course']->id;
        $k = $this->w['concept']->id;
        $l = $this->w['lesson']->id;
        $q = $this->w['quiz']->id;
        $r = $this->w['reassessment']->id;
        $q1 = $this->w['question']->id;
        $a = $this->w['attempt']->id;
        $m = $this->w['member']->id;
        $t = $this->w['thread']->id;
        $p = $this->w['post']->id;

        $teacherOnly = ['member', 'outsider', 'other'];
        $studentOnly = ['owner', 'other', 'outsider'];
        $shared = ['outsider', 'other'];

        $question = [
            'body' => 'B',
            'concept_id' => $k,
            'options' => [['body' => 'a', 'is_correct' => true], ['body' => 'b']],
        ];

        return [
            ['POST', '/api/courses', ['title' => 'X'], ['member', 'outsider']],
            ['POST', '/api/courses/join', ['join_code' => $this->w['course']->join_code], ['owner', 'other']],
            ['GET', "/api/courses/{$c}", [], $shared],
            ['PUT', "/api/courses/{$c}", ['title' => 'X'], $teacherOnly],
            ['POST', "/api/courses/{$c}/archive", [], $teacherOnly],
            ['GET', "/api/courses/{$c}/students", [], $teacherOnly],
            ['GET', "/api/courses/{$c}/students/{$m}", [], $teacherOnly],
            ['GET', "/api/courses/{$c}/dashboard", [], $teacherOnly],
            ['GET', "/api/courses/{$c}/remediation", [], $studentOnly],
            ['GET', "/api/courses/{$c}/concepts", [], $shared],
            ['POST', "/api/courses/{$c}/concepts", ['name' => 'Baru'], $teacherOnly],
            ['PUT', "/api/concepts/{$k}", ['name' => 'Ubah'], $teacherOnly],
            ['DELETE', "/api/concepts/{$k}", [], $teacherOnly],
            ['GET', "/api/courses/{$c}/lessons", [], $shared],
            ['POST', "/api/courses/{$c}/lessons", ['title' => 'T', 'content' => 'C', 'kind' => 'regular'], $teacherOnly],
            ['GET', "/api/lessons/{$l}", [], $shared],
            ['PUT', "/api/lessons/{$l}", ['title' => 'Ubah'], $teacherOnly],
            ['DELETE', "/api/lessons/{$l}", [], $teacherOnly],
            ['POST', "/api/lessons/{$l}/complete", [], $studentOnly],
            ['GET', "/api/courses/{$c}/quizzes", [], $shared],
            ['POST', "/api/courses/{$c}/quizzes", ['title' => 'T', 'kind' => 'assessment'], $teacherOnly],
            ['GET', "/api/quizzes/{$q}", [], $shared],
            ['PUT', "/api/quizzes/{$q}", ['title' => 'Ubah'], $teacherOnly],
            ['DELETE', "/api/quizzes/{$q}", [], $teacherOnly],
            ['POST', "/api/quizzes/{$q}/publish", [], $teacherOnly],
            ['POST', "/api/quizzes/{$q}/questions", $question, $teacherOnly],
            ['PUT', "/api/questions/{$q1}", $question, $teacherOnly],
            ['DELETE', "/api/questions/{$q1}", [], $teacherOnly],
            ['POST', "/api/quizzes/{$q}/attempts", [], $studentOnly],
            ['GET', "/api/quizzes/{$r}/comparison?student_id={$m}", [], $shared],
            ['GET', "/api/attempts/{$a}", [], $shared],
            ['PUT', "/api/attempts/{$a}/answers", ['question_id' => $q1, 'option_id' => null], $studentOnly],
            ['POST', "/api/attempts/{$a}/submit", [], $studentOnly],
            ['GET', "/api/attempts/{$a}/result", [], $shared],
            ['POST', "/api/attempts/{$a}/remediation", ['concept_id' => $k, 'lesson_id' => $l], $teacherOnly],
            ['GET', "/api/courses/{$c}/threads", [], $shared],
            ['POST', "/api/courses/{$c}/threads", ['title' => 'T', 'body' => 'B'], $shared],
            ['GET', "/api/threads/{$t}", [], $shared],
            ['PUT', "/api/threads/{$t}", ['title' => 'Ubah'], $teacherOnly],
            ['DELETE', "/api/threads/{$t}", [], $teacherOnly],
            ['POST', "/api/threads/{$t}/posts", ['body' => 'B'], $shared],
            ['PUT', "/api/posts/{$p}", ['body' => 'Ubah'], $teacherOnly],
            ['DELETE', "/api/posts/{$p}", [], $teacherOnly],
        ];
    }

    public function test_guest_gets_401_on_every_protected_route(): void
    {
        $this->world();
        $failures = [];

        $all = $this->routes();
        $all[] = ['GET', '/api/user', [], []];
        $all[] = ['POST', '/api/logout', [], []];

        foreach ($all as [$method, $url, $payload]) {
            $status = $this->probe($method, $url, $payload);

            if ($status !== 401) {
                $failures[] = "guest {$method} {$url} => {$status}";
            }
        }

        $this->assertSame([], $failures);
    }

    public function test_denied_actors_get_403_or_404_on_every_route(): void
    {
        $this->world();
        $failures = [];

        foreach (['member', 'outsider', 'other', 'owner'] as $actor) {
            $this->signIn($this->w[$actor]);

            foreach ($this->routes() as [$method, $url, $payload, $denied]) {
                if (! in_array($actor, $denied, true)) {
                    continue;
                }

                $status = $this->probe($method, $url, $payload);

                if (! in_array($status, [403, 404], true)) {
                    $failures[] = "{$actor} {$method} {$url} => {$status}";
                }
            }
        }

        $this->assertSame([], $failures);
    }

    public function test_malformed_ids_in_urls_return_client_errors_not_5xx(): void
    {
        $this->world();
        $c = $this->w['course']->id;

        $templates = [
            ['GET', '/api/courses/{id}'],
            ['PUT', '/api/courses/{id}'],
            ['POST', '/api/courses/{id}/archive'],
            ['GET', '/api/courses/{id}/students'],
            ['GET', "/api/courses/{$c}/students/{id}"],
            ['GET', '/api/courses/{id}/dashboard'],
            ['GET', '/api/courses/{id}/remediation'],
            ['GET', '/api/courses/{id}/concepts'],
            ['POST', '/api/courses/{id}/concepts'],
            ['PUT', '/api/concepts/{id}'],
            ['DELETE', '/api/concepts/{id}'],
            ['GET', '/api/courses/{id}/lessons'],
            ['POST', '/api/courses/{id}/lessons'],
            ['GET', '/api/lessons/{id}'],
            ['PUT', '/api/lessons/{id}'],
            ['DELETE', '/api/lessons/{id}'],
            ['POST', '/api/lessons/{id}/complete'],
            ['GET', '/api/courses/{id}/quizzes'],
            ['POST', '/api/courses/{id}/quizzes'],
            ['GET', '/api/quizzes/{id}'],
            ['PUT', '/api/quizzes/{id}'],
            ['DELETE', '/api/quizzes/{id}'],
            ['POST', '/api/quizzes/{id}/publish'],
            ['POST', '/api/quizzes/{id}/questions'],
            ['POST', '/api/quizzes/{id}/attempts'],
            ['GET', '/api/quizzes/{id}/comparison'],
            ['PUT', '/api/questions/{id}'],
            ['DELETE', '/api/questions/{id}'],
            ['GET', '/api/attempts/{id}'],
            ['PUT', '/api/attempts/{id}/answers'],
            ['POST', '/api/attempts/{id}/submit'],
            ['GET', '/api/attempts/{id}/result'],
            ['POST', '/api/attempts/{id}/remediation'],
            ['GET', '/api/courses/{id}/threads'],
            ['POST', '/api/courses/{id}/threads'],
            ['GET', '/api/threads/{id}'],
            ['PUT', '/api/threads/{id}'],
            ['DELETE', '/api/threads/{id}'],
            ['POST', '/api/threads/{id}/posts'],
            ['PUT', '/api/posts/{id}'],
            ['DELETE', '/api/posts/{id}'],
        ];

        $failures = [];

        foreach (['owner', 'member'] as $actor) {
            $this->signIn($this->w[$actor]);

            foreach (['abc', '99999999999999999999'] as $bad) {
                foreach ($templates as [$method, $template]) {
                    $url = str_replace('{id}', $bad, $template);
                    $status = $this->probe($method, $url);

                    if (! in_array($status, [403, 404, 405, 422], true)) {
                        $failures[] = "{$actor} {$method} {$url} => {$status}";
                    }
                }
            }
        }

        $this->assertSame([], $failures);
    }

    public function test_malformed_input_values_return_422_not_5xx(): void
    {
        $this->world();
        $c = $this->w['course']->id;
        $k = $this->w['concept']->id;
        $l = $this->w['lesson']->id;
        $q = $this->w['quiz']->id;
        $r = $this->w['reassessment']->id;
        $q1 = $this->w['question']->id;
        $a = $this->w['attempt']->id;
        $t = $this->w['thread']->id;
        $p = $this->w['post']->id;

        $lesson = ['title' => 'T', 'content' => 'C', 'kind' => 'regular'];
        $remedial = ['title' => 'T', 'content' => 'C', 'kind' => 'remedial'];

        $cases = [
            ['member', 'PUT', "/api/attempts/{$a}/answers", ['question_id' => 'abc', 'option_id' => 'abc']],
            ['member', 'PUT', "/api/attempts/{$a}/answers", ['question_id' => $q1, 'option_id' => 'abc']],
            ['member', 'PUT', "/api/attempts/{$a}/answers", ['question_id' => ['x'], 'option_id' => null]],
            ['owner', 'POST', "/api/courses/{$c}/lessons", $lesson + ['concept_ids' => ['abc']]],
            ['owner', 'POST', "/api/courses/{$c}/lessons", $lesson + ['concept_ids' => ['99999999999999999999']]],
            ['owner', 'POST', "/api/courses/{$c}/lessons", $lesson + ['concept_ids' => 'abc']],
            ['owner', 'POST', "/api/courses/{$c}/lessons", $remedial + ['concept_ids' => 'abc']],
            ['owner', 'PUT', "/api/lessons/{$l}", ['concept_ids' => 'abc']],
            ['owner', 'POST', "/api/quizzes/{$q}/questions", ['body' => 'B', 'concept_id' => 'abc', 'options' => [['body' => 'a', 'is_correct' => true], ['body' => 'b']]]],
            ['owner', 'POST', "/api/courses/{$c}/quizzes", ['title' => 'T', 'kind' => 'reassessment', 'base_quiz_id' => 'abc']],
            ['owner', 'POST', "/api/attempts/{$a}/remediation", ['concept_id' => 'abc', 'lesson_id' => 'abc']],
            ['owner', 'POST', "/api/attempts/{$a}/remediation", ['concept_id' => $k, 'lesson_id' => 'abc']],
            ['owner', 'POST', "/api/attempts/{$a}/remediation", ['concept_id' => 'abc', 'lesson_id' => $l]],
            ['owner', 'GET', "/api/quizzes/{$r}/comparison?student_id=abc", []],
            ['owner', 'GET', "/api/courses/{$c}/dashboard?quiz_id=abc", []],
            ['owner', 'PUT', "/api/concepts/{$k}", ['name' => str_repeat('a', 300)]],
            ['owner', 'POST', '/api/courses', ['title' => ['x']]],
            ['outsider', 'POST', '/api/courses/join', ['join_code' => ['x']]],
            ['owner', 'GET', "/api/courses/{$c}/threads?before_id=abc", []],
            ['owner', 'GET', "/api/courses/{$c}/threads?before_id=99999999999999999999", []],
            ['owner', 'POST', "/api/courses/{$c}/threads", ['title' => ['x'], 'body' => 'B']],
            ['owner', 'POST', "/api/courses/{$c}/threads", ['title' => 'T', 'body' => ['x']]],
            ['owner', 'POST', "/api/threads/{$t}/posts", ['body' => ['x']]],
            ['owner', 'PUT', "/api/threads/{$t}", ['title' => str_repeat('a', 151)]],
            ['owner', 'PUT', "/api/threads/{$t}", ['is_pinned' => 'abc']],
            ['owner', 'PUT', "/api/posts/{$p}", ['body' => ['x']]],
        ];

        $failures = [];

        foreach ($cases as [$actor, $method, $url, $payload]) {
            $this->signIn($this->w[$actor]);
            $status = $this->probe($method, $url, $payload);

            if ($status !== 422) {
                $failures[] = "{$actor} {$method} {$url} ".json_encode($payload)." => {$status}";
            }
        }

        $this->assertSame([], $failures);
    }
}
