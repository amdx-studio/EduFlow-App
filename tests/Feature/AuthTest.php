<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sanctum.stateful' => ['localhost:8000']]);
    }

    /**
     * Request dari SPA (domain stateful Sanctum).
     */
    private function spa(): static
    {
        return $this->withHeaders(['Referer' => 'http://localhost:8000']);
    }

    private function validPayload(array $override = []): array
    {
        return array_merge([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'password' => 'password123',
            'role' => 'student',
        ], $override);
    }

    public function test_tc001_registrasi_valid(): void
    {
        $response = $this->spa()->postJson('/api/register', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('data.email', 'budi@example.com')
            ->assertJsonPath('data.role', 'student')
            ->assertJsonMissingPath('data.password');

        $user = User::where('email', 'budi@example.com')->firstOrFail();
        $this->assertSame('student', $user->role);
        $this->assertNotSame('password123', $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_tc001_guru_terbuka_bila_kode_undangan_kosong(): void
    {
        config(['eduflow.teacher_invite_code' => null]);

        $this->spa()->postJson('/api/register', $this->validPayload(['role' => 'teacher']))
            ->assertCreated()
            ->assertJsonPath('data.role', 'teacher');
    }

    public function test_tc001_guru_wajib_kode_bila_diset(): void
    {
        config(['eduflow.teacher_invite_code' => 'KODE-RAHASIA']);

        $this->spa()->postJson('/api/register', $this->validPayload(['role' => 'teacher']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('teacher_code');

        $this->spa()->postJson('/api/register', $this->validPayload(['role' => 'teacher', 'teacher_code' => 'salah']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('teacher_code');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_tc001_guru_dengan_kode_benar(): void
    {
        config(['eduflow.teacher_invite_code' => 'KODE-RAHASIA']);

        $this->spa()->postJson('/api/register', $this->validPayload(['role' => 'teacher', 'teacher_code' => 'KODE-RAHASIA']))
            ->assertCreated()
            ->assertJsonPath('data.role', 'teacher');
    }

    public function test_tc002_email_duplikat(): void
    {
        User::factory()->create(['email' => 'budi@example.com']);

        $this->spa()->postJson('/api/register', $this->validPayload(['email' => 'Budi@Example.com']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_tc003_input_tidak_valid(): void
    {
        $this->spa()->postJson('/api/register', $this->validPayload([
            'email' => 'bukan-email',
            'password' => '1234567',
            'role' => 'admin',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password', 'role']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_tc004_login_valid(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->spa()->get('/sanctum/csrf-cookie')->assertNoContent();

        $login = $this->spa()->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);

        $sessionName = config('session.cookie');
        $cookie = $login->getCookie($sessionName, false);
        $this->assertNotNull($cookie, 'Cookie sesi tidak dikirim saat login.');

        // Pastikan yang mengenali pengguna adalah sesi, bukan guard yang masih di memori.
        $this->app['auth']->forgetGuards();

        $this->withUnencryptedCookie($sessionName, $cookie->getValue())
            ->spa()
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.role', $user->role);
    }

    public function test_tc005_login_gagal_pesan_generik(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $salahPassword = $this->spa()->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password-salah',
        ])->assertUnprocessable();

        $emailTakAda = $this->spa()->postJson('/api/login', [
            'email' => 'tidak-ada@example.com',
            'password' => 'password123',
        ])->assertUnprocessable();

        $salahPassword->assertJsonPath('errors.email.0', 'Email atau password salah.');
        $emailTakAda->assertJsonPath('errors.email.0', 'Email atau password salah.');
        $this->assertGuest('web');
    }

    public function test_tc006_throttle_login(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        $payload = ['email' => $user->email, 'password' => 'password-salah'];

        for ($i = 1; $i <= 5; $i++) {
            $this->spa()->postJson('/api/login', $payload)->assertUnprocessable();
        }

        $this->spa()->postJson('/api/login', $payload)->assertStatus(429);
    }

    public function test_tc007_siswa_ditolak_di_endpoint_guru(): void
    {
        // Rute sementara: /api/courses baru ada di Phase 4.
        Route::middleware(['api', 'auth:sanctum', 'role:teacher'])
            ->post('/api/__tc007', fn () => response()->noContent());

        $siswa = User::factory()->student()->create();

        $this->actingAs($siswa)->postJson('/api/__tc007')->assertForbidden();
    }

    public function test_tc007_guru_diizinkan_di_endpoint_guru(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'role:teacher'])
            ->post('/api/__tc007', fn () => response()->noContent());

        $guru = User::factory()->teacher()->create();

        $this->actingAs($guru)->postJson('/api/__tc007')->assertNoContent();
    }

    public function test_tc008_tanpa_login(): void
    {
        // /api/courses baru ada di Phase 4; sementara memakai /api/user.
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_logout_mengakhiri_sesi(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->spa()->postJson('/api/logout')->assertNoContent();

        $this->assertGuest('web');
    }
}
