<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsAttemptData;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use BuildsAttemptData;
    use RefreshDatabase;

    private function userWithPassword(string $role = 'student'): User
    {
        $user = $role === 'teacher' ? $this->teacher() : $this->student();
        $user->forceFill(['password' => 'password-lama-1'])->save();

        return $user->refresh();
    }

    private function sessionRow(int $userId): string
    {
        $id = Str::random(40);
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'perangkat-lain',
            'payload' => base64_encode('x'),
            'last_activity' => time(),
        ]);

        return $id;
    }

    // TC-057: ubah profil
    public function test_tc057_name_changes_and_email_and_role_are_ignored(): void
    {
        $user = $this->userWithPassword();
        $email = $user->email;
        $this->signIn($user);

        $this->putJson('/api/profile', [
            'name' => 'Nama Baru',
            'email' => 'penyusup@example.com',
            'role' => 'teacher',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nama Baru')
            ->assertJsonPath('data.email', $email)
            ->assertJsonPath('data.role', 'student');

        $user->refresh();
        $this->assertSame('Nama Baru', $user->name);
        $this->assertSame($email, $user->email);
        $this->assertSame('student', $user->role);

        $this->getJson('/api/user')->assertOk()->assertJsonPath('data.name', 'Nama Baru');
    }

    public function test_tc057_name_is_validated_and_guest_is_rejected(): void
    {
        $user = $this->userWithPassword();
        $name = $user->name;

        $this->app['auth']->forgetGuards();
        $this->putJson('/api/profile', ['name' => 'X'])->assertUnauthorized();

        $this->signIn($user);
        $this->putJson('/api/profile', ['name' => ''])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->putJson('/api/profile', [])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->putJson('/api/profile', ['name' => str_repeat('a', 101)])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->putJson('/api/profile', ['name' => ['x']])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->putJson('/api/profile', ['name' => str_repeat('a', 100)])->assertOk();

        $this->assertNotSame($name, $user->refresh()->name);
    }

    // TC-056: ganti password
    public function test_tc056_validation_rules_and_nothing_changes_on_failure(): void
    {
        $user = $this->userWithPassword();
        $this->signIn($user);

        // Password saat ini salah.
        $this->putJson('/api/profile/password', [
            'current_password' => 'salah-salah',
            'password' => 'password-baru-2',
            'password_confirmation' => 'password-baru-2',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        // Wajib diisi.
        $this->putJson('/api/profile/password', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password', 'password']);

        // Baru sama dengan lama.
        $this->putJson('/api/profile/password', [
            'current_password' => 'password-lama-1',
            'password' => 'password-lama-1',
            'password_confirmation' => 'password-lama-1',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        // Terlalu pendek, dan konfirmasi tidak cocok.
        $this->putJson('/api/profile/password', [
            'current_password' => 'password-lama-1',
            'password' => 'pendek',
            'password_confirmation' => 'pendek',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->putJson('/api/profile/password', [
            'current_password' => 'password-lama-1',
            'password' => 'password-baru-2',
            'password_confirmation' => 'beda-sekali-3',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertTrue(Hash::check('password-lama-1', $user->refresh()->password));
    }

    public function test_tc056_success_changes_password_and_login_follows(): void
    {
        $user = $this->userWithPassword();
        $this->signIn($user);

        $this->putJson('/api/profile/password', [
            'current_password' => 'password-lama-1',
            'password' => 'password-baru-2',
            'password_confirmation' => 'password-baru-2',
        ])->assertNoContent();

        $user->refresh();
        $this->assertTrue(Hash::check('password-baru-2', $user->password));
        $this->assertFalse(Hash::check('password-lama-1', $user->password));

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password-lama-1'])
            ->assertUnprocessable();

        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Referer' => 'http://localhost:8000'])
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'password-baru-2'])
            ->assertOk();
    }

    // OI-21: sesi perangkat lain tidak berlaku lagi
    public function test_tc056_other_sessions_are_revoked_on_success_only(): void
    {
        $user = $this->userWithPassword();
        $other = $this->userWithPassword();

        $mine1 = $this->sessionRow($user->id);
        $mine2 = $this->sessionRow($user->id);
        $theirs = $this->sessionRow($other->id);

        $this->signIn($user);

        // Gagal: sesi tetap utuh.
        $this->putJson('/api/profile/password', [
            'current_password' => 'salah-salah',
            'password' => 'password-baru-2',
            'password_confirmation' => 'password-baru-2',
        ])->assertUnprocessable();

        $this->assertSame(2, DB::table('sessions')->where('user_id', $user->id)->count());

        // Berhasil: semua sesi lain pengguna ini hilang, milik orang lain tetap.
        $this->putJson('/api/profile/password', [
            'current_password' => 'password-lama-1',
            'password' => 'password-baru-2',
            'password_confirmation' => 'password-baru-2',
        ])->assertNoContent();

        $this->assertFalse(DB::table('sessions')->where('id', $mine1)->exists());
        $this->assertFalse(DB::table('sessions')->where('id', $mine2)->exists());
        $this->assertTrue(DB::table('sessions')->where('id', $theirs)->exists());
    }

    public function test_tc056_sixth_attempt_within_a_minute_gets_429(): void
    {
        $user = $this->userWithPassword();
        $this->signIn($user);

        $payload = [
            'current_password' => 'salah-salah',
            'password' => 'password-baru-2',
            'password_confirmation' => 'password-baru-2',
        ];

        for ($i = 1; $i <= 5; $i++) {
            $this->putJson('/api/profile/password', $payload)->assertUnprocessable();
        }

        $this->putJson('/api/profile/password', $payload)->assertStatus(429);
    }
}
