<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAttemptData;
use Tests\TestCase;

/**
 * Pembatas laju untuk register (per IP) dan join kelas (per pengguna).
 */
class ThrottleTest extends TestCase
{
    use BuildsAttemptData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sanctum.stateful' => ['localhost:8000']]);
    }

    private function spa(): static
    {
        return $this->withHeaders(['Referer' => 'http://localhost:8000']);
    }

    /** Email tidak valid: 422 dari validasi, tetapi tetap dihitung oleh throttle. */
    private function badRegister(): array
    {
        return ['name' => 'Budi', 'email' => 'bukan-email', 'password' => 'password123', 'role' => 'student'];
    }

    public function test_register_is_limited_to_ten_per_minute_per_ip(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->spa()->postJson('/api/register', $this->badRegister())->assertUnprocessable();
        }

        $this->spa()->postJson('/api/register', $this->badRegister())->assertStatus(429);

        // IP lain tidak ikut terblokir.
        $this->spa()
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->postJson('/api/register', $this->badRegister())
            ->assertUnprocessable();
    }

    public function test_join_is_limited_to_ten_per_minute_even_with_a_valid_code(): void
    {
        $course = $this->course($this->teacher());
        $student = $this->student();

        $this->signIn($student);

        for ($i = 1; $i <= 10; $i++) {
            $this->postJson('/api/courses/join', ['join_code' => 'ZZZZZZZZ'])->assertUnprocessable();
        }

        $this->postJson('/api/courses/join', ['join_code' => 'ZZZZZZZZ'])->assertStatus(429);

        // Setelah batas, kode yang benar pun ditolak sementara dan siswa belum terdaftar.
        $this->postJson('/api/courses/join', ['join_code' => $course->join_code])->assertStatus(429);
        $this->assertDatabaseCount('course_enrollments', 0);
    }

    public function test_join_limit_is_per_user_not_shared(): void
    {
        $course = $this->course($this->teacher());
        $first = $this->student();
        $second = $this->student();

        $this->signIn($first);
        for ($i = 1; $i <= 11; $i++) {
            $this->postJson('/api/courses/join', ['join_code' => 'ZZZZZZZZ']);
        }

        $this->signIn($second);
        $this->postJson('/api/courses/join', ['join_code' => $course->join_code])->assertOk();
        $this->assertDatabaseCount('course_enrollments', 1);
    }
}
